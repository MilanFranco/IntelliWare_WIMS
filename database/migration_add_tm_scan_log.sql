-- ============================================================
-- Migration: Add Teachable Machine scan audit infrastructure
-- Target DB: warehouse_system
-- Run once. Safe to re-run (uses IF NOT EXISTS / IF EXISTS guards).
-- ============================================================

-- 1. Add transaction_source column to transactions table
--    Tracks HOW a transaction was initiated:
--      'ai_scan'  – Teachable Machine / AI camera recognition
--      'manual'   – staff typed/selected product manually
ALTER TABLE `transactions`
  ADD COLUMN IF NOT EXISTS `transaction_source`
    ENUM('ai_scan','manual') DEFAULT 'manual'
    AFTER `remarks`;

-- 2. Create tm_scan_log: one row per AI recognition attempt
--    Records raw model output + how staff resolved it.
CREATE TABLE IF NOT EXISTS `tm_scan_log` (
  `scan_id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`            INT(11)       DEFAULT NULL,
  `product_id`         INT(11)       DEFAULT NULL,
  `category_slug`      VARCHAR(100)  NOT NULL,
  `top_class`          VARCHAR(255)  NOT NULL,
  `top_confidence`     DECIMAL(5,4)  NOT NULL,
  `second_class`       VARCHAR(255)  DEFAULT NULL,
  `second_confidence`  DECIMAL(5,4)  DEFAULT NULL,
  `resolution`         ENUM('auto','manual','rejected') NOT NULL DEFAULT 'auto',
  `scanned_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`scan_id`),
  KEY `idx_tm_user`    (`user_id`),
  KEY `idx_tm_product` (`product_id`),
  KEY `idx_tm_slug`    (`category_slug`),
  KEY `idx_tm_scanned` (`scanned_at`),
  CONSTRAINT `fk_tm_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
