-- Migration: Add price column to products table
-- Run against warehouse_system database.
-- This adds a nullable price field with a default value for existing products.

ALTER TABLE `products`
  ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `expiry_date`;
