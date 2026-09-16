"""
Clean and aggregate transaction data into time-series suitable for dynamic ML forecasting.
Includes Philippine seasonal market intelligence, intermittent demand decomposition,
and non-collapsing feature engineering.
"""

import numpy as np
import pandas as pd

# Comprehensive Philippine month-by-month demand multipliers
PHILIPPINE_MONTHLY_MULTIPLIERS = {
    1: 1.05,  # Jan: Post-holiday staple restocking & budget reset
    2: 1.15,  # Feb: Valentine's confectionery, snacks & midterm baon
    3: 1.25,  # Mar: Early summer heatwave & Lenten fish/canned season
    4: 1.35,  # Apr: Peak summer heat, Holy Week & Halo-halo dairy
    5: 1.40,  # May: Flores de Mayo, town fiestas, viand catering
    6: 1.35,  # Jun: Balik-Eskwela & Habagat monsoon onset
    7: 1.30,  # Jul: Peak monsoon typhoons (instant noodles, canned goods)
    8: 1.25,  # Aug: Ghost month caution & emergency storm stockpiling
    9: 1.35,  # Sep: Ber Months kickoff & early Christmas countdown
    10: 1.45, # Oct: Oktoberfest, Halloween trick-or-treat & pre-Undas
    11: 1.70, # Nov: Undas reunions, 13th Month Pay & holiday pantry rush
    12: 1.95, # Dec: Peak Christmas, Noche Buena, hampers & Year-End feasts
}

PHILIPPINE_CATEGORY_MULTIPLIERS = {
    "Dry Goods": {1: 1.35, 3: 1.20, 5: 1.50, 6: 1.30, 7: 1.40, 8: 1.60, 9: 1.55, 11: 1.75, 12: 1.90},
    "Canned Goods": {1: 1.40, 3: 1.35, 4: 1.50, 7: 1.65, 8: 1.55, 9: 1.45, 11: 1.40, 12: 1.60},
    "Beverages": {1: 1.20, 2: 1.35, 3: 1.65, 4: 1.85, 5: 1.40, 6: 1.40, 7: 1.45, 10: 1.35, 12: 1.80},
    "Dairy Products": {1: 1.15, 3: 1.25, 4: 1.70, 9: 1.50, 11: 1.85, 12: 2.10},
    "Snacks and Biscuits": {2: 1.50, 4: 1.35, 6: 1.65, 8: 1.35, 10: 1.60, 11: 1.50, 12: 1.85},
    "Frozen Foods": {2: 1.20, 3: 1.30, 5: 1.45, 9: 1.35, 11: 1.60, 12: 1.90},
    "Bakery Products": {1: 1.20, 2: 1.25, 6: 1.50, 8: 1.40, 10: 1.35, 12: 1.65},
    "Condiments and Sauces": {2: 1.10, 5: 1.60, 9: 1.45, 11: 1.65, 12: 1.80},
    "Personal Care": {1: 1.10, 2: 1.30, 4: 1.40, 7: 1.25, 10: 1.20, 12: 1.40},
    "Household Items": {3: 1.15, 6: 1.30, 7: 1.35, 8: 1.25, 10: 1.45, 12: 1.50},
    "Stationery and Office Supplies": {5: 1.40, 6: 2.20, 7: 1.20, 10: 1.10, 12: 1.20},
}


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
    Aggregate sales to daily totals, padded up to today.
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

    # Pad to today
    today = pd.to_datetime(pd.Timestamp.now().normalize())
    if daily["date"].max() < today:
        extra_dates = pd.date_range(start=daily["date"].max() + pd.Timedelta(days=1), end=today, freq="D")
        extra_df = pd.DataFrame({"date": extra_dates, "quantity": 0.0})
        daily = pd.concat([daily, extra_df], ignore_index=True)

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


def get_philippine_multiplier(month: int, category: str = None) -> float:
    """Get the Philippine seasonal demand lift factor for a given month and category."""
    base_mult = PHILIPPINE_MONTHLY_MULTIPLIERS.get(month, 1.0)
    if category and category in PHILIPPINE_CATEGORY_MULTIPLIERS:
        cat_mult = PHILIPPINE_CATEGORY_MULTIPLIERS[category].get(month, base_mult)
        # Weighted blend (60% category-specific, 40% macro Philippine calendar)
        return round(0.60 * cat_mult + 0.40 * base_mult, 4)
    return base_mult


