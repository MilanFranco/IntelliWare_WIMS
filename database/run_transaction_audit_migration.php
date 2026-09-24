<?php
/**
 * One-time migration: add audit columns to transactions table.
 * Run once via browser or CLI.
 */
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $result && $result->num_rows > 0;
}

function indexExists(mysqli $conn, string $table, string $index): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeIndex = $conn->real_escape_string($index);
    $result = $conn->query("SHOW INDEX FROM `$safeTable` WHERE Key_name = '$safeIndex'");
    return $result && $result->num_rows > 0;
}

$changes = [];

if (!columnExists($conn, 'transactions', 'previous_stock')) {
    if ($conn->query("ALTER TABLE `transactions` ADD COLUMN `previous_stock` INT(11) DEFAULT NULL AFTER `quantity`")) {
        $changes[] = 'previous_stock';
    } else {
        echo "ERROR adding previous_stock: " . $conn->error . "\n";
        exit(1);
    }
}

if (!columnExists($conn, 'transactions', 'new_stock')) {
    if ($conn->query("ALTER TABLE `transactions` ADD COLUMN `new_stock` INT(11) DEFAULT NULL AFTER `previous_stock`")) {
        $changes[] = 'new_stock';
    } else {
        echo "ERROR adding new_stock: " . $conn->error . "\n";
        exit(1);
    }
}


if (!indexExists($conn, 'transactions', 'idx_transactions_date')) {
    if ($conn->query("CREATE INDEX `idx_transactions_date` ON `transactions` (`transaction_date`)")) {
        $changes[] = 'idx_transactions_date';
    } else {
        echo "ERROR adding index: " . $conn->error . "\n";
        exit(1);
    }
}

if ($changes) {
    echo "OK: added " . implode(', ', $changes) . ".\n";
} else {
    echo "OK: transaction audit columns already exist.\n";
}

$conn->close();
