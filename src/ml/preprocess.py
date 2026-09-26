"""
Clean and aggregate transaction data into time-series suitable for dynamic ML forecasting.

Includes:
 - Philippine seasonal market intelligence (18 public holidays + ber months + payday cycles)
 - Intermittent demand decomposition (TSB Croston variant)
 - Rich feature engineering: stock levels, reorder proximity, payday signals, holiday proximity
 - Non-collapsing rolling statistics with EWM and trend slope
"""

import numpy as np
import pandas as pd

# ─────────────────────────────────────────────────────────────────────────────
# Philippine Macro Monthly Demand Multipliers
# ─────────────────────────────────────────────────────────────────────────────
PHILIPPINE_MONTHLY_MULTIPLIERS = {
    1:  1.08,  # Jan: Post-holiday staple restocking & budget reset
    2:  1.18,  # Feb: Valentine's confectionery, snacks & midterm baon
    3:  1.28,  # Mar: Early summer heatwave & Lenten fish/canned season
    4:  1.38,  # Apr: Peak summer heat, Holy Week & Halo-halo dairy
    5:  1.42,  # May: Flores de Mayo, town fiestas, viand catering
    6:  1.38,  # Jun: Balik-Eskwela & Habagat monsoon onset
    7:  1.32,  # Jul: Peak monsoon typhoons (instant noodles, canned goods)
    8:  1.28,  # Aug: Ghost month caution & emergency storm stockpiling
    9:  1.38,  # Sep: Ber Months kickoff & early Christmas countdown
    10: 1.48,  # Oct: Oktoberfest, Halloween & pre-Undas
    11: 1.75,  # Nov: Undas reunions, 13th Month Pay & holiday pantry rush
    12: 2.00,  # Dec: Peak Christmas, Noche Buena, hampers & Year-End feasts
}

# Category-specific multipliers (overrides macro by 60/40 blend)
PHILIPPINE_CATEGORY_MULTIPLIERS = {
    "Dry Goods":                   {1: 1.35, 3: 1.20, 5: 1.50, 6: 1.30, 7: 1.40, 8: 1.60, 9: 1.55, 11: 1.75, 12: 1.90},
    "Canned Goods":                {1: 1.40, 3: 1.35, 4: 1.50, 7: 1.65, 8: 1.55, 9: 1.45, 11: 1.40, 12: 1.60},
    "Beverages":                   {1: 1.20, 2: 1.35, 3: 1.65, 4: 1.85, 5: 1.40, 6: 1.40, 7: 1.45, 10: 1.35, 12: 1.80},
    "Dairy Products":              {1: 1.15, 3: 1.25, 4: 1.70, 9: 1.50, 11: 1.85, 12: 2.10},
    "Snacks and Biscuits":         {2: 1.50, 4: 1.35, 6: 1.65, 8: 1.35, 10: 1.60, 11: 1.50, 12: 1.85},
    "Frozen Foods":                {2: 1.20, 3: 1.30, 5: 1.45, 9: 1.35, 11: 1.60, 12: 1.90},
    "Bakery Products":             {1: 1.20, 2: 1.25, 6: 1.50, 8: 1.40, 10: 1.35, 12: 1.65},
    "Condiments and Sauces":       {2: 1.10, 5: 1.60, 9: 1.45, 11: 1.65, 12: 1.80},
    "Personal Care":               {1: 1.10, 2: 1.30, 4: 1.40, 7: 1.25, 10: 1.20, 12: 1.40},
    "Household Items":             {3: 1.15, 6: 1.30, 7: 1.35, 8: 1.25, 10: 1.45, 12: 1.50},
    "Stationery and Office Supplies": {5: 1.40, 6: 2.20, 7: 1.20, 10: 1.10, 12: 1.20},
    "Cleaning Supplies":           {3: 1.20, 5: 1.30, 6: 1.45, 10: 1.25, 11: 1.35, 12: 1.40},
    "Health and Wellness":         {1: 1.15, 2: 1.20, 7: 1.35, 8: 1.45, 10: 1.30, 12: 1.25},
}

# ─────────────────────────────────────────────────────────────────────────────
# Philippine Public Holidays (MM-DD) — 18 regular + special non-working days
# ─────────────────────────────────────────────────────────────────────────────
PH_FIXED_HOLIDAYS = {
    "01-01",  # New Year's Day
    "02-25",  # EDSA People Power Anniversary
    "04-09",  # Araw ng Kagitingan (Bataan/Corregidor Day)
    "05-01",  # Labor Day
    "06-12",  # Independence Day
    "08-21",  # Ninoy Aquino Day
    "08-26",  # National Heroes Day (last Mon Aug → approximate as 08-26)
    "11-01",  # All Saints' Day
    "11-02",  # All Souls' Day
    "11-30",  # Bonifacio Day
    "12-08",  # Feast of the Immaculate Conception
    "12-24",  # Christmas Eve (special non-working)
    "12-25",  # Christmas Day
    "12-30",  # Rizal Day
    "12-31",  # New Year's Eve (special non-working)
}

