<?php
/**
 * database/migrations/setup_batch_triggers.php
 *
 * Sets up triggers on product_batches to enforce that products.quantity is strictly
 * derived from SUM(product_batches.qty) and can never drift.
 */
require_once __DIR__ . '/../../config/db.php';

echo "1. Checking/creating triggers on product_batches...\n";

// Drop existing if any, then recreate
$conn->query("DROP TRIGGER IF EXISTS trg_pb_after_insert");
$conn->query("
    CREATE TRIGGER trg_pb_after_insert AFTER INSERT ON product_batches
    FOR EACH ROW
    BEGIN
        UPDATE products 
        SET quantity = COALESCE((SELECT SUM(qty) FROM product_batches WHERE product_id = NEW.product_id AND qty > 0), 0)
        WHERE product_id = NEW.product_id;
    END
");
if ($conn->error) {
    echo "Error on insert trigger: " . $conn->error . "\n";
} else {
    echo "Trigger trg_pb_after_insert active.\n";
}

$conn->query("DROP TRIGGER IF EXISTS trg_pb_after_update");
$conn->query("
    CREATE TRIGGER trg_pb_after_update AFTER UPDATE ON product_batches
    FOR EACH ROW
    BEGIN
        UPDATE products 
        SET quantity = COALESCE((SELECT SUM(qty) FROM product_batches WHERE product_id = NEW.product_id AND qty > 0), 0)
        WHERE product_id = NEW.product_id;
        IF OLD.product_id != NEW.product_id THEN
            UPDATE products 
            SET quantity = COALESCE((SELECT SUM(qty) FROM product_batches WHERE product_id = OLD.product_id AND qty > 0), 0)
            WHERE product_id = OLD.product_id;
        END IF;
    END
");
if ($conn->error) {
    echo "Error on update trigger: " . $conn->error . "\n";
} else {
    echo "Trigger trg_pb_after_update active.\n";
}

$conn->query("DROP TRIGGER IF EXISTS trg_pb_after_delete");
$conn->query("
    CREATE TRIGGER trg_pb_after_delete AFTER DELETE ON product_batches
    FOR EACH ROW
    BEGIN
        UPDATE products 
        SET quantity = COALESCE((SELECT SUM(qty) FROM product_batches WHERE product_id = OLD.product_id AND qty > 0), 0)
        WHERE product_id = OLD.product_id;
    END
");
if ($conn->error) {
    echo "Error on delete trigger: " . $conn->error . "\n";
} else {
    echo "Trigger trg_pb_after_delete active.\n";
}

echo "2. Recalculating all active products quantity from product_batches...\n";
$conn->query("
    UPDATE products p
    SET quantity = COALESCE((
        SELECT SUM(pb.qty)
        FROM product_batches pb
        WHERE pb.product_id = p.product_id AND pb.qty > 0
    ), 0)
");
echo "Done. All product totals are now guaranteed derived from product_batches.\n";
