#!/usr/bin/env python
"""
IntelliWare ML Forecasting Pipeline v3.0 (Dynamic & Intelligent)
=================================================================
Entry point called by PHP (MLForecastManager).

Workflow:
  1. Load transaction history + product catalog from MySQL
  2. Clean & aggregate into time-series with Philippine seasonal drivers
  3. Auto-select and train best model (Prophet → XGBoost → GB Ensemble)
  4. Walk-forward cross-validation for honest accuracy
  5. Generate multi-horizon predictions (30-day daily + 6-month seasonal)
  6. Detect demand spike anomalies (Isolation Forest + Z-score)
  7. Persist results to forecast_results, forecast_metrics, and forecast
  8. Emit clean JSON summary for PHP
"""

import json
import sys
import traceback
from datetime import date, datetime, timedelta

import numpy as np
import pandas as pd
import mysql.connector

from config_loader import load_db_config
from data_loader import get_connection, load_product_catalog, load_sales_only
from predictor import (
    build_seasonal_chart_data,
    evaluate_by_movement_class,
    export_predictions_json,
    generate_extended_monthly_forecast,
    predict_daily,
)
from preprocess import (
    aggregate_daily_sales,
    aggregate_daily_sales_with_stock,
    aggregate_monthly_sales,
    get_philippine_multiplier,
)
from train_model import (
    MODEL_VERSION,
    load_model,
    model_exists,
    save_model,
    save_training_metadata,
    train_category_model,
    train_forecast_model,
)

HORIZON_DAYS = 30
FORECAST_MONTHS = 6
TOP_PRODUCTS = 30


def detect_and_save_anomalies(conn, sales_df: pd.DataFrame) -> int:
    """
    Detect statistical sales spikes (anomalies) using dual detection:
      1. Z-score >= 1.5 on per-product rolling quantities
      2. Isolation Forest on global transaction quantities (5% contamination)
    Saves detected anomalies to the anomalies table.
    """
    cursor = conn.cursor()
    cursor.execute("DELETE FROM anomalies WHERE detection_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")

    anomalies_count = 0
    if sales_df.empty:
        cursor.close()
        return 0

    # Global Isolation Forest to pre-screen bulk orders
    iso_inlier_mask = np.ones(len(sales_df), dtype=bool)
    try:
        from sklearn.ensemble import IsolationForest
        iso   = IsolationForest(contamination=0.06, random_state=42)
        preds = iso.fit_predict(sales_df[["quantity"]].values)
        iso_inlier_mask = (preds == -1)   # -1 = anomaly
    except Exception:
        pass

    grouped = sales_df.groupby("product_id")
    for pid, group in grouped:
        q      = group["quantity"].values
        mean_q = float(np.mean(q))
        std_q  = float(np.std(q))

        for idx, row in group.iterrows():
            qty = float(row["quantity"])
            z   = (qty - mean_q) / std_q if std_q > 0 else 0.0

            is_zscore_spike  = (std_q > 0 and z >= 1.5 and qty >= 15)
            is_bulk_surge    = (qty >= 50)
            is_iso_anomaly   = (iso_inlier_mask[sales_df.index.get_loc(idx)] if idx in sales_df.index else False)

            if is_zscore_spike or is_bulk_surge or is_iso_anomaly:
                tx_date = (
                    row["transaction_date"].date()
                    if hasattr(row["transaction_date"], "date")
                    else row["transaction_date"]
                )
                anomaly_score = round(
                    float(max(1.5, z if z > 0 else (qty / max(mean_q, 1.0)))), 4
                )
                try:
                    cursor.execute(
                        """
                        INSERT IGNORE INTO anomalies (product_id, detection_date, quantity, anomaly_score)
                        VALUES (%s, %s, %s, %s)
                        """,
                        (int(pid), tx_date, qty, anomaly_score),
                    )
                    anomalies_count += 1
                except Exception:
                    pass

    conn.commit()
    cursor.close()
    return anomalies_count


