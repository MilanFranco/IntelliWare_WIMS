<?php

require_once __DIR__ . '/app.php';

$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? '')));
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'warehouse_system');
$dbPort = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
	die('Database connection failed: ' . $conn->connect_error);
}


$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Manila');
?>


