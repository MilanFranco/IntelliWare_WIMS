#!/usr/bin/env python
"""
IntelliWare ML Forecasting Pipeline (Dynamic & Intelligent)
===========================================================
Entry point called by PHP (MLForecastManager).

Workflow:
  1. Load transaction history from MySQL
  2. Clean & aggregate into time-series with Philippine seasonal drivers
  3. Train forecasting models (Tri-Model Gradient Boosting Ensemble)
  4. Backtest and compute robust WAPE / MAPE accuracy
  5. Generate multi-horizon predictions (30-day daily + 6-month seasonal)
  6. Detect demand spike anomalies and record to anomalies table
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
    aggregate_monthly_sales,
    get_philippine_multiplier,
)
from train_model import (
    load_model,
    model_exists,
    save_model,
    save_training_metadata,
    train_forecast_model,
)

HORIZON_DAYS = 30
FORECAST_MONTHS = 6
TOP_PRODUCTS = 20


def detect_and_save_anomalies(conn, sales_df: pd.DataFrame) -> int:
    """
    Detect statistical sales spikes (anomalies) and save them to the anomalies table.
    Uses rolling Z-score / outlier detection on transaction quantities.
    """
    cursor = conn.cursor()
    cursor.execute("DELETE FROM anomalies WHERE detection_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")

    anomalies_count = 0
    if sales_df.empty:
        cursor.close()
        return 0

    # Group by product and detect volume spikes > 1.5 std or bulk surges >= 50
    grouped = sales_df.groupby("product_id")
    for pid, group in grouped:
        q = group["quantity"].values
        mean_q = float(np.mean(q))
        std_q = float(np.std(q))

        for _, row in group.iterrows():
            qty = float(row["quantity"])
            z = (qty - mean_q) / std_q if std_q > 0 else 0.0

            # Spike detection: Z-score >= 1.5 with quantity >= 15, or bulk surge >= 50
            if (std_q > 0 and z >= 1.5 and qty >= 15) or (qty >= 50):
                tx_date = row["transaction_date"].date() if hasattr(row["transaction_date"], "date") else row["transaction_date"]
                anomaly_score = round(float(max(1.5, z if z > 0 else (qty / max(mean_q, 1.0)))), 4)

                cursor.execute(
                    """
                    INSERT INTO anomalies (product_id, detection_date, quantity, anomaly_score)
                    VALUES (%s, %s, %s, %s)
                    """,
                    (int(pid), tx_date, qty, anomaly_score),
                )
                anomalies_count += 1

    conn.commit()
    cursor.close()
    return anomalies_count


def save_results_to_db(conn, aggregate_artifact, daily_preds, monthly_preds, sales_df, metrics, model_type):
    """Write dynamic predictions and metrics to MySQL."""
    cursor = conn.cursor()
    model_version = aggregate_artifact.get("model_version", "2.5.0")
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

    # 2. forecast_metrics
    cursor.execute(
        """
        INSERT INTO forecast_metrics
            (training_date, mae, rmse, mape, forecast_accuracy, error_rate,
             model_type, model_version, products_processed, forecasts_generated)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """,
        (
            training_date,
            metrics["mae"],
            metrics["rmse"],
            metrics["mape"],
            metrics["forecast_accuracy"],
            metrics["error_rate"],
            model_type,
            model_version,
            sales_df["product_id"].nunique() if not sales_df.empty else 0,
            len(daily_preds) + TOP_PRODUCTS * FORECAST_MONTHS,
        ),
    )

    # Clear old aggregate predictions
    cursor.execute("DELETE FROM forecast_results WHERE product_id = 0")

    # 3. Aggregate daily predictions -> forecast_results (product_id = 0, horizon_type = 'daily')
    for _, row in daily_preds.iterrows():
        fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
        cursor.execute(
            """
            INSERT INTO forecast_results
                (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
            VALUES (0, %s, %s, %s, %s, 'daily')
            ON DUPLICATE KEY UPDATE
                predicted_quantity = VALUES(predicted_quantity),
                confidence_score = VALUES(confidence_score),
                model_version = VALUES(model_version)
            """,
            (
                fd,
                float(row["predicted_quantity"]),
                float(row.get("confidence_score", 0.85)),
                model_version,
            ),
        )

    # 4. Aggregate monthly projections -> forecast_results (product_id = 0, horizon_type = 'monthly')
    for _, row in monthly_preds.iterrows():
        fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
        cursor.execute(
            """
            INSERT INTO forecast_results
                (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
            VALUES (0, %s, %s, %s, %s, 'monthly')
            ON DUPLICATE KEY UPDATE
                predicted_quantity = VALUES(predicted_quantity),
                confidence_score = VALUES(confidence_score),
                model_version = VALUES(model_version)
            """,
            (
                fd,
                float(row["predicted_quantity"]),
                0.90,
                model_version,
            ),
        )

    # 5. Per-product forecasts for top sellers
    top_prods = (
        sales_df.groupby(["product_id", "category"])["quantity"]
        .sum()
        .reset_index()
        .sort_values(by="quantity", ascending=False)
        .head(TOP_PRODUCTS)
    )

    forecasts_generated = len(daily_preds)
    products_processed = 0

    for _, p_row in top_prods.iterrows():
        pid = int(p_row["product_id"])
        cat = str(p_row["category"])

        try:
            prod_artifact, prod_metrics, prod_model = train_forecast_model(
                sales_df, product_id=pid, category=cat
            )
            prod_daily = predict_daily(prod_artifact, horizon_days=HORIZON_DAYS)
            prod_monthly = generate_extended_monthly_forecast(
                sales_df, forecast_months=FORECAST_MONTHS, product_id=pid, category=cat
            )

            cursor.execute("DELETE FROM forecast_results WHERE product_id = %s", (pid,))
            cursor.execute("DELETE FROM forecast WHERE product_id = %s", (pid,))

            # Save daily product predictions
            for _, row in prod_daily.iterrows():
                fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
                cursor.execute(
                    """
                    INSERT INTO forecast_results
                        (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
                    VALUES (%s, %s, %s, %s, %s, 'daily')
                    ON DUPLICATE KEY UPDATE
                        predicted_quantity = VALUES(predicted_quantity),
                        confidence_score = VALUES(confidence_score),
                        model_version = VALUES(model_version)
                    """,
                    (
                        pid,
                        fd,
                        float(row["predicted_quantity"]),
                        float(row.get("confidence_score", 0.85)),
                        model_version,
                    ),
                )
                forecasts_generated += 1

            # Save monthly product predictions & legacy forecast table
            for _, row in prod_monthly.iterrows():
                fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
                pred_qty = float(row["predicted_quantity"])
                cursor.execute(
                    """
                    INSERT INTO forecast_results
                        (product_id, forecast_date, predicted_quantity, confidence_score, model_version, horizon_type)
                    VALUES (%s, %s, %s, %s, %s, 'monthly')
                    ON DUPLICATE KEY UPDATE
                        predicted_quantity = VALUES(predicted_quantity),
                        confidence_score = VALUES(confidence_score),
                        model_version = VALUES(model_version)
                    """,
                    (
                        pid,
                        fd,
                        pred_qty,
                        float(prod_metrics.get("forecast_accuracy", 82.0)) / 100.0,
                        model_version,
                    ),
                )
                cursor.execute(
                    """
                    INSERT INTO forecast
                        (product_id, forecast_date, predicted_qty, predicted_quantity, lower_bound, upper_bound, model_used)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    """,
                    (
                        pid,
                        fd,
                        int(round(pred_qty)),
                        pred_qty,
                        round(pred_qty * 0.85, 2),
                        round(pred_qty * 1.15, 2),
                        prod_model,
                    ),
                )
                forecasts_generated += 1

            products_processed += 1
            save_model(prod_artifact, suffix=f"product_{pid}")
        except Exception:
            continue

    # 6. Segment accuracy for radar chart
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
    return products_processed, forecasts_generated, segment_acc


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
            artifact = load_model("aggregate")
            model_type = artifact["model_type"]
            metrics = artifact.get("metrics", {})
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
            "model_version": artifact.get("model_version", "2.5.0"),
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
