#!/usr/bin/env python
"""
IntelliWare ML Forecasting Pipeline
====================================
Entry point called by PHP (MLForecastManager).

Workflow:
  1. Load transaction history from MySQL
  2. Clean & aggregate into time-series
  3. Train forecasting model (Prophet or Random Forest)
  4. Backtest and compute MAE / RMSE / MAPE / accuracy
  5. Generate future predictions
  6. Persist results to forecast_results, forecast_metrics, and legacy forecast table
  7. Emit JSON summary for PHP

Usage:
  python run_pipeline.py [--predict-only]
"""

import json
import sys
import traceback
from datetime import date, datetime

import mysql.connector

from config_loader import load_db_config
from data_loader import get_connection, load_product_catalog, load_sales_only
from predictor import (
    build_seasonal_chart_data,
    evaluate_by_movement_class,
    export_predictions_json,
    predict_daily,
)
from preprocess import aggregate_daily_sales, aggregate_monthly_sales
from train_model import (
    load_model,
    model_exists,
    save_model,
    save_training_metadata,
    train_forecast_model,
)

HORIZON_DAYS = 30
FORECAST_MONTHS = 12
TOP_PRODUCTS = 20


def save_results_to_db(conn, aggregate_artifact, daily_preds, sales_df, metrics, model_type):
    """Write predictions and metrics to MySQL."""
    cursor = conn.cursor()
    model_version = aggregate_artifact.get("model_version", "1.0.0")
    training_date = datetime.now()

    # --- forecast_metrics ---
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

    # Clear stale aggregate predictions (product_id = 0)
    cursor.execute("DELETE FROM forecast_results WHERE product_id = 0")

    # Aggregate daily predictions -> forecast_results (product_id=0 = system-wide)
    for _, row in daily_preds.iterrows():
        cursor.execute(
            """
            INSERT INTO forecast_results
                (product_id, forecast_date, predicted_quantity, confidence_score, model_version)
            VALUES (0, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                predicted_quantity = VALUES(predicted_quantity),
                confidence_score = VALUES(confidence_score),
                model_version = VALUES(model_version)
            """,
            (
                row["date"].date() if hasattr(row["date"], "date") else row["date"],
                float(row["predicted_quantity"]),
                float(row.get("confidence_score", 0.75)),
                model_version,
            ),
        )

    # Per-product monthly forecasts for top sellers
    top_ids = (
        sales_df.groupby("product_id")["quantity"]
        .sum()
        .sort_values(ascending=False)
        .head(TOP_PRODUCTS)
        .index.tolist()
    )

    forecasts_generated = len(daily_preds)
    products_processed = 0

    for pid in top_ids:
        try:
            artifact, prod_metrics, prod_model = train_forecast_model(sales_df, product_id=pid)
            prod_daily = predict_daily(artifact, horizon_days=HORIZON_DAYS)
            monthly = aggregate_monthly_sales(sales_df, product_id=pid)

            # Extend with predicted months using daily sum grouped by month
            from predictor import predict_monthly_from_daily
            pred_monthly = predict_monthly_from_daily(prod_daily)

            cursor.execute("DELETE FROM forecast_results WHERE product_id = %s", (pid,))

            for _, row in pred_monthly.head(FORECAST_MONTHS).iterrows():
                fd = row["date"].date() if hasattr(row["date"], "date") else row["date"]
                cursor.execute(
                    """
                    INSERT INTO forecast_results
                        (product_id, forecast_date, predicted_quantity, confidence_score, model_version)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        predicted_quantity = VALUES(predicted_quantity),
                        confidence_score = VALUES(confidence_score),
                        model_version = VALUES(model_version)
                    """,
                    (pid, fd, float(row["predicted_quantity"]), prod_metrics.get("forecast_accuracy", 75) / 100, model_version),
                )
                cursor.execute(
                    """
                    INSERT INTO forecast
                        (product_id, forecast_date, predicted_quantity, lower_bound, upper_bound, model_used)
                    VALUES (%s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        predicted_quantity = VALUES(predicted_quantity),
                        lower_bound = VALUES(lower_bound),
                        upper_bound = VALUES(upper_bound),
                        model_used = VALUES(model_used)
                    """,
                    (pid, fd, float(row["predicted_quantity"]), float(row["predicted_quantity"]) * 0.85,
                     float(row["predicted_quantity"]) * 1.15, prod_model),
                )
                forecasts_generated += 1

            products_processed += 1
            save_model(artifact, suffix=f"product_{pid}")
        except Exception:
            continue  # Skip products with insufficient history

    # Store segment accuracy for dashboard radar chart
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

        if predict_only and model_exists("aggregate"):
            artifact = load_model("aggregate")
            model_type = artifact["model_type"]
            metrics = artifact.get("metrics", {})
        else:
            artifact, metrics, model_type = train_forecast_model(sales_df)
            save_model(artifact, suffix="aggregate")
            save_training_metadata(artifact, metrics)

        daily_preds = predict_daily(artifact, horizon_days=HORIZON_DAYS)
        chart_data = build_seasonal_chart_data(sales_df, daily_preds)

        products_processed, forecasts_generated, segment_acc = save_results_to_db(
            conn, artifact, daily_preds, sales_df, metrics, model_type
        )

        export_predictions_json(chart_data, metrics)

        return {
            "status": "success",
            "model_type": model_type,
            "model_version": artifact.get("model_version"),
            "products_processed": products_processed,
            "forecasts_generated": forecasts_generated,
            "anomalies_detected": 0,
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
