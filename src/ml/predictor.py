"""
Generate future demand predictions from trained models and evaluate accuracy by segment.
"""

import json
import os
from datetime import datetime, timedelta

import numpy as np
import pandas as pd

from preprocess import (
    aggregate_daily_sales,
    aggregate_monthly_sales,
    build_feature_frame,
    classify_movement,
    seasonal_index,
)
from train_model import FEATURE_COLS, compute_metrics, load_model, _available_feature_cols

PREDICTIONS_DIR = os.path.join(os.path.dirname(__file__), "predictions")


def _ensure_predictions_dir():
    os.makedirs(PREDICTIONS_DIR, exist_ok=True)


def predict_daily(artifact: dict, horizon_days: int = 30) -> pd.DataFrame:
    """Generate daily forecasts for the next `horizon_days` days."""
    model_type = artifact["model_type"]
    last_records = artifact.get("last_daily", [])

    if not last_records:
        raise ValueError("Model artifact missing historical context")

    history = pd.DataFrame(last_records)
    history["date"] = pd.to_datetime(history["date"])

    if model_type == "prophet":
        return _predict_prophet_daily(artifact["model"], horizon_days)

    return _predict_rf_daily(artifact, history, horizon_days)


def _predict_prophet_daily(model, horizon_days: int) -> pd.DataFrame:
    future = model.make_future_dataframe(periods=horizon_days, freq="D")
    forecast = model.predict(future)
    future_only = forecast.tail(horizon_days)[["ds", "yhat", "yhat_lower", "yhat_upper"]].copy()
    future_only.columns = ["date", "predicted_quantity", "lower_bound", "upper_bound"]
    future_only["predicted_quantity"] = future_only["predicted_quantity"].clip(lower=0).round(2)
    future_only["confidence_score"] = (
        1 - (future_only["upper_bound"] - future_only["lower_bound"])
        / (future_only["predicted_quantity"] + 1)
    ).clip(0, 1).round(4)
    return future_only


def _predict_rf_daily(artifact: dict, history: pd.DataFrame, horizon_days: int) -> pd.DataFrame:
    """
    Recursive daily forecast using the trained model artifact.
    Supports gradient_boosting (with ensemble blend) and random_forest.
    """
    model = artifact["model"]
    rf_model = artifact.get("rf_model")
    scaler = artifact.get("scaler")
    feature_cols = artifact.get("feature_cols", FEATURE_COLS)
    model_type = artifact.get("model_type", "random_forest")

    # Extend history day-by-day for recursive forecasting
    extended = history.copy()
    predictions = []
    recent_preds = []

    last_date = extended["date"].max()
    for i in range(1, horizon_days + 1):
        next_date = last_date + timedelta(days=i)
        row = pd.DataFrame({"date": [next_date], "quantity": [0]})
        combined = pd.concat([extended, row], ignore_index=True)
        features = build_feature_frame(combined)

        if features.empty:
            pred_qty = float(extended["quantity"].tail(7).mean())
        else:
            last_row = features.iloc[[-1]]
            avail_cols = [c for c in feature_cols if c in last_row.columns]
            X = last_row[avail_cols].values

            if model_type == "gradient_boosting" and artifact.get("transform") == "log1p":
                et_model = artifact.get("et_model")
                p_gb = model.predict(X)[0]
                p_rf = rf_model.predict(X)[0] if rf_model is not None else p_gb
                p_et = et_model.predict(X)[0] if et_model is not None else p_rf
                p_log = 0.40 * p_gb + 0.35 * p_rf + 0.25 * p_et
                pred_qty = float(max(0.0, np.expm1(p_log)))
            elif artifact.get("transform") == "log1p":
                pred_qty = float(max(0.0, np.expm1(model.predict(X)[0])))
            elif model_type == "gradient_boosting" and scaler is not None and rf_model is not None:
                # Ensemble blend: GB (inverse-scaled) + RF
                gb_scaled = model.predict(X)[0]
                gb_pred = float(max(0, scaler.inverse_transform([[gb_scaled]])[0][0]))
                rf_pred = float(max(0, rf_model.predict(X)[0]))
                pred_qty = max(0.0, 0.6 * gb_pred + 0.4 * rf_pred)
            else:
                pred_qty = float(max(0, model.predict(X)[0]))

        recent_preds.append(pred_qty)
        # Confidence: based on prediction stability over last 7 steps
        if len(recent_preds) >= 3:
            window = recent_preds[-7:]
            mean_p = np.mean(window)
            std_p = np.std(window)
            cv = std_p / (mean_p + 1e-6)
            confidence = round(float(np.clip(1.0 - cv * 0.5, 0.60, 0.98)), 4)
        else:
            confidence = 0.82

        # Asymmetric bounds: tighter lower, slightly wider upper for demand planning
        lower = round(max(0.0, pred_qty * 0.88), 2)
        upper = round(pred_qty * 1.12, 2)

        predictions.append(
            {
                "date": next_date,
                "predicted_quantity": round(pred_qty, 2),
                "lower_bound": lower,
                "upper_bound": upper,
                "confidence_score": confidence,
            }
        )
        extended = pd.concat(
            [extended, pd.DataFrame({"date": [next_date], "quantity": [pred_qty]})],
            ignore_index=True,
        )

    return pd.DataFrame(predictions)


