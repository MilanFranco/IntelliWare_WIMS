<?php
/**
 * One-time migration: add price column to products table.
 * Run once via browser: http://localhost/IntelliWare/database/run_price_migration.php
 * Or CLI: C:\xampp\php\php.exe database/run_price_migration.php
 */
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$check = $conn->query("SHOW COLUMNS FROM products LIKE 'price'");
if ($check && $check->num_rows > 0) {
    echo "OK: price column already exists.\n";
    exit(0);
}

$sql = "ALTER TABLE `products`
    ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `expiry_date`";

if ($conn->query($sql)) {
    echo "OK: price column added successfully.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

$conn->close();