def save_results_to_db(conn, aggregate_artifact, daily_preds, monthly_preds, sales_df, metrics, model_type):
    """Write dynamic predictions and metrics to MySQL."""
    cursor = conn.cursor()
    model_version = aggregate_artifact.get("model_version", MODEL_VERSION)
    training_date = datetime.now()

    # 1. Update ml_training_state
    cursor.execute(
        """
        INSERT INTO ml_training_state (id, last_training_at, last_transaction_count, auto_retrain_threshold)
        VALUES (1, NOW(), %s, 50)
        ON DUPLICATE KEY UPDATE
            last_training_at = NOW(),
            last_transaction_count = VALUES(last_transaction_count)
        """,
        (len(sales_df),),
    )

    # 2. Train specialized category models
    cat_models = {}
    cat_daily_map = {}
    cat_monthly_map = {}
    unique_categories = [c for c in sales_df["category"].dropna().unique() if c]

    for cat in unique_categories:
        try:
            cat_artifact, _ = train_category_model(sales_df, cat)
            cat_models[cat] = cat_artifact
            cat_daily_map[cat] = predict_daily(cat_artifact, horizon_days=HORIZON_DAYS)
            cat_monthly_map[cat] = generate_extended_monthly_forecast(
                sales_df, forecast_months=FORECAST_MONTHS, category=cat
            )
        except Exception:
            pass

    # 3. Purge obsolete model versions and stale aggregate predictions
    cursor.execute("DELETE FROM forecast_results WHERE model_version != %s", (model_version,))
    cursor.execute("DELETE FROM forecast_results WHERE product_id = 0")

    # 4. Save aggregate predictions
    agg_records = []
    for _, row in daily_preds.iterrows():
        fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
        agg_records.append((
            0,
            fd,
            float(row["predicted_quantity"]),
            float(row.get("confidence_score", 0.85)),
            model_version,
            "daily",
        ))

    for _, row in monthly_preds.iterrows():
        fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
        agg_records.append((
            0,
            fd,
            float(row["predicted_quantity"]),
            0.90,
            model_version,
            "monthly",
        ))

    cursor.executemany(
        """
        INSERT INTO forecast_results
            (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
        VALUES (%s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            predicted_quantity = VALUES(predicted_quantity),
            confidence_score = VALUES(confidence_score),
            model_version = VALUES(model_version)
        """,
        agg_records,
    )

    # 5. Process all products with sales history
    prod_grouped = (
        sales_df.groupby(["product_id", "category"])
        .agg(
            total_qty=("quantity", "sum"),
            tx_count=("quantity", "count"),
            active_days=("transaction_date", lambda d: d.dt.date.nunique() if hasattr(d.dt, "date") else len(d)),
        )
        .reset_index()
        .sort_values(by="total_qty", ascending=False)
    )

    cat_totals = sales_df.groupby("category")["quantity"].sum().to_dict()

    daily_prod_records = []
    monthly_prod_records = []
    legacy_forecast_records = []
    products_processed = 0

    top_pids = set(prod_grouped.head(TOP_PRODUCTS)["product_id"].tolist())

    for _, p_row in prod_grouped.iterrows():
        pid = int(p_row["product_id"])
        cat = str(p_row["category"])
        total_q = float(p_row["total_qty"])
        act_d = int(p_row["active_days"])

        prod_daily = None
        prod_monthly = None
        prod_model_label = "category_tri_boost_scaled"
        conf_score = 0.82

        # Top sellers with sufficient active days: individual Tri-Boost model
        if pid in top_pids and act_d >= 3:
            try:
                prod_artifact, prod_metrics, p_model = train_forecast_model(
                    sales_df, product_id=pid, category=cat
                )
                prod_daily = predict_daily(prod_artifact, horizon_days=HORIZON_DAYS)
                prod_monthly = generate_extended_monthly_forecast(
                    sales_df, forecast_months=FORECAST_MONTHS, product_id=pid, category=cat
                )
                prod_model_label = p_model
                conf_score = float(prod_metrics.get("forecast_accuracy", 82.0)) / 100.0
                save_model(prod_artifact, suffix=f"product_{pid}")
            except Exception:
                prod_daily = None
                prod_monthly = None

        # Category-proportional fallback (all other products & failed individual fits)
        if prod_daily is None or prod_monthly is None:
            cat_sum = max(float(cat_totals.get(cat, 1.0)), 1.0)
            p_share = max(0.001, total_q / cat_sum)

            cat_d = cat_daily_map.get(cat, daily_preds)
            cat_m = cat_monthly_map.get(cat, monthly_preds)

            prod_daily = cat_d.copy()
            prod_daily["predicted_quantity"] = np.maximum(
                np.round(prod_daily["predicted_quantity"] * p_share, 2),
                0.05,
            )

            prod_monthly = cat_m.copy()
            prod_monthly["predicted_quantity"] = np.maximum(
                np.round(prod_monthly["predicted_quantity"] * p_share, 2),
                1.0,
            )

        # Collect daily records
        for _, row in prod_daily.iterrows():
            fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
            daily_prod_records.append((
                pid,
                fd,
                float(row["predicted_quantity"]),
                conf_score,
                model_version,
                "daily",
            ))

        # Collect monthly records
        for _, row in prod_monthly.iterrows():
            fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
            pred_qty = float(row["predicted_quantity"])
            monthly_prod_records.append((
                pid,
                fd,
                pred_qty,
                conf_score,
                model_version,
                "monthly",
            ))
            legacy_forecast_records.append((
                pid,
                fd,
                int(round(pred_qty)),
                pred_qty,
                round(pred_qty * 0.85, 2),
                round(pred_qty * 1.15, 2),
                prod_model_label,
            ))

        products_processed += 1

    # Clear old per-product entries before saving new ones
    cursor.execute("DELETE FROM forecast_results WHERE product_id > 0")
    cursor.execute("DELETE FROM forecast")

    # Batch insert all daily predictions
    if daily_prod_records:
        cursor.executemany(
            """
            INSERT INTO forecast_results
                (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                predicted_quantity = VALUES(predicted_quantity),
                confidence_score = VALUES(confidence_score),
                model_version = VALUES(model_version)
            """,
            daily_prod_records,
        )

    # Batch insert all monthly projections
    if monthly_prod_records:
        cursor.executemany(
            """
            INSERT INTO forecast_results
                (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                predicted_quantity = VALUES(predicted_quantity),
                confidence_score = VALUES(confidence_score),
                model_version = VALUES(model_version)
            """,
            monthly_prod_records,
        )

    # Batch insert legacy forecast table
    if legacy_forecast_records:
        cursor.executemany(
            """
            INSERT INTO forecast
                (product_id, forecast_date, predicted_qty, predicted_quantity, lower_bound, upper_bound, model_used)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            """,
            legacy_forecast_records,
        )

    total_forecasts_generated = len(agg_records) + len(daily_prod_records) + len(monthly_prod_records)

    # 6. Save forecast_metrics
    cursor.execute(
        """
        INSERT INTO forecast_metrics
            (training_date, mae, rmse, mape, forecast_accuracy, error_rate,
             model_type, model_version, products_processed, forecasts_generated)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """,
        (
            training_date,
            metrics.get("mae", 0),
            metrics.get("rmse", 0),
            metrics.get("mape", 0),
            metrics.get("forecast_accuracy", 0),
            metrics.get("error_rate", 0),
            model_type,
            model_version,
            products_processed,
            total_forecasts_generated,
        ),
    )

    # 7. Segment accuracy for radar chart
    segment_acc = evaluate_by_movement_class(sales_df, {"aggregate": aggregate_artifact})
    cursor.execute(
        """
        INSERT INTO forecast_metrics
            (training_date, mae, rmse, mape, forecast_accuracy, error_rate,
             model_type, model_version, segment_accuracy_json)
        VALUES (%s, 0, 0, 0, %s, %s, %s, %s, %s)
        """,
        (
            training_date,
            metrics["forecast_accuracy"],
            metrics["error_rate"],
            f"{model_type}_segments",
            model_version,
            json.dumps(segment_acc),
        ),
    )

    conn.commit()
    cursor.close()
    return products_processed, total_forecasts_generated, segment_acc


