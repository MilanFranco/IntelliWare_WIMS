<?php
// api/preview_email.php
// Instant browser preview of the inventory status report email template
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo "Unauthorized: Please log in to preview email templates.";
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo "Forbidden: Insufficient privileges.";
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/EmailService.php';

$type = $_GET['type'] ?? 'complete_table';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
echo EmailService::renderSampleHtml($type, $conn);