# Holy Week approximate dates (floats around April)
PH_HOLY_WEEK_MONTHS_DAYS = {(3, 24), (3, 25), (3, 26), (3, 27), (3, 28),
                              (4, 1), (4, 2), (4, 3), (4, 4), (4, 5)}

# Payday proximity signal: days 13-16 and 28-31 each month are payday windows
PAYDAY_EARLY_RANGE = set(range(13, 17))   # 15th payday window
PAYDAY_LATE_RANGE  = set(range(27, 32))   # 30th/EOM payday window


def _is_ph_holiday(date: pd.Timestamp) -> int:
    """Return 1 if the date is a Philippine public holiday, else 0."""
    mmdd = date.strftime("%m-%d")
    if mmdd in PH_FIXED_HOLIDAYS:
        return 1
    if (date.month, date.day) in PH_HOLY_WEEK_MONTHS_DAYS:
        return 1
    return 0


def _days_to_nearest_holiday(date: pd.Timestamp, horizon: int = 7) -> float:
    """
    Return the fraction of days (0–1) to the nearest PH holiday within `horizon` days.
    1.0 = holiday today, 0.0 = no holiday within horizon.
    """
    for delta in range(0, horizon + 1):
        candidate = date + pd.Timedelta(days=delta)
        if _is_ph_holiday(candidate):
            return round(1.0 - delta / horizon, 4)
    return 0.0


def _payday_proximity(day_of_month: int) -> float:
    """
    Return a float in [0, 1] reflecting proximity to Philippine payday windows.
    Peak = 1.0 on days 15 and 30/31; tapers off ±3 days.
    """
    dist_15 = abs(day_of_month - 15)
    dist_30 = abs(day_of_month - 30)
    dist = min(dist_15, dist_30)
    return round(max(0.0, 1.0 - dist / 4.0), 4)


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
        extra_dates = pd.date_range(
            start=daily["date"].max() + pd.Timedelta(days=1), end=today, freq="D"
        )
        extra_df = pd.DataFrame({"date": extra_dates, "quantity": 0.0})
        daily = pd.concat([daily, extra_df], ignore_index=True)

    return daily


def aggregate_daily_sales_with_stock(
    sales_df: pd.DataFrame,
    product_df: pd.DataFrame = None,
    product_id: int = None,
) -> pd.DataFrame:
    """
    Aggregate sales to daily totals, enriched with stock level & reorder point
    from product_df if provided.
    """
    daily = aggregate_daily_sales(sales_df, product_id)
    if daily.empty or product_df is None or product_df.empty:
        daily["current_stock"] = 0.0
        daily["reorder_point"] = 0.0
        return daily

    if product_id is not None:
        row = product_df[product_df["product_id"] == product_id]
    else:
        row = product_df  # system-wide: use median stock

    stock = float(row["current_stock"].median()) if not row.empty else 0.0
    reorder = float(row["reorder_point"].median()) if "reorder_point" in row.columns and not row.empty else 0.0

    daily["current_stock"] = stock
    daily["reorder_point"] = reorder
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
        # 60% category-specific, 40% macro Philippine calendar
        return round(0.60 * cat_mult + 0.40 * base_mult, 4)
    return base_mult


