<?php
// api/get_notifications.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/NotificationService.php';

// Ensure tables are initialized
NotificationService::ensureTables($conn);

// Query active inventory alerts: Out of stock (0) first, then low stock (> 0 AND <= reorder_point)
$sql = "SELECT product_id, product_name, quantity, reorder_point, unit, location,
               CONCAT('P-', LPAD(product_id, 4, '0')) AS sku
        FROM products 
        WHERE archived = 0 AND quantity <= reorder_point 
        ORDER BY 
            CASE WHEN quantity = 0 THEN 0 ELSE 1 END,
            quantity ASC,
            product_name ASC";

$result = $conn->query($sql);
$notifications = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $qty = (int)$row['quantity'];
        $unit = htmlspecialchars($row['unit'] ?: 'units');
        $name = htmlspecialchars($row['product_name']);

        if ($qty === 0) {
            // Distinct Out-of-Stock Alert
            $notifications[] = [
                'type'     => 'out_of_stock',
                'product'  => $row['product_name'],
                'sku'      => $row['sku'],
                'qty'      => 0,
                'unit'     => $row['unit'],
                'title'    => 'OUT OF STOCK',
                'headline' => "{$name} is now out of stock.",
                'message'  => "OUT OF STOCK: <strong>{$name}</strong> is now out of stock. Current stock: 0.",
                'subtext'  => "Current stock: 0 &bull; Please restock this product.",
                'time'     => 'Urgent Restock Needed',
                'badge'    => 'Critical'
            ];
        } else {
            // Existing Low Stock Alert preserved
            $notifications[] = [
                'type'     => 'low_stock',
                'product'  => $row['product_name'],
                'sku'      => $row['sku'],
                'qty'      => $qty,
                'unit'     => $row['unit'],
                'title'    => 'LOW STOCK',
                'headline' => "Low stock: {$name}",
                'message'  => "Low stock: <strong>{$name}</strong> ({$qty} {$unit} left)",
                'subtext'  => "{$qty} {$unit} left (Reorder point: {$row['reorder_point']})",
                'time'     => 'Action needed',
                'badge'    => 'Warning'
            ];
        }
    }
}

// Return JSON response
echo json_encode([
    'success' => true,
    'count'   => count($notifications),
    'items'   => $notifications
]);
exit;