def run_pipeline(predict_only: bool = False) -> dict:
    config = load_db_config()
    conn = get_connection(config)

    try:
        sales_df = load_sales_only(conn)

        if sales_df.empty:
            return {
                "status": "error",
                "error": "No transaction history found. Record stock_out transactions before training.",
            }

        # Train or load aggregate model
        if predict_only and model_exists("aggregate"):
            artifact   = load_model("aggregate")
            model_type = artifact["model_type"]
            metrics    = artifact.get("metrics", {})
        else:
            artifact, metrics, model_type = train_forecast_model(sales_df)
            save_model(artifact, suffix="aggregate")
            save_training_metadata(artifact, metrics)

        # Generate multi-horizon forecasts
        daily_preds = predict_daily(artifact, horizon_days=HORIZON_DAYS)
        monthly_preds = generate_extended_monthly_forecast(sales_df, forecast_months=FORECAST_MONTHS)
        chart_data = build_seasonal_chart_data(sales_df, daily_preds, forecast_months=FORECAST_MONTHS)

        # Detect active anomalies
        anomalies_detected = detect_and_save_anomalies(conn, sales_df)

        # Save to MySQL
        products_processed, forecasts_generated, segment_acc = save_results_to_db(
            conn, artifact, daily_preds, monthly_preds, sales_df, metrics, model_type
        )

        export_predictions_json(chart_data, metrics)

        return {
            "status": "success",
            "model_type": model_type,
            "model_version": artifact.get("model_version", MODEL_VERSION),
            "products_processed": products_processed,
            "forecasts_generated": forecasts_generated,
            "anomalies_detected": anomalies_detected,
            "metrics": metrics,
            "segment_accuracy": segment_acc,
            "chart_data": chart_data,
        }

    finally:
        conn.close()


def main():
    predict_only = "--predict-only" in sys.argv

    try:
        result = run_pipeline(predict_only=predict_only)
        print(json.dumps(result))
        sys.exit(0 if result.get("status") == "success" else 1)
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e), "trace": traceback.format_exc()}))
        sys.exit(1)


if __name__ == "__main__":
    main()
