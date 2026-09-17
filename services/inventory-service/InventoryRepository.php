<?php
/**
 * services/inventory-service/InventoryRepository.php
 * Core repository and transaction engine for the Inventory Microservice.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../../src/services/NotificationService.php';
require_once __DIR__ . '/../../src/services/StockMovementService.php';

class InventoryRepository
{
    private mysqli $conn;

    public function __construct(?mysqli $conn = null)
    {
        $this->conn = $conn ?: getInventoryDbConnection();
    }

    /**
     * Fetch paginated products with filtering and search.
     */
    public function getProducts(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;
        $q = trim((string)($filters['q'] ?? ($filters['search'] ?? '')));
        $category = trim((string)($filters['category'] ?? ''));
        $archived = isset($filters['archived']) && (string)$filters['archived'] === '1' ? 1 : 0;
        $statusRaw = strtolower(trim((string)($filters['status'] ?? ($filters['filter'] ?? ''))));

        $where = ['p.archived = ?'];
        $params = [$archived];
        $types = 'i';

        if ($q !== '') {
            $where[] = '(p.product_name LIKE ? OR p.supplier LIKE ? OR p.barcode LIKE ? OR p.barcode = ?)';
            $like = "%{$q}%";
            array_push($params, $like, $like, $like, $q);
            $types .= 'ssss';
        }

        if ($category !== '') {
            $where[] = 'p.category = ?';
            $params[] = $category;
            $types .= 's';
        }

        if ($statusRaw !== '') {
            if ($statusRaw === 'low' || $statusRaw === 'low_stock') {
                $where[] = 'p.quantity > 0 AND p.quantity <= p.reorder_point';
            } elseif ($statusRaw === 'out' || $statusRaw === 'out_of_stock') {
                $where[] = 'p.quantity <= 0';
            } elseif ($statusRaw === 'good' || $statusRaw === 'in_stock') {
                $where[] = 'p.quantity > p.reorder_point';
            } elseif ($statusRaw === 'attention') {
                $where[] = 'p.quantity <= p.reorder_point';
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // 1. Total Count
        $countStmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM products p {$whereSql}");
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // 2. Fetch Items
        $sql = "
            SELECT 
                p.product_id AS id,
                CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
                p.product_name AS name,
                p.barcode,
                p.category,
                p.quantity AS qty,
                p.unit AS uom,
                p.supplier,
                p.location,
                p.reorder_point,
                p.price,
                p.shelf_life,
                p.archived,
                (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS next_expiry
            FROM products p
            {$whereSql}
            ORDER BY p.product_id DESC
            LIMIT ? OFFSET ?
        ";

        $mainParams = array_merge($params, [$limit, $offset]);
        $mainTypes = $types . 'ii';

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($mainTypes, ...$mainParams);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['qty'] = (int)$row['qty'];
            $row['reorder_point'] = (int)$row['reorder_point'];
            $row['price'] = (float)$row['price'];
            $row['archived'] = (int)$row['archived'];
            $items[] = $row;
        }
        $stmt->close();

        return [
            'total'        => $total,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => ceil($total / $limit),
            'items'        => $items
        ];
    }

    /**
     * Get single product details by ID.
     */
    public function getProductById(int $productId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                p.product_id AS id,
                CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
                p.product_name AS name,
                p.barcode,
                p.category,
                p.quantity AS qty,
                p.unit AS uom,
                p.supplier,
                p.location,
                p.reorder_point,
                p.price,
                p.shelf_life,
                p.archived,
                (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS next_expiry
            FROM products p
            WHERE p.product_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        $product = $res->fetch_assoc();
        $stmt->close();

        if (!$product) {
            return null;
        }

        // Fetch active batches
        $batchStmt = $this->conn->prepare("
            SELECT batch_id, batch_number, quantity, expiry_date, date_received
            FROM inventory_batches
            WHERE product_id = ? AND quantity > 0
            ORDER BY expiry_date ASC, batch_id ASC
        ");
        $batchStmt->bind_param('i', $productId);
        $batchStmt->execute();
        $batches = $batchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $batchStmt->close();

        $product['id'] = (int)$product['id'];
        $product['qty'] = (int)$product['qty'];
        $product['batches'] = $batches;

        return $product;
    }

    /**
     * Get products with quantity <= reorder_point and > 0.
     */
    public function getLowStockProducts(): array
    {
        $sql = "
            SELECT 
                p.product_id AS id,
                p.product_name AS name,
                CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
                p.quantity AS qty,
                p.reorder_point,
                p.unit AS uom,
                p.location
            FROM products p
            WHERE p.archived = 0 AND p.quantity > 0 AND p.quantity <= p.reorder_point
            ORDER BY p.quantity ASC, p.product_name ASC
        ";
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get products with quantity = 0.
     */
    public function getOutOfStockProducts(): array
    {
        $sql = "
            SELECT 
                p.product_id AS id,
                p.product_name AS name,
                CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
                p.quantity AS qty,
                p.unit AS uom,
                p.location
            FROM products p
            WHERE p.archived = 0 AND p.quantity = 0
            ORDER BY p.product_name ASC
        ";
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Atomic Receive Inventory (Stock In).
     * Delegated to unified StockMovementService engine.
     */
    public function receiveStock(
        int $productId,
        int $quantity,
        string $username,
        ?string $remarks = null,
        ?array $metadata = null,
        string $source = 'manual'
    ): array {
        if ($productId <= 0) {
            throw new InvalidArgumentException('Invalid product ID.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to receive must be greater than zero.');
        }

        try {
            $moveResult = StockMovementService::executeMovement(
                $this->conn,
                $productId,
                $quantity,
                'stock_in',
                $username,
                $remarks ?: 'Inventory received',
                $metadata,
                $source
            );

            // Trigger non-blocking async notice to Insights Service to invalidate stale analytics
            $this->notifyInsightsAsync('stock_in', $productId);

            return [
                'success'         => true,
                'transaction_id'  => (int)$moveResult['transaction_id'],
                'product_id'      => $productId,
                'previous_stock'  => (int)$moveResult['previous_stock'],
                'new_stock'       => (int)$moveResult['new_stock'],
                'received_qty'    => $quantity
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Atomic Release Inventory (Stock Out).
     * Delegated to unified StockMovementService engine.
     */
    public function releaseStock(
        int $productId,
        int $quantity,
        string $username,
        ?string $remarks = null,
        string $source = 'manual'
    ): array {
        if ($productId <= 0) {
            throw new InvalidArgumentException('Invalid product ID.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to release must be greater than zero.');
        }

        try {
            $moveResult = StockMovementService::executeMovement(
                $this->conn,
                $productId,
                $quantity,
                'stock_out',
                $username,
                $remarks ?: 'Stock issued',
                null,
                $source
            );

            // Trigger non-blocking async notice to Insights Service
            $this->notifyInsightsAsync('stock_out', $productId);

            return [
                'success'                => true,
                'transaction_id'         => (int)$moveResult['transaction_id'],
                'product_id'             => $productId,
                'previous_stock'         => (int)$moveResult['previous_stock'],
                'new_stock'              => (int)$moveResult['new_stock'],
                'released_qty'           => $quantity,
                'is_out_of_stock_alert'  => !empty($moveResult['is_out_of_stock_transition'])
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Resolve user ID safely.
     */
    private function resolveUserId(string $username): int
    {
        $stmt = $this->conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['user_id'])) {
            return (int)$row['user_id'];
        }
        $fallback = $this->conn->query('SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1');
        if ($fallback && $f = $fallback->fetch_assoc()) {
            return (int)$f['user_id'];
        }
        return 1;
    }

    /**
     * Non-blocking async ping to Insights Service to mark cache stale / trigger background processing.
     */
    private function notifyInsightsAsync(string $eventType, int $productId): void
    {
        // Use non-blocking cURL or local file marker to notify Insights Microservice without stalling
        $insightsUrl = 'http://localhost/intelliware/services/insights-service/index.php?action=event';
        $ch = curl_init($insightsUrl);
        if ($ch) {
            $payload = json_encode(['event' => $eventType, 'product_id' => $productId, 'timestamp' => time()]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300); // 300ms max non-blocking
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            @curl_exec($ch);
            curl_close($ch);
        }
    }
}