def build_feature_frame(daily: pd.DataFrame, category: str = None) -> pd.DataFrame:
    """
    Build supervised-learning features from daily sales.

    Feature groups:
      1. Calendar & Fourier cyclical signals
      2. Philippine seasonal multipliers (macro & category-specific)
      3. Philippine holiday proximity (7-day lookahead)
      4. Philippine payday proximity (15th & 30th cycle)
      5. Ber months indicator (Sep–Dec Christmas countdown)
      6. Active demand tracking (TSB variant) — prevents zero-collapse
      7. Day-of-week shipment propensity
      8. Rolling statistics on raw and active demand
      9. EWM & trend slope
      10. Stock level & reorder proximity (if available)
    """
    if daily.empty or len(daily) < 7:
        return pd.DataFrame()

    frame = daily.copy()
    frame = frame.set_index("date").asfreq("D", fill_value=0).reset_index()
    n = len(frame)

    # ── 1. Calendar features ────────────────────────────────────────────────
    frame["day_of_week"]   = frame["date"].dt.dayofweek
    frame["month"]         = frame["date"].dt.month
    frame["day_of_month"]  = frame["date"].dt.day
    frame["week_of_year"]  = frame["date"].dt.isocalendar().week.astype(int)
    frame["quarter"]       = frame["date"].dt.quarter
    frame["is_weekend"]    = (frame["day_of_week"] >= 5).astype(int)
    frame["is_month_end"]  = frame["date"].dt.is_month_end.astype(int)
    frame["is_month_start"] = frame["date"].dt.is_month_start.astype(int)

    # ── 2. Philippine seasonal multiplier ───────────────────────────────────
    frame["ph_seasonal_mult"] = frame["month"].apply(
        lambda m: get_philippine_multiplier(int(m), category)
    )

    # ── 3. Philippine holiday proximity ─────────────────────────────────────
    frame["ph_holiday_proximity"] = frame["date"].apply(
        lambda d: _days_to_nearest_holiday(d, horizon=7)
    )
    frame["is_ph_holiday"] = frame["date"].apply(_is_ph_holiday)

    # ── 4. Payday proximity ──────────────────────────────────────────────────
    frame["payday_proximity"] = frame["day_of_month"].apply(_payday_proximity)

    # ── 5. Ber months (Sep–Dec) ─────────────────────────────────────────────
    frame["is_ber_month"] = (frame["month"] >= 9).astype(int)

    # ── 6. Fourier cyclical seasonality ────────────────────────────────────
    frame["sin_dow"]   = np.sin(2 * np.pi * frame["day_of_week"] / 7.0)
    frame["cos_dow"]   = np.cos(2 * np.pi * frame["day_of_week"] / 7.0)
    frame["sin_month"] = np.sin(2 * np.pi * frame["month"] / 12.0)
    frame["cos_month"] = np.cos(2 * np.pi * frame["month"] / 12.0)
    # Biweekly payroll cycle
    frame["sin_biweek"] = np.sin(2 * np.pi * frame["day_of_month"] / 15.0)
    frame["cos_biweek"] = np.cos(2 * np.pi * frame["day_of_month"] / 15.0)

    # ── 7. Warehouse Hiatus Detection & Effective Demand Smoothing ────────
    # Identifies unrecorded hiatuses (e.g. system downtime or gap months >= 14 days)
    is_zero = (frame["quantity"] == 0).astype(int)
    zero_groups = (is_zero != is_zero.shift()).cumsum()
    zero_counts = is_zero.groupby(zero_groups).transform("sum")
    frame["is_hiatus_day"] = ((is_zero == 1) & (zero_counts >= 14)).astype(int)

    eff_qty = frame["quantity"].copy()
    if frame["is_hiatus_day"].sum() > 0:
        eff_qty.loc[frame["is_hiatus_day"] == 1] = np.nan
        eff_qty = eff_qty.interpolate(method="linear").bfill().ffill()

    # ── 8. TSB Intermittent Demand (Teunter-Syntetos-Babai smoothing) ───────
    active_mask  = frame["quantity"] > 0
    active_sales = frame.loc[active_mask, "quantity"]
    median_batch = float(active_sales.median()) if len(active_sales) > 0 else 50.0
    overall_mean = float(eff_qty.mean()) if not eff_qty.empty else 1.0
    if overall_mean <= 0:
        overall_mean = 1.0

    alpha = 0.20   # order size smoothing
    beta  = 0.15   # demand occurrence probability smoothing

    z = np.zeros(n)
    d = np.zeros(n)
    curr_z = median_batch
    curr_d = max(0.15, len(active_sales) / max(n, 1))

    for i in range(n):
        q = frame.loc[i, "quantity"]
        if q > 0:
            curr_z = alpha * q + (1 - alpha) * curr_z
            curr_d = beta * 1.0 + (1 - beta) * curr_d
        elif frame.loc[i, "is_hiatus_day"] == 0:
            curr_d = (1 - beta) * curr_d
        z[i] = max(curr_z, median_batch * 0.5)
        d[i] = max(curr_d, 0.10)

    frame["active_demand_level"] = z

    # ── 9. Day-of-week shipment propensity ──────────────────────────────────
    active_frame = frame[frame["is_hiatus_day"] == 0]
    if not active_frame.empty:
        dow_counts = active_frame.groupby("day_of_week")["quantity"].agg(
            total_days="count",
            sale_days=lambda s: (s > 0).sum()
        ).reset_index()
        dow_counts["dow_propensity"] = (
            dow_counts["sale_days"] / np.maximum(dow_counts["total_days"], 1)
        ).clip(0.15, 1.0)
        dow_map = dict(zip(dow_counts["day_of_week"], dow_counts["dow_propensity"]))
    else:
        dow_map = {}
    frame["dow_propensity"] = frame["day_of_week"].map(dow_map).fillna(0.35)

    # Expected daily velocity = active_level × occurrence_prob × dow_propensity × season
    frame["expected_daily_velocity"] = (
        z * d * frame["dow_propensity"] * frame["ph_seasonal_mult"]
    )

    # ── 10. Standard & Effective Lags ────────────────────────────────────────
    for lag in (1, 2, 3, 7, 14, 21, 28, 60):
        if lag < n:
            frame[f"lag_{lag}"] = eff_qty.shift(lag).bfill().fillna(0)

    # Active-demand lags (preserve order-size memory across zero lulls)
    for lag in (1, 2, 3):
        frame[f"active_lag_{lag}"] = pd.Series(z).shift(lag).fillna(median_batch)

    # ── 11. Rolling statistics on effective demand ───────────────────────────
    for window in (3, 7, 14, 30):
        if window <= n:
            frame[f"rolling_mean_{window}"]   = eff_qty.rolling(window, min_periods=1).mean()
            frame[f"rolling_max_{window}"]    = eff_qty.rolling(window, min_periods=1).max()
    frame["rolling_std_7"]    = eff_qty.rolling(7,  min_periods=1).std().fillna(0)
    frame["rolling_std_14"]   = eff_qty.rolling(14, min_periods=1).std().fillna(0)
    frame["rolling_median_7"] = eff_qty.rolling(7,  min_periods=1).median().fillna(0)
    frame["rolling_median_14"]= eff_qty.rolling(14, min_periods=1).median().fillna(0)

    # ── 12. EWM & trend slope ────────────────────────────────────────────────
    frame["ewm_7"]  = eff_qty.ewm(span=7,  adjust=False).mean()
    frame["ewm_14"] = eff_qty.ewm(span=14, adjust=False).mean()

    def _slope(series):
        y = series.values
        if len(y) < 2:
            return 0.0
        x = np.arange(len(y), dtype=float)
        x -= x.mean()
        denom = (x * x).sum()
        return float((x * (y - y.mean())).sum() / denom) if denom != 0 else 0.0

    frame["trend_14"] = (
        eff_qty
        .rolling(14, min_periods=2)
        .apply(_slope, raw=False)
        .fillna(0)
    )

    # ── 13. Stock level & reorder proximity ─────────────────────────────────
    if "current_stock" in frame.columns:
        reorder_pt = float(frame["reorder_point"].iloc[0]) if "reorder_point" in frame.columns else 0.0
        stock_vals = frame["current_stock"].fillna(0).values
        max_stock = float(np.percentile(stock_vals, 95)) if stock_vals.max() > 0 else 1.0
        frame["stock_level_norm"]    = np.clip(stock_vals / max(max_stock, 1.0), 0, 1)
        frame["below_reorder_point"] = (stock_vals <= reorder_pt).astype(int)
    else:
        frame["stock_level_norm"]    = 0.5
        frame["below_reorder_point"] = 0

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
    """Map lifetime sales volume to movement class."""
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
    test  = daily.iloc[split_idx:].copy()
    return train, test


