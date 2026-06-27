<?php
/**
 * Central inventory movement service.
 * All stock increases/decreases must go through this class to preserve audit trail.
 */
class StockMovementService
{
    private static $auditColumnsChecked = false;
    private static $hasAuditColumns = false;

    public static function hasAuditColumns(mysqli $conn): bool
    {
        if (!self::$auditColumnsChecked) {
            $result = $conn->query("SHOW COLUMNS FROM transactions LIKE 'previous_stock'");
            self::$hasAuditColumns = $result && $result->num_rows > 0;
            self::$auditColumnsChecked = true;
        }
        return self::$hasAuditColumns;
    }

    public static function ensureAuditColumns(mysqli $conn): void
    {
        if (self::hasAuditColumns($conn)) {
            return;
        }

        $conn->query("ALTER TABLE `transactions` ADD COLUMN `previous_stock` INT(11) DEFAULT NULL AFTER `quantity`");
        $conn->query("ALTER TABLE `transactions` ADD COLUMN `new_stock` INT(11) DEFAULT NULL AFTER `previous_stock`");
        $conn->query("ALTER TABLE `transactions` ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL AFTER `new_stock`");

        self::$auditColumnsChecked = true;
        self::$hasAuditColumns = true;
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['in', 'stock_in', 'receive'], true)) {
            return 'stock_in';
        }
        if (in_array($type, ['out', 'stock_out', 'release'], true)) {
            return 'stock_out';
        }
        throw new InvalidArgumentException('Invalid transaction type.');
    }

    public static function getUserId(mysqli $conn, string $username): int
    {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['user_id'] ?? 1);
    }

    /**
     * Process a stock movement inside an existing DB transaction.
     *
     * @return array{transaction_id:int,previous_stock:int,new_stock:int,product:array}
     */
    public static function processMovement(
        mysqli $conn,
        int $productId,
        int $quantity,
        string $type,
        string $username,
        ?string $remarks = null
    ): array {
        if ($productId <= 0) {
            throw new Exception('Invalid product.');
        }
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero.');
        }

        $transactionType = self::normalizeType($type);
        self::ensureAuditColumns($conn);

        $productStmt = $conn->prepare(
            'SELECT product_id, product_name, barcode, quantity, unit, reorder_point,
                    CONCAT(\'P-\', LPAD(product_id, 4, \'0\')) AS sku
             FROM products WHERE product_id = ? FOR UPDATE'
        );
        $productStmt->bind_param('i', $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        $productStmt->close();

        if (!$product) {
            throw new Exception('Product not found.');
        }

        $previousStock = (int)$product['quantity'];
        $newStock = $previousStock;

        if ($transactionType === 'stock_in') {
            $newStock = $previousStock + $quantity;
            $updateStmt = $conn->prepare('UPDATE products SET quantity = ? WHERE product_id = ?');
            $updateStmt->bind_param('ii', $newStock, $productId);
        } else {
            if ($quantity > $previousStock) {
                throw new Exception(
                    'Insufficient stock. Available: ' . $previousStock . ', requested: ' . $quantity . '.'
                );
            }
            $newStock = $previousStock - $quantity;
            $updateStmt = $conn->prepare('UPDATE products SET quantity = ? WHERE product_id = ?');
            $updateStmt->bind_param('ii', $newStock, $productId);
        }

        if (!$updateStmt->execute()) {
            $updateStmt->close();
            throw new Exception('Failed to update stock.');
        }
        $updateStmt->close();

        if ($remarks === null || $remarks === '') {
            $remarks = $transactionType === 'stock_in' ? 'Stock received' : 'Stock issued';
        }

        $userId = self::getUserId($conn, $username);
        $barcode = $product['barcode'] ?? null;

        $transStmt = $conn->prepare(
            'INSERT INTO transactions
            (product_id, user_id, transaction_type, quantity, previous_stock, new_stock, barcode, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $transStmt->bind_param(
            'iisiiiss',
            $productId,
            $userId,
            $transactionType,
            $quantity,
            $previousStock,
            $newStock,
            $barcode,
            $remarks
        );
        if (!$transStmt->execute()) {
            $transStmt->close();
            throw new Exception('Failed to record transaction.');
        }
        $transactionId = (int)$conn->insert_id;
        $transStmt->close();

        $logAction = sprintf(
            'Processed %s (Qty: %d) for %s — stock %d → %d',
            $transactionType,
            $quantity,
            $product['product_name'],
            $previousStock,
            $newStock
        );
        $logStmt = $conn->prepare('INSERT INTO logs (user_id, action) VALUES (?, ?)');
        $logStmt->bind_param('is', $userId, $logAction);
        $logStmt->execute();
        $logStmt->close();

        $product['quantity'] = $newStock;

        return [
            'transaction_id' => $transactionId,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'product' => $product,
            'transaction_type' => $transactionType,
        ];
    }

    /**
     * Wrap processMovement in begin/commit with flash-friendly errors.
     */
    public static function executeMovement(
        mysqli $conn,
        int $productId,
        int $quantity,
        string $type,
        string $username,
        ?string $remarks = null
    ): array {
        $conn->begin_transaction();
        try {
            $result = self::processMovement($conn, $productId, $quantity, $type, $username, $remarks);
            $conn->commit();
            return $result;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
