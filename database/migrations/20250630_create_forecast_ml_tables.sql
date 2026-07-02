-- ML forecasting tables for IntelliWare
-- Run once: mysql -u root warehouse_system < database/migrations/20250630_create_forecast_ml_tables.sql

CREATE TABLE IF NOT EXISTS forecast_results (
    result_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL DEFAULT 0 COMMENT '0 = system-wide aggregate forecast',
    forecast_date DATE NOT NULL,
    predicted_quantity DECIMAL(12,2) NOT NULL,
    confidence_score DECIMAL(6,4) DEFAULT NULL,
    model_version VARCHAR(20) DEFAULT '1.0.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_forecast_result (product_id, forecast_date),
    KEY idx_forecast_date (forecast_date),
    KEY idx_product_id (product_id)
);

CREATE TABLE IF NOT EXISTS forecast_metrics (
    metric_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    training_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mae DECIMAL(12,4) DEFAULT NULL,
    rmse DECIMAL(12,4) DEFAULT NULL,
    mape DECIMAL(8,4) DEFAULT NULL,
    forecast_accuracy DECIMAL(6,2) DEFAULT NULL COMMENT 'Percentage 0-100',
    error_rate DECIMAL(6,2) DEFAULT NULL COMMENT 'Percentage 0-100',
    model_type VARCHAR(50) DEFAULT NULL,
    model_version VARCHAR(20) DEFAULT '1.0.0',
    products_processed INT DEFAULT 0,
    forecasts_generated INT DEFAULT 0,
    segment_accuracy_json TEXT DEFAULT NULL COMMENT 'JSON radar chart data by movement class',
    KEY idx_training_date (training_date)
);

-- Track when auto-retrain should trigger (optional)
CREATE TABLE IF NOT EXISTS ml_training_state (
    id INT PRIMARY KEY DEFAULT 1,
    last_training_at TIMESTAMP NULL,
    last_transaction_count INT DEFAULT 0,
    auto_retrain_threshold INT DEFAULT 50 COMMENT 'Retrain after N new transactions',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO ml_training_state (id, auto_retrain_threshold) VALUES (1, 50);