def walk_forward_cv_score(features: pd.DataFrame, feat_cols: list, model_fn) -> float:
    """
    Walk-forward cross-validation (expanding window, 3 folds).
    Returns mean forecast_accuracy across folds evaluated on active operating periods.
    """
    import warnings
    import numpy as np

    n = len(features)
    if n < 25:
        return None

    fold_size = max(7, n // 4)
    scores = []

    for fold in range(1, 4):
        train_end = n - fold_size * (3 - fold + 1)
        test_end  = train_end + fold_size

        if train_end < 10 or test_end > n:
            continue

        train_df = features.iloc[:train_end]
        test_df  = features.iloc[train_end:test_end]

        if "is_hiatus_day" in train_df.columns:
            train_df = train_df[train_df["is_hiatus_day"] == 0]
            test_df  = test_df[test_df["is_hiatus_day"] == 0]

        if len(train_df) < 8 or len(test_df) < 2 or test_df["quantity"].sum() == 0:
            continue

        X_tr = train_df[feat_cols].values
        y_tr = train_df["quantity"].values
        X_te = test_df[feat_cols].values
        y_te = test_df["quantity"].values

        with warnings.catch_warnings():
            warnings.simplefilter("ignore")
            m = model_fn()
            m.fit(X_tr, np.log1p(y_tr))
            preds = np.maximum(np.expm1(m.predict(X_te)), 0)

        # WAPE-based accuracy (7-day replenishment)
        s7_a  = pd.Series(y_te).rolling(7, min_periods=1).sum().values
        s7_p  = pd.Series(preds).rolling(7, min_periods=1).sum().values
        wape7 = float(np.abs(s7_a - s7_p).sum() / max(float(s7_a.sum()), 1.0) * 100)
        scores.append(max(0.0, 100.0 - wape7))

    return round(float(np.mean(scores)), 2) if scores else None

