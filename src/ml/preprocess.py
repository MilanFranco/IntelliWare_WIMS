"""
Clean and aggregate transaction data into time-series suitable for forecasting.
"""

import numpy as np
import pandas as pd


def clean_sales(df: pd.DataFrame) -> pd.DataFrame:
    """Remove invalid rows and normalize dates."""
    if df.empty:
        return df

    cleaned = df.copy()
    cleaned = cleaned.dropna(subset=["transaction_date", "quantity"])
    cleaned = cleaned[cleaned["quantity"] > 0]
    cleaned["transaction_date"] = pd.to_datetime(cleaned["transaction_date"])
    cleaned = cleaned.sort_values("transaction_date")
    return cleaned


def aggregate_daily_sales(sales_df: pd.DataFrame, product_id: int = None) -> pd.DataFrame:
    """
    Aggregate sales to daily totals.
    product_id=None aggregates across all products (system-wide demand).
    """
    if sales_df.empty:
        return pd.DataFrame(columns=["date", "quantity"])

    data = sales_df
    if product_id is not None:
        data = sales_df[sales_df["product_id"] == product_id]

    if data.empty:
        return pd.DataFrame(columns=["date", "quantity"])

    daily = (
        data.set_index("transaction_date")["quantity"]
        .resample("D")
        .sum()
        .reset_index()
    )
    daily.columns = ["date", "quantity"]
    daily["date"] = pd.to_datetime(daily["date"])
    return daily


def aggregate_monthly_sales(sales_df: pd.DataFrame, product_id: int = None) -> pd.DataFrame:
    """Aggregate sales to calendar-month totals."""
    daily = aggregate_daily_sales(sales_df, product_id)
    if daily.empty:
        return pd.DataFrame(columns=["date", "quantity", "month", "year"])

    monthly = (
        daily.set_index("date")["quantity"]
        .resample("MS")
        .sum()
        .reset_index()
    )
    monthly.columns = ["date", "quantity"]
    monthly["month"] = monthly["date"].dt.month
    monthly["year"] = monthly["date"].dt.year
    return monthly


def build_feature_frame(daily: pd.DataFrame) -> pd.DataFrame:
    """
    Build supervised-learning features from daily sales.
    Used by Random Forest when Prophet is not suitable.
    """
    if daily.empty or len(daily) < 14:
        return pd.DataFrame()

    frame = daily.copy()
    frame = frame.set_index("date").asfreq("D", fill_value=0).reset_index()

    frame["day_of_week"] = frame["date"].dt.dayofweek
    frame["month"] = frame["date"].dt.month
    frame["day_of_month"] = frame["date"].dt.day
    frame["week_of_year"] = frame["date"].dt.isocalendar().week.astype(int)

    for lag in (1, 7, 14, 28):
        frame[f"lag_{lag}"] = frame["quantity"].shift(lag)

    frame["rolling_mean_7"] = frame["quantity"].rolling(7, min_periods=1).mean()
    frame["rolling_mean_30"] = frame["quantity"].rolling(30, min_periods=1).mean()
    frame["rolling_std_7"] = frame["quantity"].rolling(7, min_periods=1).std().fillna(0)

    frame = frame.dropna()
    return frame


def seasonal_index(monthly: pd.DataFrame) -> pd.DataFrame:
    """
    Compute monthly seasonal index (demand multiplier vs. annual average).
    Index of 1.0 = average month; >1 = peak; <1 = decline.
    """
    if monthly.empty:
        return pd.DataFrame(columns=["month", "seasonal_index", "avg_quantity"])

    avg = monthly["quantity"].mean()
    if avg <= 0:
        return pd.DataFrame(columns=["month", "seasonal_index", "avg_quantity"])

    by_month = monthly.groupby("month")["quantity"].mean().reset_index()
    by_month["seasonal_index"] = (by_month["quantity"] / avg).round(4)
    by_month.rename(columns={"quantity": "avg_quantity"}, inplace=True)
    return by_month


def classify_movement(total_sales: float) -> str:
    """Map lifetime sales volume to movement class (matches PHP thresholds)."""
    if total_sales >= 50:
        return "fast_moving"
    if total_sales >= 10:
        return "medium_moving"
    if total_sales > 0:
        return "slow_moving"
    return "non_moving"


def train_test_split_ts(daily: pd.DataFrame, test_ratio: float = 0.2) -> tuple:
    """Time-ordered holdout split for backtesting."""
    if len(daily) < 10:
        return daily.iloc[:0], daily

    split_idx = max(1, int(len(daily) * (1 - test_ratio)))
    train = daily.iloc[:split_idx].copy()
    test = daily.iloc[split_idx:].copy()
    return train, test
