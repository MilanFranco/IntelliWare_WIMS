<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username']) || $_SESSION['Role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db.php';

// Get low stock items
$query = "SELECT 
            product_id,
            product_name,
            quantity,
            reorder_point,
            TIMESTAMPDIFF(MINUTE, last_updated, NOW()) as minutes_ago
          FROM products 
          WHERE quantity <= reorder_point 
          ORDER BY quantity ASC, last_updated DESC
          LIMIT 10";

$result = $conn->query($query);
$notifications = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $minutesAgo = (int)$row['minutes_ago'];
        
        // Format time
        if ($minutesAgo < 1) {
            $timeText = 'Just now';
        } elseif ($minutesAgo < 60) {
            $timeText = $minutesAgo . ' min ago';
        } elseif ($minutesAgo < 1440) {
            $hours = floor($minutesAgo / 60);
            $timeText = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($minutesAgo / 1440);
            $timeText = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        
        $notifications[] = [
            'type' => 'low_stock',
            'product_name' => $row['product_name'],
            'quantity' => (int)$row['quantity'],
            'reorder_point' => (int)$row['reorder_point'],
            'time' => $timeText
        ];
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'count' => count($notifications),
    'notifications' => $notifications
]);
?>