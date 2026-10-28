<?php
/**
 * Central inventory movement service.
 * All stock increases/decreases must go through this class to preserve audit trail.
 */
require_once __DIR__ . '/../../config/app.php';

class StockMovementService
{
    private static $auditColumnsChecked = false;
    private static $hasAuditColumns = false;
    private static $batchColumnChecked = false;
    private static $hasBatchColumn = false;

    public static function ensureBatchColumn(mysqli $conn): void
    {
        if (self::$batchColumnChecked) {
            return;
        }

        try {
            $check = $conn->query("SHOW TABLES LIKE 'inventory_batches'");
            if (!$check || $check->num_rows === 0) {
                $conn->query("CREATE TABLE IF NOT EXISTS `inventory_batches` (
                    `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT NOT NULL,
                    `transaction_id` INT DEFAULT NULL,
                    `batch_number` VARCHAR(50) DEFAULT NULL,
                    `quantity` INT NOT NULL DEFAULT 0,
                    `initial_quantity` INT NOT NULL DEFAULT 0,
                    `date_received` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `manufacturing_date` DATE DEFAULT NULL,
                    `expiry_date` DATE DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            $pbCheck = $conn->query("SHOW TABLES LIKE 'product_batches'");
            if (!$pbCheck || $pbCheck->num_rows === 0) {
                $conn->query("CREATE TABLE IF NOT EXISTS `product_batches` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (Throwable $e) {
            error_log('[StockMovementService] ensureBatchColumn check: ' . $e->getMessage());
        }

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
        if ($row && !empty($row['user_id'])) {
            return (int)$row['user_id'];
        }
        $fallback = $conn->query('SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1');
        if ($fallback && $fRow = $fallback->fetch_assoc()) {
            return (int)$fRow['user_id'];
        }
        return 1;
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
        ?array $metadata = null,
        string $source = 'manual'
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
            'SELECT p.product_id, p.product_name, p.category, p.location, p.quantity, p.unit,
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

        // Derive actual current stock from active batches
        $curTotStmt = $conn->prepare("SELECT COALESCE(SUM(qty), 0) AS total FROM product_batches WHERE product_id = ? AND qty > 0");
        $curTotStmt->bind_param("i", $productId);
        $curTotStmt->execute();
        $batchTotal = (int)($curTotStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $curTotStmt->close();

        $previousStock = $batchTotal > 0 ? $batchTotal : (int)$product['quantity'];

        if ($transactionType === 'stock_out') {
            if ($quantity > $previousStock) {
                throw new Exception(
                    'Insufficient stock. Available: ' . $previousStock . ', requested: ' . $quantity . '.'
                );
            }
        }

        $userId = self::getUserId($conn, $username);
        $validSources = ['manual', 'ai_scan'];
        $cleanSource = in_array(strtolower(trim($source)), $validSources, true) ? strtolower(trim($source)) : 'manual';

        // Process batch changes
        if ($transactionType === 'stock_in') {
            $batchNo = !empty($metadata['batch_number']) ? trim((string)$metadata['batch_number']) : null;
            $expDate = !empty($metadata['expiry_date']) ? trim((string)$metadata['expiry_date']) : null;
            $mfgDate = !empty($metadata['manufacturing_date']) ? trim((string)$metadata['manufacturing_date']) : null;
            $targetBatchId = !empty($metadata['target_batch_id']) ? (int)$metadata['target_batch_id'] : 0;
            $receiveMode = !empty($metadata['receive_mode']) ? trim((string)$metadata['receive_mode']) : '';

            $merged = false;

            // 1. Direct merge by specified target batch ID
            if ($receiveMode === 'merge' && $targetBatchId > 0) {
                $chk = $conn->prepare("SELECT batch_id, qty, batch_number, expiry_date FROM product_batches WHERE batch_id = ? AND product_id = ? LIMIT 1");
                $chk->bind_param("ii", $targetBatchId, $productId);
                $chk->execute();
                $matchedPb = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($matchedPb) {
                    $pbId = (int)$matchedPb['batch_id'];
                    $newPbQty = (int)$matchedPb['qty'] + $quantity;
                    $updPb = $conn->prepare("UPDATE product_batches SET qty = ?, received_at = CURRENT_TIMESTAMP WHERE batch_id = ?");
                    $updPb->bind_param("ii", $newPbQty, $pbId);
                    $updPb->execute();
                    $updPb->close();

                    // Sync inventory_batches
                    $updIb = $conn->prepare("UPDATE inventory_batches SET quantity = quantity + ?, initial_quantity = initial_quantity + ? WHERE batch_id = ?");
                    if ($updIb) {
                        $updIb->bind_param("iii", $quantity, $quantity, $pbId);
                        $updIb->execute();
                        $updIb->close();
                    }
                    $merged = true;
                }
            }

            // 2. Merge by matching expiry date if merge mode selected or auto-matching
            if (!$merged && $receiveMode !== 'new') {
                if (!empty($expDate)) {
                    $chk = $conn->prepare("SELECT batch_id, qty, batch_number FROM product_batches WHERE product_id = ? AND expiry_date = ? AND qty > 0 ORDER BY batch_id ASC LIMIT 1");
                    $chk->bind_param("is", $productId, $expDate);
                } elseif ($receiveMode === 'merge') {
                    $chk = $conn->prepare("SELECT batch_id, qty, batch_number FROM product_batches WHERE product_id = ? AND (expiry_date IS NULL OR expiry_date = '') AND qty > 0 ORDER BY batch_id ASC LIMIT 1");
                    $chk->bind_param("i", $productId);
                } else {
                    $chk = null;
                }

                if ($chk) {
                    $chk->execute();
                    $matchedPb = $chk->get_result()->fetch_assoc();
                    $chk->close();

                    if ($matchedPb) {
                        $pbId = (int)$matchedPb['batch_id'];
                        $newPbQty = (int)$matchedPb['qty'] + $quantity;
                        $updPb = $conn->prepare("UPDATE product_batches SET qty = ?, received_at = CURRENT_TIMESTAMP WHERE batch_id = ?");
                        $updPb->bind_param("ii", $newPbQty, $pbId);
                        $updPb->execute();
                        $updPb->close();

                        // Sync inventory_batches
                        $updIb = $conn->prepare("UPDATE inventory_batches SET quantity = quantity + ?, initial_quantity = initial_quantity + ? WHERE batch_id = ?");
                        if ($updIb) {
                            $updIb->bind_param("iii", $quantity, $quantity, $pbId);
                            $updIb->execute();
                            $updIb->close();
                        }
                        $merged = true;
                    }
                }
            }

            // 3. Create new batch row if not merged
            if (!$merged) {
                if (empty($batchNo)) {
                    $batchNo = 'BATCH-' . date('Ymd') . '-IN';
                }
                $insPb = $conn->prepare("INSERT INTO product_batches (product_id, qty, expiry_date, received_at, batch_number) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)");
                $insPb->bind_param("iiss", $productId, $quantity, $expDate, $batchNo);
                $insPb->execute();
                $pbId = (int)$conn->insert_id;
                $insPb->close();

                // Sync inventory_batches
                $insIb = $conn->prepare("INSERT INTO inventory_batches (batch_id, product_id, batch_number, quantity, initial_quantity, manufacturing_date, expiry_date, date_received) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
                if ($insIb) {
                    $insIb->bind_param("iisiiss", $pbId, $productId, $batchNo, $quantity, $quantity, $mfgDate, $expDate);
                    $insIb->execute();
                    $insIb->close();
                }
            }
        } else {
            // Deduct from batches using FEFO (First Expired, First Out)
            $remainingToDeduct = $quantity;

            $batchQuery = 'SELECT batch_id, qty FROM product_batches WHERE product_id = ? AND qty > 0 ORDER BY expiry_date IS NULL ASC, expiry_date ASC, received_at ASC, batch_id ASC';
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
                $batchQty = (int)$batch['qty'];

                if ($batchQty >= $remainingToDeduct) {
                    $newBatchQty = $batchQty - $remainingToDeduct;
                    $remainingToDeduct = 0;
                } else {
                    $newBatchQty = 0;
                    $remainingToDeduct -= $batchQty;
                }

                $updatePb = $conn->prepare('UPDATE product_batches SET qty = ? WHERE batch_id = ?');
                $updatePb->bind_param('ii', $newBatchQty, $batchId);
                $updatePb->execute();
                $updatePb->close();

                // Sync inventory_batches
                $updateIb = $conn->prepare('UPDATE inventory_batches SET quantity = ? WHERE batch_id = ?');
                if ($updateIb) {
                    $updateIb->bind_param('ii', $newBatchQty, $batchId);
                    $updateIb->execute();
                    $updateIb->close();
                }
            }
        }

        // Strictly derive product total from SUM(batch.qty)
        $sumStmt = $conn->prepare("SELECT COALESCE(SUM(qty), 0) AS new_total FROM product_batches WHERE product_id = ? AND qty > 0");
        $sumStmt->bind_param("i", $productId);
        $sumStmt->execute();
        $newStock = (int)($sumStmt->get_result()->fetch_assoc()['new_total'] ?? ($transactionType === 'stock_in' ? $previousStock + $quantity : $previousStock - $quantity));
        $sumStmt->close();

        // Update products table with derived sum
        $updProd = $conn->prepare("UPDATE products SET quantity = ? WHERE product_id = ?");
        $updProd->bind_param("ii", $newStock, $productId);
        $updProd->execute();
        $updProd->close();

        if ($transactionType === 'stock_in' && is_array($metadata) && !empty($metadata)) {
            self::applyProductMetadata($conn, $productId, $metadata);
        }

        if ($remarks === null || $remarks === '') {
            $remarks = $transactionType === 'stock_in' ? 'Stock received' : 'Stock issued';
        }

        $transStmt = $conn->prepare(
            'INSERT INTO transactions
            (product_id, user_id, transaction_type, quantity, previous_stock, new_stock, remarks, transaction_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $transStmt->bind_param(
            'iisiisss',
            $productId,
            $userId,
            $transactionType,
            $quantity,
            $previousStock,
            $newStock,
            $remarks,
            $cleanSource
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

        // Invalidate insights cache so the insights page immediately re-studies the database
        try {
            require_once __DIR__ . '/../../services/insights-service/InsightsEngine.php';
            InsightsEngine::invalidateCache($conn);
        } catch (Throwable $e) {
            // Non-blocking
        }

        $product['quantity'] = $newStock;
        $reorderPoint = (int)($product['reorder_point'] ?? 10);
        $isOutOfStockTransition = ($transactionType === 'stock_out' && $previousStock > 0 && $newStock === 0);
        $isLowStockTransition = ($transactionType === 'stock_out' && $previousStock > $reorderPoint && $newStock <= $reorderPoint && $newStock > 0);

        return [
            'transaction_id' => $transactionId,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'product' => $product,
            'transaction_type' => $transactionType,
            'is_out_of_stock_transition' => $isOutOfStockTransition,
            'is_low_stock_transition' => $isLowStockTransition,
            'has_threshold_crossing' => ($isOutOfStockTransition || $isLowStockTransition),
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
        ?array $metadata = null,
        string $source = 'manual'
    ): array {
        $conn->begin_transaction();
        try {
            $result = self::processMovement($conn, $productId, $quantity, $type, $username, $remarks, $metadata, $source);
            $conn->commit();

            // Real-Time Stock Threshold Alert Trigger (Post-Commit)
            if (!empty($result['has_threshold_crossing'])) {
                try {
                    require_once __DIR__ . '/NotificationService.php';
                    NotificationService::handleStockMovementAlert(
                        $conn,
                        $result['product'],
                        (int)$result['previous_stock'],
                        (int)$result['new_stock'],
                        $username,
                        (int)$result['transaction_id']
                    );
                } catch (Throwable $notifErr) {
                    error_log('[StockMovementService] Stock threshold alert trigger error: ' . $notifErr->getMessage());
                }
            }

            // Asynchronous event ping to Insights Microservice (non-blocking)
            try {
                $insightsUrl = function_exists('getInsightsServiceUrl')
                    ? getInsightsServiceUrl() . '?action=event'
                    : 'http://localhost/IntelliWare/services/insights-service/index.php?action=event';
                $ch = curl_init($insightsUrl);
                if ($ch) {
                    $payload = json_encode(['event' => $type, 'product_id' => $productId, 'timestamp' => time()]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 250);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                    @curl_exec($ch);
                    curl_close($ch);
                }
            } catch (Throwable $ignore) {}

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
        ?string $remarks = null,
        string $source = 'manual'
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
                $itemSource = !empty($item['source']) ? (string)$item['source'] : $source;
                $results[] = self::processMovement(
                    $conn,
                    $productId,
                    $qty,
                    $type,
                    $username,
                    $remarks,
                    $metadata,
                    $itemSource
                );
            }
            $conn->commit();

            // Post-commit Batch Stock Alert dispatch (consolidated digest email for multi-item crossing)
            try {
                require_once __DIR__ . '/NotificationService.php';
                NotificationService::handleBatchMovementAlert(
                    $conn,
                    $results,
                    $username
                );
            } catch (Throwable $notifErr) {
                error_log('[StockMovementService] Batch stock alert notification trigger error: ' . $notifErr->getMessage());
            }

            // Asynchronous event ping to Insights Microservice (non-blocking)
            try {
                $insightsUrl = function_exists('getInsightsServiceUrl')
                    ? getInsightsServiceUrl() . '?action=event'
                    : 'http://localhost/IntelliWare/services/insights-service/index.php?action=event';
                $ch = curl_init($insightsUrl);
                if ($ch) {
                    $payload = json_encode(['event' => 'batch_' . $type, 'count' => count($items), 'timestamp' => time()]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 250);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                    @curl_exec($ch);
                    curl_close($ch);
                }
            } catch (Throwable $ignore) {}

            return $results;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
