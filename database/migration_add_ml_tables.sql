-- Migration script to add ML-related tables to warehouse_system database
-- Run this script if you already have the database set up

-- Add missing columns to forecast table
ALTER TABLE `forecast` 
ADD COLUMN `predicted_quantity` decimal(10,2) DEFAULT NULL AFTER `predicted_qty`,
ADD COLUMN `lower_bound` decimal(10,2) DEFAULT NULL AFTER `predicted_quantity`,
ADD COLUMN `upper_bound` decimal(10,2) DEFAULT NULL AFTER `lower_bound`,
ADD KEY `forecast_date` (`forecast_date`);

-- Create product_recommendations table
CREATE TABLE IF NOT EXISTS `product_recommendations` (
  `recommendation_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `avg_monthly_demand` decimal(10,2) DEFAULT NULL,
  `demand_std` decimal(10,2) DEFAULT NULL,
  `seasonal_strength` decimal(10,2) DEFAULT NULL,
  `recommended_stock` int(11) DEFAULT NULL,
  `restock_recommended` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`recommendation_id`),
  UNIQUE KEY `product_month` (`product_id`,`month`),
  KEY `product_id` (`product_id`),
  KEY `month` (`month`),
  CONSTRAINT `product_recommendations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create forecast_runs table
CREATE TABLE IF NOT EXISTS `forecast_runs` (
  `run_id` int(11) NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `status` enum('running','completed','error') DEFAULT 'running',
  `products_processed` int(11) DEFAULT NULL,
  `forecasts_generated` int(11) DEFAULT NULL,
  `anomalies_detected` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`run_id`),
  KEY `started_at` (`started_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create anomalies table
CREATE TABLE IF NOT EXISTS `anomalies` (
  `anomaly_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `detection_date` date NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `anomaly_score` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`anomaly_id`),
  KEY `product_id` (`product_id`),
  KEY `detection_date` (`detection_date`),
  KEY `anomaly_score` (`anomaly_score`),
  CONSTRAINT `anomalies_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

