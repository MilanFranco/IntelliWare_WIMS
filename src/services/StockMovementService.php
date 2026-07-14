<?php
/**
 * Central inventory movement service.
 * All stock increases/decreases must go through this class to preserve audit trail.
 */
class StockMovementService
{
    private static $auditColumnsChecked = false;
    private static $hasAuditColumns = false;
    private static $batchColumnChecked = false;
    private static $hasBatchColumn = false;

    public static function ensureBatchColumn(mysqli $conn): void
    {
        self::$batchColumnChecked = true;
        self::$hasBatchColumn = true;
    }

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
        ?string $remarks = null,
        ?array $metadata = null
    ): array {
        if ($productId <= 0) {
            throw new Exception('Invalid product.');
        }
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than zero.');
        }

        $transactionType = self::normalizeType($type);
        self::ensureAuditColumns($conn);
        self::ensureBatchColumn($conn);

        $productStmt = $conn->prepare(
            'SELECT p.product_id, p.product_name, p.barcode, p.category, p.location, p.quantity, p.unit,
                    p.reorder_point,
                    (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
                    (SELECT b.batch_number FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0 ORDER BY b.expiry_date ASC LIMIT 1) AS batch_number,
                    CONCAT(\'P-\', LPAD(p.product_id, 4, \'0\')) AS sku
             FROM products p WHERE p.product_id = ? FOR UPDATE'
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

        if ($transactionType === 'stock_in' && is_array($metadata) && !empty($metadata)) {
            self::applyProductMetadata($conn, $productId, $metadata);
        }

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

        // Record or deduct inventory batches
        if ($transactionType === 'stock_in') {
            $batchNo = !empty($metadata['batch_number']) ? trim((string)$metadata['batch_number']) : null;
            $expDate = !empty($metadata['expiry_date']) ? trim((string)$metadata['expiry_date']) : null;
            $mfgDate = !empty($metadata['manufacturing_date']) ? trim((string)$metadata['manufacturing_date']) : null;

            $batchStmt = $conn->prepare(
                'INSERT INTO inventory_batches (product_id, transaction_id, batch_number, quantity, initial_quantity, manufacturing_date, expiry_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $batchStmt->bind_param('iisiiss', $productId, $transactionId, $batchNo, $quantity, $quantity, $mfgDate, $expDate);
            if (!$batchStmt->execute()) {
                $batchStmt->close();
                throw new Exception('Failed to record inventory batch.');
            }
            $batchStmt->close();
        } else {
            // Deduct from batches using FEFO
            $remainingToDeduct = $quantity;

            $batchQuery = 'SELECT batch_id, quantity FROM inventory_batches WHERE product_id = ? AND quantity > 0 ORDER BY expiry_date IS NULL ASC, expiry_date ASC, date_received ASC, batch_id ASC';
            $batchStmt = $conn->prepare($batchQuery);
            $batchStmt->bind_param('i', $productId);
            $batchStmt->execute();
            $batches = $batchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $batchStmt->close();

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) {
                    break;
                }
                $batchId = (int)$batch['batch_id'];
                $batchQty = (int)$batch['quantity'];

                if ($batchQty >= $remainingToDeduct) {
                    $newBatchQty = $batchQty - $remainingToDeduct;
                    $remainingToDeduct = 0;
                } else {
                    $newBatchQty = 0;
                    $remainingToDeduct -= $batchQty;
                }

                $updateBatchStmt = $conn->prepare('UPDATE inventory_batches SET quantity = ? WHERE batch_id = ?');
                $updateBatchStmt->bind_param('ii', $newBatchQty, $batchId);
                if (!$updateBatchStmt->execute()) {
                    $updateBatchStmt->close();
                    throw new Exception('Failed to update inventory batch quantity.');
                }
                $updateBatchStmt->close();
            }
        }

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
     * Update product metadata after a stock-in receive.
     */
    private static function applyProductMetadata(mysqli $conn, int $productId, array $metadata): void
    {
        $fields = [];
        $params = [];
        $types = '';

        if (!empty($metadata['location'])) {
            $fields[] = 'location = ?';
            $params[] = trim((string)$metadata['location']);
            $types .= 's';
        }

        if (!empty($metadata['category'])) {
            $fields[] = 'category = ?';
            $params[] = trim((string)$metadata['category']);
            $types .= 's';
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE product_id = ?';
        $params[] = $productId;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to update product inventory details.');
        }
        $stmt->close();
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
        ?string $remarks = null,
        ?array $metadata = null
    ): array {
        $conn->begin_transaction();
        try {
            $result = self::processMovement($conn, $productId, $quantity, $type, $username, $remarks, $metadata);
            $conn->commit();
            return $result;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Process multiple stock movements in a single database transaction.
     *
     * @param array<int, array{product_id:int, qty:int}> $items
     * @return array<int, array>
     */
    public static function executeBatchMovement(
        mysqli $conn,
        array $items,
        string $type,
        string $username,
        ?string $remarks = null
    ): array {
        if (empty($items)) {
            throw new Exception('No items in transaction.');
        }

        $conn->begin_transaction();
        try {
            $results = [];
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);
                $metadata = isset($item['metadata']) && is_array($item['metadata']) ? $item['metadata'] : null;
                $results[] = self::processMovement(
                    $conn,
                    $productId,
                    $qty,
                    $type,
                    $username,
                    $remarks,
                    $metadata
                );
            }
            $conn->commit();
            return $results;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
