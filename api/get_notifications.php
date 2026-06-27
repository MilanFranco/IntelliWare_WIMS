<?php
// api/get_notifications.php
header('Content-Type: application/json');

// 1. Connect to Database (Adjust path if needed)
require_once '../config/db.php'; 

// 2. Query for Low Stock Items
// We check where quantity is less than or equal to reorder_point
$sql = "SELECT product_name, quantity, reorder_point, unit 
        FROM products 
        WHERE quantity <= reorder_point 
        ORDER BY quantity ASC";

$result = $conn->query($sql);
$notifications = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'type' => 'low_stock',
            'product' => $row['product_name'],
            'qty' => $row['quantity'],
            'unit' => $row['unit'],
            'message' => "Low stock: <strong>{$row['product_name']}</strong> ({$row['quantity']} {$row['unit']} left)",
            'time' => 'Action needed' // You could add a timestamp if you track 'updated_at'
        ];
    }
}

// 3. Return JSON
echo json_encode([
    'count' => count($notifications),
    'items' => $notifications
]);
exit;
?>