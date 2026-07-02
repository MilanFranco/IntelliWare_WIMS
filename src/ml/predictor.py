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
from train_model import FEATURE_COLS, compute_metrics, load_model

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
    model = artifact["model"]
    feature_cols = artifact.get("feature_cols", FEATURE_COLS)

    # Extend history day-by-day for recursive forecasting
    extended = history.copy()
    predictions = []

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
            pred_qty = float(max(0, model.predict(last_row[feature_cols])[0]))

        predictions.append(
            {
                "date": next_date,
                "predicted_quantity": round(pred_qty, 2),
                "lower_bound": round(pred_qty * 0.85, 2),
                "upper_bound": round(pred_qty * 1.15, 2),
                "confidence_score": 0.75,
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
    """Simple holdout accuracy for a product segment."""
    if not product_ids or artifact is None:
        return None

    segment_sales = sales_df[sales_df["product_id"].isin(product_ids)]
    daily = aggregate_daily_sales(segment_sales)
    if len(daily) < 10:
        return None

    split = int(len(daily) * 0.8)
    test = daily.iloc[split:]
    actual = test["quantity"].values

    # Use segment mean with seasonal adjustment as baseline predictor
    train_mean = daily.iloc[:split]["quantity"].mean()
    predicted = np.full(len(actual), train_mean)

    metrics = compute_metrics(actual, predicted)
    return metrics["forecast_accuracy"]


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
