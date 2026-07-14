<?php
require '../config/db.php';

header('Content-Type: application/json');

$tableType = $_GET['type'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$response = [
    'data' => [],
    'total' => 0,
    'page' => $page,
    'limit' => $limit
];

function fetchPaginatedData($conn, $countQuery, $dataQuery, $offset, $limit) {
    $total = 0;
    if ($res = $conn->query($countQuery)) {
        $total = (int)$res->fetch_row()[0];
    }
    
    $data = [];
    $dataQuery .= " LIMIT $limit OFFSET $offset";
    if ($res = $conn->query($dataQuery)) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return ['total' => $total, 'data' => $data];
}

switch ($tableType) {
    case 'expiry_risk':
        $countQuery = "
            SELECT COUNT(*) 
            FROM inventory_batches b
            WHERE b.expiry_date IS NOT NULL AND b.expiry_date > CURDATE() AND b.quantity > 0
        ";
        $dataQuery = "
            SELECT p.product_id, p.product_name, p.category AS category_name, b.quantity, b.expiry_date,
            DATEDIFF(b.expiry_date, CURDATE()) AS days_until_expiry,
            (SELECT SUM(quantity) FROM transactions WHERE product_id = p.product_id AND transaction_type = 'stock_out') AS monthly_sales
            FROM inventory_batches b
            JOIN products p ON b.product_id = p.product_id
            WHERE b.expiry_date IS NOT NULL AND b.expiry_date > CURDATE() AND b.quantity > 0
            ORDER BY days_until_expiry ASC
        ";
        $result = fetchPaginatedData($conn, $countQuery, $dataQuery, $offset, $limit);
        
        // Add calculated fields
        foreach ($result['data'] as &$row) {
            if ($row['days_until_expiry'] <= 30) { $row['expiry_risk'] = 'high'; } 
            elseif ($row['days_until_expiry'] <= 90) { $row['expiry_risk'] = 'medium'; } 
            else { $row['expiry_risk'] = 'low'; }
            
            $daily_sales = ($row['monthly_sales'] ?? 0) / 30;
            $row['sell_through_days'] = $daily_sales > 0 ? round($row['quantity'] / $daily_sales, 1) : 999;
        }
        $response['total'] = $result['total'];
        $response['data'] = $result['data'];
        break;

    case 'supplier_performance':
        $countQuery = "
            SELECT COUNT(DISTINCT supplier) 
            FROM products 
            WHERE supplier IS NOT NULL AND supplier != ''
        ";
        $dataQuery = "
            SELECT p.supplier, COUNT(p.product_id) as product_count, SUM(p.quantity) as total_stock_value,
            AVG(p.quantity) as avg_stock_per_product,
            (SELECT COUNT(*) FROM transactions t JOIN products p2 ON t.product_id = p2.product_id 
             WHERE p2.supplier = p.supplier AND t.transaction_type = 'stock_out') as monthly_sales,
            COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) as low_stock_count
            FROM products p 
            WHERE p.supplier IS NOT NULL AND p.supplier != ''
            GROUP BY p.supplier 
            ORDER BY monthly_sales DESC
        ";
        $result = fetchPaginatedData($conn, $countQuery, $dataQuery, $offset, $limit);
        $response['total'] = $result['total'];
        $response['data'] = $result['data'];
        break;

    case 'category_performance':
        $countQuery = "
            SELECT COUNT(DISTINCT category) 
            FROM products 
            WHERE category IS NOT NULL AND category != ''
        ";
        $dataQuery = "
            SELECT p.category AS category_name, COUNT(p.product_id) AS product_count, SUM(p.quantity) AS total_stock,
            AVG(p.quantity) AS avg_stock, COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) AS low_stock_count,
            (SELECT COUNT(*) FROM transactions t JOIN products p2 ON t.product_id = p2.product_id
             WHERE p2.category = p.category AND t.transaction_type = 'stock_out') AS monthly_sales
            FROM products p
            WHERE p.category IS NOT NULL AND p.category != ''
            GROUP BY p.category 
            ORDER BY monthly_sales DESC
        ";
        $result = fetchPaginatedData($conn, $countQuery, $dataQuery, $offset, $limit);
        $response['total'] = $result['total'];
        $response['data'] = $result['data'];
        break;
        
    case 'recent_activity':
        $countQuery = "SELECT COUNT(*) FROM transactions";
        $dataQuery = "
            SELECT t.transaction_type, t.quantity, t.transaction_date, p.product_name, COALESCE(u.username, 'System') as username
            FROM transactions t
            STRAIGHT_JOIN products p ON t.product_id = p.product_id
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY t.transaction_date DESC
        ";
        $result = fetchPaginatedData($conn, $countQuery, $dataQuery, $offset, $limit);
        $response['total'] = $result['total'];
        $response['data'] = $result['data'];
        break;

    default:
        $response['error'] = 'Invalid table type';
        break;
}

echo json_encode($response);
$conn->close();
?>