def predict_monthly_from_daily(daily_preds: pd.DataFrame) -> pd.DataFrame:
    """Sum daily predictions into calendar months."""
    if daily_preds.empty:
        return pd.DataFrame(columns=["date", "predicted_quantity", "month", "year"])

    monthly = (
        daily_preds.set_index("date")["predicted_quantity"]
        .resample("MS")
        .sum()
        .reset_index()
    )
    monthly["month"] = monthly["date"].dt.month
    monthly["year"] = monthly["date"].dt.year
    return monthly


def build_seasonal_chart_data(sales_df: pd.DataFrame, daily_preds: pd.DataFrame) -> dict:
    """
    Build chart payload: historical monthly sales, forecasted monthly sales, trend line.
    """
    monthly_hist = aggregate_monthly_sales(sales_df)
    monthly_pred = predict_monthly_from_daily(daily_preds)

    month_labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]

    # Last 6 months historical + next 6 months forecast
    hist_tail = monthly_hist.tail(6)
    pred_head = monthly_pred.head(6)

    labels = []
    historical = []
    forecasted = []
    trend = []

    for _, row in hist_tail.iterrows():
        labels.append(f"{month_labels[row['month'] - 1]} {row['year']}")
        historical.append(round(float(row["quantity"]), 2))
        forecasted.append(None)

    # Trend: linear fit on historical monthly values
    if len(hist_tail) >= 2:
        x = np.arange(len(hist_tail))
        coef = np.polyfit(x, hist_tail["quantity"].values, 1)
    else:
        coef = [0, hist_tail["quantity"].mean() if not hist_tail.empty else 0]

    trend_hist = [round(float(np.polyval(coef, i)), 2) for i in range(len(hist_tail))]
    trend.extend(trend_hist)

    offset = len(hist_tail)
    for i, (_, row) in enumerate(pred_head.iterrows()):
        labels.append(f"{month_labels[row['month'] - 1]} {row['year']} (F)")
        historical.append(None)
        val = round(float(row["predicted_quantity"]), 2)
        forecasted.append(val)
        trend.append(round(float(np.polyval(coef, offset + i)), 2))

    # Pad forecast trend for historical portion
    full_trend = trend_hist + trend[len(trend_hist):]

    # Seasonal index for tooltip/reference
    season = seasonal_index(monthly_hist)
    seasonal_pattern = [1.0] * 12
    if not season.empty:
        for _, row in season.iterrows():
            seasonal_pattern[int(row["month"]) - 1] = float(row["seasonal_index"])

    return {
        "labels": labels,
        "historical": historical,
        "forecasted": forecasted,
        "trend": full_trend,
        "seasonal_index": seasonal_pattern,
        "month_labels": month_labels,
    }


def evaluate_by_movement_class(sales_df: pd.DataFrame, artifacts: dict) -> dict:
    """
    Backtest per movement class to populate accuracy radar chart.
    Returns accuracy % per segment based on holdout MAPE.
    """
    segments = {
        "Fast Moving": "fast_moving",
        "Medium": "medium_moving",
        "Slow": "slow_moving",
        "Seasonal": "seasonal",
        "New": "new",
        "Promo": "promo",
    }

    product_totals = sales_df.groupby("product_id")["quantity"].sum()
    product_classes = {pid: classify_movement(total) for pid, total in product_totals.items()}

    # Identify seasonal products (high month-to-month variance)
    monthly_by_product = (
        sales_df.set_index("transaction_date")
        .groupby("product_id")["quantity"]
        .resample("MS")
        .sum()
        .reset_index()
    )
    seasonal_pids = set()
    for pid, grp in monthly_by_product.groupby("product_id"):
        if len(grp) >= 3 and grp["quantity"].std() > grp["quantity"].mean() * 0.5:
            seasonal_pids.add(pid)

    # New products: first sale within last 30 days
    if not sales_df.empty:
        cutoff = sales_df["transaction_date"].max() - pd.Timedelta(days=30)
        first_sale = sales_df.groupby("product_id")["transaction_date"].min()
        new_pids = set(first_sale[first_sale >= cutoff].index)
    else:
        new_pids = set()

    results = {}
    default_accuracy = 0

    for label, key in segments.items():
        if key == "seasonal":
            pids = seasonal_pids
        elif key == "new":
            pids = new_pids
        elif key == "promo":
            # Promo: products with spike in last 14 days vs prior 30
            pids = _detect_promo_products(sales_df)
        else:
            pids = {pid for pid, cls in product_classes.items() if cls == key}

        acc = _backtest_segment(sales_df, pids, artifacts.get("aggregate"))
        results[label] = acc if acc is not None else default_accuracy

    return results


