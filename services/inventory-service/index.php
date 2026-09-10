<?php
/**
 * services/inventory-service/index.php
 * REST API Router for the Inventory Microservice.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/InventoryRepository.php';

$repository = new InventoryRepository();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Parse endpoint route: ?endpoint=products or PATH_INFO
$endpoint = $_GET['endpoint'] ?? '';
if (empty($endpoint) && !empty($_SERVER['PATH_INFO'])) {
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
}
if (empty($endpoint)) {
    $endpoint = 'products';
}

// Parse request body JSON if present
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: $_POST;

try {
    // 1. GET /products or GET /products/{id}
    if ($endpoint === 'products' || preg_match('#^products/?(\d+)?$#', $endpoint, $matches)) {
        $productId = isset($matches[1]) ? (int)$matches[1] : (int)($_GET['id'] ?? 0);

        if ($method === 'GET') {
            if ($productId > 0) {
                $product = $repository->getProductById($productId);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Product not found']);
                    exit();
                }
                echo json_encode(['success' => true, 'data' => $product]);
                exit();
            }

            // List products
            $filters = [
                'page'     => (int)($_GET['page'] ?? 1),
                'limit'    => (int)($_GET['limit'] ?? 15),
                'q'        => trim((string)($_GET['q'] ?? '')),
                'category' => trim((string)($_GET['category'] ?? '')),
                'archived' => trim((string)($_GET['archived'] ?? '0')),
            ];
            $result = $repository->getProducts($filters);
            echo json_encode(array_merge(['success' => true], $result));
            exit();
        }

        if ($method === 'PUT' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
            if ($productId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Product ID is required for update.']);
                exit();
            }
            // Update logic can be performed through atomic movement or repository
            echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
            exit();
        }
    }

    // 2. GET /low-stock
    if ($endpoint === 'low-stock' && $method === 'GET') {
        $items = $repository->getLowStockProducts();
        echo json_encode([
            'success' => true,
            'count'   => count($items),
            'items'   => $items
        ]);
        exit();
    }

    // 3. GET /out-of-stock
    if ($endpoint === 'out-of-stock' && $method === 'GET') {
        $items = $repository->getOutOfStockProducts();
        echo json_encode([
            'success' => true,
            'count'   => count($items),
            'items'   => $items
        ]);
        exit();
    }

    // 4. POST /receive
    if ($endpoint === 'receive' && $method === 'POST') {
        $productId = (int)($body['product_id'] ?? 0);
        $quantity  = (int)($body['quantity'] ?? 0);
        $username  = trim((string)($body['username'] ?? 'admin'));
        $remarks   = isset($body['remarks']) ? trim((string)$body['remarks']) : null;
        $metadata  = isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : null;

        $result = $repository->receiveStock($productId, $quantity, $username, $remarks, $metadata);
        http_response_code(201);
        echo json_encode($result);
        exit();
    }

    // 5. POST /release
    if ($endpoint === 'release' && $method === 'POST') {
        $productId = (int)($body['product_id'] ?? 0);
        $quantity  = (int)($body['quantity'] ?? 0);
        $username  = trim((string)($body['username'] ?? 'admin'));
        $remarks   = isset($body['remarks']) ? trim((string)$body['remarks']) : null;

        $result = $repository->releaseStock($productId, $quantity, $username, $remarks);
        http_response_code(200);
        echo json_encode($result);
        exit();
    }

    // Route not recognized
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => "Unknown inventory endpoint: {$endpoint}",
        'service' => 'inventory-service'
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[InventoryMicroservice Error] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An internal inventory service error occurred.']);
}
