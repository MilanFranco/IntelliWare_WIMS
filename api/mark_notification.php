<?php
// api/mark_notification.php
// Toggle a notification's read/unread status, or mark all as read.

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true) ?: [];
$action = $body['action'] ?? '';  // 'mark_read' | 'mark_unread' | 'mark_all_read'
$id     = isset($body['id']) ? (int)$body['id'] : 0;

if ($action === 'mark_all_read') {
    $conn->query("UPDATE notifications SET status='READ' WHERE channel='SYSTEM' AND status != 'READ'");
    echo json_encode(['success' => true, 'updated' => $conn->affected_rows]);
    exit;
}

if (!$id || !in_array($action, ['mark_read', 'mark_unread'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$newStatus = ($action === 'mark_read') ? 'READ' : 'SENT';
$stmt = $conn->prepare("UPDATE notifications SET status=? WHERE id=? AND channel='SYSTEM'");
$stmt->bind_param('si', $newStatus, $id);
$stmt->execute();
$ok = ($stmt->affected_rows > 0);
$stmt->close();

echo json_encode(['success' => $ok]);
exit;
