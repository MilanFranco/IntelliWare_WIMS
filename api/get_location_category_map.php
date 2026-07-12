<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/LocationCategoryService.php';

$map = LocationCategoryService::buildMap($conn);
$conn->close();

echo json_encode([
    'success' => true,
    'driver' => $map['driver'],
    'category_to_location' => $map['category_to_location'],
    'location_to_category' => $map['location_to_category'],
    'categories' => $map['categories'],
]);
