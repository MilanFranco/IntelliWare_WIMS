# IntelliWare ML Forecasting Module

Machine learning demand forecasting for the IntelliWare Inventory Management System.

## Overview

This module trains on real `stock_out` transaction history and produces demand predictions displayed on the **Insights** dashboard:

- **Seasonal Demand Patterns** — historical vs forecasted monthly sales with trend line
- **Forecast Accuracy** — MAE, RMSE, MAPE-derived accuracy %, error rate, segment radar chart
- **Sales Forecast** — daily historical + 30-day ML prediction
- **Demand Heatmap** — per-product forecasted demand (top sellers)

PHP **never trains** models during page loads. Training is triggered manually from **Settings → ML Demand Forecasting** (admin only).

## Architecture

```
transactions (MySQL)
       │
       ▼
 data_loader.py ──► preprocess.py ──► train_model.py ──► model/*.joblib
                                           │
                                           ▼
                                    predictor.py
                                           │
                    ┌──────────────────────┼──────────────────────┐
                    ▼                      ▼                      ▼
           forecast_results        forecast_metrics         forecast (per-product)
                    │
                    ▼
         ForecastDataService.php ──► insights.php (Chart.js)
```

## Module Reference

| File | Purpose |
|------|---------|
| `data_loader.py` | Load transactions + product catalog from MySQL |
| `preprocess.py` | Clean data, aggregate daily/monthly, build ML features |
| `train_model.py` | Train Prophet (≥60 days) or Random Forest, compute metrics |
| `predictor.py` | Generate forecasts, seasonal chart data, segment accuracy |
| `run_pipeline.py` | Main entry point — train, predict, save to DB |
| `config_loader.py` | Read DB credentials from `.env` or `config/db.php` |
| `ForecastDataService.php` | PHP read-only access to saved predictions |
| `MLForecastManager.php` | Execute Python pipeline from PHP |

## Model Selection

| Condition | Model |
|-----------|-------|
| ≥ 60 days of daily sales history | **Prophet** (weekly + yearly seasonality) |
| < 60 days or Prophet unavailable | **Random Forest Regressor** (lag + rolling features) |

Metrics are computed on a 20% holdout set:

- **MAE** — Mean Absolute Error (units)
- **RMSE** — Root Mean Squared Error
- **MAPE** — Mean Absolute Percentage Error (%)
- **Forecast Accuracy** = `max(0, 100 − MAPE)`
- **Error Rate** = MAPE

## Installation

### 1. Database tables

```bash
cd C:\xampp\htdocs\IntelliWare
php setup_ml_tables.php
```

Or run the migration SQL:

```bash
mysql -u root warehouse_system < database/migrations/20250630_create_forecast_ml_tables.sql
```

### 2. Python environment

Requires **Python 3.9+**.

```bash
cd src/ml
python -m venv venv
venv\Scripts\activate        # Windows
pip install -r requirements.txt
```

Prophet on Windows may require:

```bash
pip install prophet
```

### 3. Train the model

**Option A — Admin UI:** Settings → ML Demand Forecasting → **Train Forecast Model**

**Option B — CLI:**

```bash
cd src/ml
python run_pipeline.py
```

**Option C — API (admin session required):**

```
POST /api/ml/train_model.php
```

### 4. View results

Open **Insights** (`insights.php`). Charts load saved predictions automatically.

If no model is trained, charts show:

> No trained forecasting model available. Please train the model.

## Retraining

- **Manual:** Admin Settings panel or `api/ml/train_model.php`
- **Automatic (optional):** When ≥ 50 new transactions exist since last training (`ml_training_state.auto_retrain_threshold`). Call `$manager->autoRetrainIfNeeded()` from a cron job — not on page load.

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/ml/train_model.php` | POST | Train model (admin) |
| `/api/ml/forecast_data.php` | GET | JSON chart data |
| `/api/ml/run_forecast.php` | GET/POST | Legacy forecast run |

## Directory Structure

```
src/ml/
├── data_loader.py
├── preprocess.py
├── train_model.py
├── predictor.py
├── run_pipeline.py
├── config_loader.py
├── ForecastDataService.php
├── MLForecastManager.php
├── requirements.txt
├── FORECASTING.md          ← this file
├── model/                  ← saved .joblib models
└── predictions/            ← JSON audit exports
```

## Requirements

See `requirements.txt`:

- pandas, numpy, scikit-learn
- prophet (optional, auto-selected)
- mysql-connector-python, joblib

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Python executable not found" | Install Python 3.9+ and ensure it's on PATH |
| "No transaction history found" | Record `stock_out` transactions first |
| "Insufficient data" | Need ≥ 7 days of sales for Random Forest, ≥ 60 for Prophet |
| Charts show placeholder message | Run training from Settings |
| Training timeout | Increase `max_execution_time` in php.ini (300s recommended) |
