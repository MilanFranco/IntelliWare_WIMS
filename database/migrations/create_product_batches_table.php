<?php
/**
 * database/migrations/create_product_batches_table.php
 *
 * Creates product_batches table and syncs existing inventory_batches.
 */
require_once __DIR__ . '/../../config/db.php';

echo "1. Creating product_batches table if not exists...\n";
$conn->query("
    CREATE TABLE IF NOT EXISTS `product_batches` (
        `batch_id` INT(11) NOT NULL AUTO_INCREMENT,
        `product_id` INT(11) NOT NULL,
        `qty` INT(11) NOT NULL DEFAULT 0,
        `expiry_date` DATE DEFAULT NULL,
        `received_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `batch_number` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`batch_id`),
        KEY `idx_pb_product` (`product_id`),
        KEY `idx_pb_expiry` (`expiry_date`),
        KEY `idx_pb_prod_exp` (`product_id`, `expiry_date`),
        CONSTRAINT `fk_pb_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

if ($conn->error) {
    echo "Error creating product_batches: " . $conn->error . "\n";
} else {
    echo "Table product_batches verified.\n";
}

echo "2. Syncing existing batches from inventory_batches into product_batches...\n";
$syncSql = "
    INSERT INTO `product_batches` (`batch_id`, `product_id`, `qty`, `expiry_date`, `received_at`, `batch_number`)
    SELECT `batch_id`, `product_id`, `quantity`, `expiry_date`, `date_received`, `batch_number`
    FROM `inventory_batches`
    ON DUPLICATE KEY UPDATE 
        `qty` = VALUES(`qty`),
        `expiry_date` = VALUES(`expiry_date`),
        `batch_number` = VALUES(`batch_number`);
";
$conn->query($syncSql);
if ($conn->error) {
    echo "Sync error: " . $conn->error . "\n";
} else {
    $affected = $conn->affected_rows;
    echo "Synced batches. Affected rows: {$affected}.\n";
}

echo "3. Harmonizing products.quantity with SUM(batch.qty)...\n";
$conn->query("
    UPDATE `products` p
    SET `quantity` = COALESCE((
        SELECT SUM(pb.qty)
        FROM `product_batches` pb
        WHERE pb.product_id = p.product_id AND pb.qty > 0
    ), 0)
    WHERE p.archived = 0;
");
echo "Products total stock harmonized with batch sums.\n";
