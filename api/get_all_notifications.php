<?php
// api/get_all_notifications.php
// Returns full notification history from the notifications table (with read/unread state).

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/NotificationService.php';

NotificationService::ensureTables($conn);

// Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Filter: 'all' | 'unread' | 'read'
$filter = $_GET['filter'] ?? 'all';
$whereParts = ["channel = 'SYSTEM'"];
if ($filter === 'unread') {
    $whereParts[] = "status != 'READ'";
} elseif ($filter === 'read') {
    $whereParts[] = "status = 'READ'";
}
$where = implode(' AND ', $whereParts);

// Total count
$countSql  = "SELECT COUNT(*) AS total FROM notifications WHERE {$where}";
$countRes  = $conn->query($countSql);
$total     = $countRes ? (int)($countRes->fetch_assoc()['total'] ?? 0) : 0;

// Unread count (always)
$unreadRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE channel='SYSTEM' AND status != 'READ'");
$unreadCount = $unreadRes ? (int)($unreadRes->fetch_assoc()['c'] ?? 0) : 0;

// Fetch rows
$sql = "
    SELECT n.id, n.product_id, n.notification_type, n.title, n.message,
           n.status, n.created_at,
           p.product_name, p.quantity, p.unit, p.reorder_point,
           CONCAT('P-', LPAD(n.product_id, 4, '0')) AS sku
    FROM notifications n
    LEFT JOIN products p ON p.product_id = n.product_id
    WHERE {$where}
    ORDER BY n.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$result = $conn->query($sql);
$items  = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $type    = strtolower($row['notification_type'] ?? 'low_stock');
        $isOut   = ($type === 'out_of_stock');
        $isRead  = ($row['status'] === 'READ');
        $name    = htmlspecialchars($row['product_name'] ?? 'Unknown Product');
        $qty     = (int)($row['quantity'] ?? 0);
        $unit    = htmlspecialchars($row['unit'] ?? 'units');
        $rp      = (int)($row['reorder_point'] ?? 0);

        // Human-readable time
        $ts = strtotime($row['created_at']);
        $diff = time() - $ts;
        if ($diff < 60)           $timeAgo = 'Just now';
        elseif ($diff < 3600)     $timeAgo = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400)    $timeAgo = floor($diff / 3600) . 'h ago';
        elseif ($diff < 604800)   $timeAgo = floor($diff / 86400) . 'd ago';
        else                      $timeAgo = date('M j, Y', $ts);

        $items[] = [
            'id'         => (int)$row['id'],
            'product_id' => (int)($row['product_id'] ?? 0),
            'sku'        => $row['sku'] ?? '',
            'type'       => $type,
            'is_out'     => $isOut,
            'is_read'    => $isRead,
            'title'      => $isOut ? 'Out of Stock' : 'Low Stock',
            'message'    => strip_tags($row['message'] ?? ($isOut ? "{$name} is out of stock." : "Low stock: {$name} ({$qty} {$unit} left)")),
            'product'    => $row['product_name'] ?? '',
            'qty'        => $qty,
            'unit'       => $unit,
            'reorder'    => $rp,
            'time_ago'   => $timeAgo,
            'created_at' => $row['created_at'],
        ];
    }
}

echo json_encode([
    'success'      => true,
    'total'        => $total,
    'unread_count' => $unreadCount,
    'page'         => $page,
    'limit'        => $limit,
    'has_more'     => ($offset + $limit) < $total,
    'items'        => $items,
]);
exit;
