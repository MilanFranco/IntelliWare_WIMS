<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient privileges']);
    exit();
}

require_once __DIR__ . '/../../src/ml/ProductRecommendationManager.php';

$action = $_GET['action'] ?? 'current';

try {
    $manager = new ProductRecommendationManager();
    
    switch($action) {
        case 'generate':
            $result = $manager->generateRecommendations();
            break;
            
        case 'restock':
            $result = [
                'success' => true,
                'data' => $manager->getRestockRecommendations()
            ];
            break;
            
        case 'current':
        default:
            $result = [
                'success' => true,
                'data' => $manager->getCurrentMonthRecommendations()
            ];
            break;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}