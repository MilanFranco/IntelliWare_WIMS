<?php
/**
 * Migration: add shelf_life column to products, create inventory_batches table,
 * and migrate existing stock to default batches.
 * Run once via browser: http://localhost/IntelliWare/database/run_expiry_migration.php
 * Or CLI: php database/run_expiry_migration.php
 */
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// 1. Add shelf_life column to products
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'shelf_life'");
if (!$check || $check->num_rows === 0) {
    $sql = "ALTER TABLE `products` ADD COLUMN `shelf_life` INT DEFAULT NULL AFTER `price`";
    if ($conn->query($sql)) {
        echo "OK: shelf_life column added to products.\n";
    } else {
        echo "ERROR adding shelf_life: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "OK: shelf_life column already exists.\n";
}

// 2. Create inventory_batches table
$createTableSql = "CREATE TABLE IF NOT EXISTS `inventory_batches` (
    `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `transaction_id` INT DEFAULT NULL,
    `batch_number` VARCHAR(50) DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `initial_quantity` INT NOT NULL DEFAULT 0,
    `date_received` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `manufacturing_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($createTableSql)) {
    echo "OK: inventory_batches table created or already exists.\n";
} else {
    echo "ERROR creating inventory_batches table: " . $conn->error . "\n";
    exit(1);
}

// 3. Migrate existing product stock into default batches
$migratedCount = 0;
// Fetch products that have quantity > 0 but no batches
$query = "SELECT product_id, quantity, expiry_date, batch_number FROM products WHERE quantity > 0 AND product_id NOT IN (SELECT DISTINCT product_id FROM inventory_batches)";
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['product_id'];
        $qty = (int)$row['quantity'];
        $expiry = $row['expiry_date'] ? "'{$row['expiry_date']}'" : "NULL";
        $batchNo = $row['batch_number'] ? "'" . $conn->real_escape_string($row['batch_number']) . "'" : "NULL";

        $insertBatchSql = "INSERT INTO `inventory_batches` (product_id, quantity, initial_quantity, expiry_date, batch_number)
                           VALUES ({$pid}, {$qty}, {$qty}, {$expiry}, {$batchNo})";
        if ($conn->query($insertBatchSql)) {
            $migratedCount++;
        } else {
            echo "ERROR migrating product {$pid}: " . $conn->error . "\n";
        }
    }
    echo "OK: Migrated {$migratedCount} product stock values into default batches.\n";
} else {
    echo "ERROR querying products for migration: " . $conn->error . "\n";
}

$conn->close();
echo "Migration finished successfully.\n";
