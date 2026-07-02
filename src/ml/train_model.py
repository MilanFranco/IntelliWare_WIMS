"""
Train lightweight forecasting models on historical demand data.

Model selection:
- Prophet: used when >= 60 days of daily history (captures weekly + yearly seasonality)
- Random Forest Regressor: fallback for shorter or sparse histories
"""

import json
import os
from datetime import datetime

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error

from preprocess import aggregate_daily_sales, build_feature_frame, train_test_split_ts

MODEL_DIR = os.path.join(os.path.dirname(__file__), "model")
FEATURE_COLS = [
    "day_of_week",
    "month",
    "day_of_month",
    "week_of_year",
    "lag_1",
    "lag_7",
    "lag_14",
    "lag_28",
    "rolling_mean_7",
    "rolling_mean_30",
    "rolling_std_7",
]

PROPHET_MIN_DAYS = 60
MODEL_VERSION = "1.0.0"


def _ensure_model_dir():
    os.makedirs(MODEL_DIR, exist_ok=True)


def select_model_type(daily: pd.DataFrame) -> str:
    """Pick Prophet or random_forest based on data volume."""
    if len(daily) >= PROPHET_MIN_DAYS:
        try:
            from prophet import Prophet  # noqa: F401
            return "prophet"
        except ImportError:
            pass
    return "random_forest"


def compute_metrics(y_true: np.ndarray, y_pred: np.ndarray) -> dict:
    """Calculate MAE, RMSE, MAPE and derived accuracy percentage."""
    y_true = np.asarray(y_true, dtype=float)
    y_pred = np.asarray(y_pred, dtype=float)
    y_pred = np.maximum(y_pred, 0)

    mae = float(mean_absolute_error(y_true, y_pred))
    rmse = float(np.sqrt(mean_squared_error(y_true, y_pred)))

    # MAPE — skip zero actuals to avoid division by zero
    mask = y_true > 0
    if mask.sum() > 0:
        mape = float(np.mean(np.abs((y_true[mask] - y_pred[mask]) / y_true[mask])) * 100)
    else:
        mape = 100.0

    # Accuracy: 100% minus MAPE, clamped to [0, 100]
    forecast_accuracy = round(max(0.0, min(100.0, 100.0 - mape)), 2)
    error_rate = round(min(100.0, mape), 2)

    return {
        "mae": round(mae, 4),
        "rmse": round(rmse, 4),
        "mape": round(mape, 4),
        "forecast_accuracy": forecast_accuracy,
        "error_rate": error_rate,
    }


def train_random_forest(daily: pd.DataFrame) -> tuple:
    """Train Random Forest on engineered daily features."""
    features = build_feature_frame(daily)
    if features.empty or len(features) < 14:
        raise ValueError("Insufficient data for Random Forest training (need >= 14 days)")

    train_df, test_df = train_test_split_ts(features, test_ratio=0.2)

    X_train = train_df[FEATURE_COLS]
    y_train = train_df["quantity"]
    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=8,
        min_samples_leaf=2,
        random_state=42,
        n_jobs=-1,
    )
    model.fit(X_train, y_train)

    metrics = {"mae": 0, "rmse": 0, "mape": 100, "forecast_accuracy": 0, "error_rate": 100}
    if not test_df.empty:
        preds = np.maximum(model.predict(test_df[FEATURE_COLS]), 0)
        metrics = compute_metrics(test_df["quantity"].values, preds)

    artifact = {
        "model_type": "random_forest",
        "model": model,
        "feature_cols": FEATURE_COLS,
        "last_daily": daily.tail(60).to_dict(orient="records"),
        "trained_at": datetime.now().isoformat(),
        "model_version": MODEL_VERSION,
        "metrics": metrics,
    }
    return artifact, metrics


def train_prophet(daily: pd.DataFrame) -> tuple:
    """Train Facebook Prophet on daily aggregate demand."""
    from prophet import Prophet

    prophet_df = daily[["date", "quantity"]].rename(columns={"date": "ds", "quantity": "y"})
    prophet_df["ds"] = pd.to_datetime(prophet_df["ds"])

    train_df, test_df = train_test_split_ts(prophet_df, test_ratio=0.2)

    model = Prophet(
        yearly_seasonality=True,
        weekly_seasonality=True,
        daily_seasonality=False,
        seasonality_mode="multiplicative",
    )
    model.fit(train_df)

    metrics = {"mae": 0, "rmse": 0, "mape": 100, "forecast_accuracy": 0, "error_rate": 100}
    if not test_df.empty:
        forecast = model.predict(test_df[["ds"]])
        preds = np.maximum(forecast["yhat"].values, 0)
        metrics = compute_metrics(test_df["y"].values, preds)

    artifact = {
        "model_type": "prophet",
        "model": model,
        "last_daily": daily.tail(60).to_dict(orient="records"),
        "trained_at": datetime.now().isoformat(),
        "model_version": MODEL_VERSION,
        "metrics": metrics,
    }
    return artifact, metrics


def train_forecast_model(sales_df: pd.DataFrame, product_id: int = None) -> tuple:
    """
    Train the best-fit model for given sales data.
    Returns (artifact_dict, metrics_dict, model_type).
    """
    daily = aggregate_daily_sales(sales_df, product_id)
    if daily.empty or len(daily) < 7:
        raise ValueError("Need at least 7 days of sales history to train")

    model_type = select_model_type(daily)

    if model_type == "prophet":
        try:
            artifact, metrics = train_prophet(daily)
            artifact["product_id"] = product_id
            return artifact, metrics, model_type
        except Exception:
            model_type = "random_forest"

    artifact, metrics = train_random_forest(daily)
    artifact["product_id"] = product_id
    return artifact, metrics, model_type


def save_model(artifact: dict, suffix: str = "aggregate") -> str:
    """Persist trained model to model/ directory."""
    _ensure_model_dir()
    path = os.path.join(MODEL_DIR, f"forecast_{suffix}.joblib")
    joblib.dump(artifact, path)
    return path


def load_model(suffix: str = "aggregate") -> dict:
    """Load a previously saved model artifact."""
    path = os.path.join(MODEL_DIR, f"forecast_{suffix}.joblib")
    if not os.path.exists(path):
        raise FileNotFoundError(f"No trained model at {path}")
    return joblib.load(path)


def model_exists(suffix: str = "aggregate") -> bool:
    return os.path.exists(os.path.join(MODEL_DIR, f"forecast_{suffix}.joblib"))


def save_training_metadata(artifact: dict, metrics: dict, path: str = None):
    """Write training summary JSON alongside the model."""
    _ensure_model_dir()
    meta_path = path or os.path.join(MODEL_DIR, "last_training.json")
    payload = {
        "model_type": artifact.get("model_type"),
        "model_version": artifact.get("model_version"),
        "trained_at": artifact.get("trained_at"),
        "metrics": metrics,
    }
    with open(meta_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, indent=2)