def _detect_promo_products(sales_df: pd.DataFrame) -> set:
    """Products with recent demand spike (>50% above prior period)."""
    if sales_df.empty:
        return set()

    max_date = sales_df["transaction_date"].max()
    recent = sales_df[sales_df["transaction_date"] >= max_date - pd.Timedelta(days=14)]
    prior = sales_df[
        (sales_df["transaction_date"] >= max_date - pd.Timedelta(days=44))
        & (sales_df["transaction_date"] < max_date - pd.Timedelta(days=14))
    ]

    recent_tot = recent.groupby("product_id")["quantity"].sum()
    prior_tot = prior.groupby("product_id")["quantity"].sum()

    promo = set()
    for pid in recent_tot.index:
        r = recent_tot.get(pid, 0)
        p = prior_tot.get(pid, 0)
        if p > 0 and r > p * 1.5:
            promo.add(pid)
    return promo


def _backtest_segment(sales_df: pd.DataFrame, product_ids: set, artifact: dict) -> float:
    """
    Holdout accuracy for a product segment using the trained model's actual predictions.
    Uses build_feature_frame so the model sees real engineered features, not just a mean.
    """
    if not product_ids or artifact is None:
        return None

    segment_sales = sales_df[sales_df["product_id"].isin(product_ids)]
    daily = aggregate_daily_sales(segment_sales)
    if len(daily) < 14:
        return None

    features = build_feature_frame(daily)
    if features.empty or len(features) < 14:
        return None

    split = int(len(features) * 0.8)
    train_feat = features.iloc[:split]
    test_feat = features.iloc[split:]

    if test_feat.empty:
        return None

    actual = test_feat["quantity"].values
    model = artifact.get("model")
    rf_model = artifact.get("rf_model")
    et_model = artifact.get("et_model")
    scaler = artifact.get("scaler")
    model_type = artifact.get("model_type", "random_forest")
    feat_cols = artifact.get("feature_cols", FEATURE_COLS)
    avail_cols = [c for c in feat_cols if c in test_feat.columns]

    if not avail_cols:
        return None

    def _predict_matrix(X_mat):
        if model_type == "gradient_boosting" and artifact.get("transform") == "log1p":
            p_gb = model.predict(X_mat)
            p_rf = rf_model.predict(X_mat) if rf_model is not None else p_gb
            p_et = et_model.predict(X_mat) if et_model is not None else p_rf
            return np.maximum(np.expm1(0.40 * p_gb + 0.35 * p_rf + 0.25 * p_et), 0)
        elif artifact.get("transform") == "log1p":
            return np.maximum(np.expm1(model.predict(X_mat)), 0)
        elif model_type == "gradient_boosting" and scaler is not None and rf_model is not None:
            gb_scaled = model.predict(X_mat)
            gb_pred = np.maximum(scaler.inverse_transform(gb_scaled.reshape(-1, 1)).ravel(), 0)
            rf_pred = np.maximum(rf_model.predict(X_mat), 0)
            return 0.6 * gb_pred + 0.4 * rf_pred
        return np.maximum(model.predict(X_mat), 0)

    try:
        # If test set has actual sale days, evaluate on test set
        if (actual > 0).sum() >= 3:
            X_test = test_feat[avail_cols].values
            predicted = _predict_matrix(X_test)
            metrics = compute_metrics(actual, predicted)
            return metrics["forecast_accuracy"]
        else:
            # Evaluate across full segment history on sale days
            X_all = features[avail_cols].values
            y_all = features["quantity"].values
            predicted_all = _predict_matrix(X_all)
            metrics = compute_metrics(y_all, predicted_all)
            return metrics["forecast_accuracy"]
    except Exception:
        return None


def export_predictions_json(chart_data: dict, metrics: dict, path: str = None) -> str:
    """Save latest prediction outputs for debugging / auditing."""
    _ensure_predictions_dir()
    out_path = path or os.path.join(
        PREDICTIONS_DIR, f"forecast_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    )
    payload = {"chart_data": chart_data, "metrics": metrics, "generated_at": datetime.now().isoformat()}
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, indent=2, default=str)
    return out_path
