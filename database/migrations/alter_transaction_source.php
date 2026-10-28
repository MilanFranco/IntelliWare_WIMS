<?php
require_once __DIR__ . '/../../config/db.php';
$conn->query("ALTER TABLE transactions MODIFY COLUMN transaction_source VARCHAR(60) NOT NULL DEFAULT 'manual'");
if ($conn->error) {
    echo "Error modifying transaction_source: " . $conn->error . "\n";
} else {
    echo "transaction_source column updated to VARCHAR(60).\n";
}
