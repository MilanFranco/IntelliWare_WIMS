<?php
/**
 * One-time migration: add barcode column to products table.
 * Run once via browser: http://localhost/IntelliWare/database/run_barcode_migration.php
 * Or CLI: C:\xampp\php\php.exe database/run_barcode_migration.php
 */
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$check = $conn->query("SHOW COLUMNS FROM products LIKE 'barcode'");
if ($check && $check->num_rows > 0) {
    echo "OK: barcode column already exists.\n";
    exit(0);
}

$sql = "ALTER TABLE `products`
    ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL AFTER `product_name`,
    ADD UNIQUE KEY `uq_products_barcode` (`barcode`)";

if ($conn->query($sql)) {
    echo "OK: barcode column added successfully.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

$conn->close();
