-- database/migrations/20260910_create_insights_cache_tables.sql
-- Pre-calculated analytics cache tables and performance indexes for IntelliWare

USE `warehouse_system`;

-- 1. Table for cached KPI summaries, category performance, and supplier metrics
CREATE TABLE IF NOT EXISTS `insight_summary` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `summary_key` VARCHAR(64) NOT NULL,
    `summary_data` LONGTEXT NOT NULL,
    `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_summary_key` (`summary_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Table for cached demand forecasting and period predictions
CREATE TABLE IF NOT EXISTS `insight_predictions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `period_type` ENUM('weekly', 'monthly', 'quarterly', 'semi-annual', 'annual') NOT NULL,
    `period_key` VARCHAR(64) NOT NULL,
    `predictions_data` LONGTEXT NOT NULL,
    `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_period_prediction` (`period_type`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Table for cached movement classification and anomaly detection results
CREATE TABLE IF NOT EXISTS `anomaly_results` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `analysis_type` VARCHAR(64) NOT NULL DEFAULT 'movement_anomalies',
    `anomaly_data` LONGTEXT NOT NULL,
    `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_analysis_type` (`analysis_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. High-performance composite indexes on transactions and products to accelerate query execution
CREATE INDEX IF NOT EXISTS `idx_trans_type_date_prod` ON `transactions` (`transaction_type`, `transaction_date`, `product_id`);
CREATE INDEX IF NOT EXISTS `idx_products_cat_archived` ON `products` (`category`, `archived`);
CREATE INDEX IF NOT EXISTS `idx_products_qty_reorder` ON `products` (`quantity`, `reorder_point`);
