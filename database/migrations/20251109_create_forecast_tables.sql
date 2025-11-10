CREATE TABLE IF NOT EXISTS forecast (
    forecast_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    forecast_date DATE NOT NULL,
    predicted_quantity DECIMAL(10,2) NOT NULL,
    lower_bound DECIMAL(10,2) NOT NULL,
    upper_bound DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_forecast (product_id, forecast_date),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE IF NOT EXISTS anomalies (
    anomaly_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    detection_date DATE NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    anomaly_score DECIMAL(10,5) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_anomaly (product_id, detection_date),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

CREATE TABLE IF NOT EXISTS forecast_runs (
    run_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    started_at TIMESTAMP NOT NULL,
    finished_at TIMESTAMP NULL,
    status VARCHAR(20) NOT NULL,
    products_processed INT DEFAULT 0,
    forecasts_generated INT DEFAULT 0,
    anomalies_detected INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);