def build_feature_frame(daily: pd.DataFrame, category: str = None) -> pd.DataFrame:
    """
    Build supervised-learning features from daily sales.
    Features:
      - Calendar & Fourier cyclical signals
      - Philippine seasonal multipliers (macro & category-specific)
      - Active demand tracking (Croston/TSB style) to prevent zero-collapse
      - Rolling statistics on both raw and active demand
    """
    if daily.empty or len(daily) < 7:
        return pd.DataFrame()

    frame = daily.copy()
    frame = frame.set_index("date").asfreq("D", fill_value=0).reset_index()
    n = len(frame)

    # --- Calendar features ---
    frame["day_of_week"] = frame["date"].dt.dayofweek
    frame["month"] = frame["date"].dt.month
    frame["day_of_month"] = frame["date"].dt.day
    frame["week_of_year"] = frame["date"].dt.isocalendar().week.astype(int)
    frame["quarter"] = frame["date"].dt.quarter
    frame["is_weekend"] = (frame["day_of_week"] >= 5).astype(int)
    frame["is_month_end"] = frame["date"].dt.is_month_end.astype(int)
    frame["is_month_start"] = frame["date"].dt.is_month_start.astype(int)

    # --- Philippine Seasonal Lift Multiplier ---
    frame["ph_seasonal_mult"] = frame["month"].apply(lambda m: get_philippine_multiplier(int(m), category))

    # --- Fourier Cyclical Seasonality ---
    frame["sin_dow"] = np.sin(2 * np.pi * frame["day_of_week"] / 7.0)
    frame["cos_dow"] = np.cos(2 * np.pi * frame["day_of_week"] / 7.0)
    frame["sin_month"] = np.sin(2 * np.pi * frame["month"] / 12.0)
    frame["cos_month"] = np.cos(2 * np.pi * frame["month"] / 12.0)

    # --- Active Demand Rate (Intermittent Demand Croston/TSB baseline) ---
    active_mask = frame["quantity"] > 0
    active_demand = frame["quantity"].where(active_mask, np.nan)
    overall_mean = frame["quantity"].mean()
    if overall_mean <= 0:
        overall_mean = 1.0

    frame["active_demand_level"] = active_demand.ffill().bfill().fillna(overall_mean)

    # Historical day-of-week shipment propensity: P(sale | DOW)
    dow_counts = frame.groupby("day_of_week")["quantity"].agg(
        total_days="count",
        sale_days=lambda s: (s > 0).sum()
    ).reset_index()
    dow_counts["dow_propensity"] = (dow_counts["sale_days"] / np.maximum(dow_counts["total_days"], 1)).clip(0.1, 1.0)
    dow_map = dict(zip(dow_counts["day_of_week"], dow_counts["dow_propensity"]))
    frame["dow_propensity"] = frame["day_of_week"].map(dow_map).fillna(0.35)

    # Expected daily velocity baseline = active level * day-of-week propensity * season multiplier
    frame["expected_daily_velocity"] = frame["active_demand_level"] * frame["dow_propensity"] * frame["ph_seasonal_mult"]

    # --- Standard Lags ---
    for lag in (1, 2, 3, 7, 14, 21, 28):
        if lag < n:
            frame[f"lag_{lag}"] = frame["quantity"].shift(lag).fillna(0)

    # --- Active Lags (magnitude of the last N shipments) ---
    for lag in (1, 2, 3):
        frame[f"active_lag_{lag}"] = frame["active_demand_level"].shift(lag).ffill().bfill().fillna(overall_mean)

    # --- Rolling Statistics ---
    for window in (3, 7, 14, 30):
        if window <= n:
            frame[f"rolling_mean_{window}"] = frame["quantity"].rolling(window, min_periods=1).mean()
            frame[f"rolling_max_{window}"] = frame["quantity"].rolling(window, min_periods=1).max()
    frame["rolling_std_7"] = frame["quantity"].rolling(7, min_periods=1).std().fillna(0)
    frame["rolling_std_14"] = frame["quantity"].rolling(14, min_periods=1).std().fillna(0)
    frame["rolling_median_7"] = frame["quantity"].rolling(7, min_periods=1).median().fillna(0)
    frame["rolling_median_14"] = frame["quantity"].rolling(14, min_periods=1).median().fillna(0)

    # --- Exponential Weighted Mean ---
    frame["ewm_7"] = frame["quantity"].ewm(span=7, adjust=False).mean()
    frame["ewm_14"] = frame["quantity"].ewm(span=14, adjust=False).mean()

    # --- Trend slope over 14-day window ---
    def _slope(series):
        y = series.values
        if len(y) < 2:
            return 0.0
        x = np.arange(len(y), dtype=float)
        x -= x.mean()
        denom = (x * x).sum()
        return float((x * (y - y.mean())).sum() / denom) if denom != 0 else 0.0

    frame["trend_14"] = (
        frame["quantity"]
        .rolling(14, min_periods=2)
        .apply(_slope, raw=False)
        .fillna(0)
    )

    frame = frame.fillna(0)
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
