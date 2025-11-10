CREATE TABLE IF NOT EXISTS product_recommendations (
    recommendation_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    month INT NOT NULL,
    avg_monthly_demand DECIMAL(10,2) NOT NULL,
    demand_std DECIMAL(10,2) NOT NULL,
    seasonal_strength DECIMAL(10,2) NOT NULL,
    recommended_stock INT NOT NULL,
    restock_recommended BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_month (product_id, month),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);