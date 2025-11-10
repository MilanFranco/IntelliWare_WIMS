<?php
header('Content-Type: application/json');
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