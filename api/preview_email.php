<?php
// api/preview_email.php
// Instant browser preview of the Out-of-Stock email notification template

require_once __DIR__ . '/../src/services/EmailService.php';

$type = $_GET['type'] ?? 'out_of_stock';
header('Content-Type: text/html; charset=UTF-8');
echo EmailService::renderSampleHtml($type);
