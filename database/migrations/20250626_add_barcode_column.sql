-- Migration: Add barcode support to products table
-- Run against warehouse_system database
-- Existing rows receive NULL barcode; new barcodes must be unique.

ALTER TABLE `products`
  ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL AFTER `product_name`,
  ADD UNIQUE KEY `uq_products_barcode` (`barcode`);
