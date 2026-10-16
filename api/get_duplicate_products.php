<?php
/**
 * api/get_duplicate_products.php
 *
 * Scans the database and returns existing duplicate or near-duplicate product pairs
 * so the user can easily review and 1-click merge them.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/helpers/product_image_url.php';

function normalizeStr(string $str): string {
    $s = preg_replace('/[^a-zA-Z0-9\s]/', ' ', strtolower($str));
    return preg_replace('/\s+/', ' ', trim($s));
}

// 1. Find exact name duplicates among active products
$exactQuery = "
    SELECT p1.product_id as id1, p1.product_name as name1, p1.category as cat1, p1.location as loc1,
           p1.quantity as qty1, p1.unit as unit1, p1.price as price1,
           p2.product_id as id2, p2.product_name as name2, p2.category as cat2, p2.location as loc2,
           p2.quantity as qty2, p2.unit as unit2, p2.price as price2
    FROM products p1
    JOIN products p2 ON p1.product_id < p2.product_id 
                    AND p1.archived = 0 
                    AND p2.archived = 0
                    AND LOWER(TRIM(p1.product_name)) = LOWER(TRIM(p2.product_name))
    ORDER BY p1.product_name ASC
";
$exactRes = $conn->query($exactQuery);
$pairs = [];
$seenKeys = [];

if ($exactRes) {
    while ($r = $exactRes->fetch_assoc()) {
        $id1 = (int)$r['id1'];
        $id2 = (int)$r['id2'];
        $key = min($id1, $id2) . '_' . max($id1, $id2);
        if (isset($seenKeys[$key])) continue;
        $seenKeys[$key] = true;

        $prodA = [
            'product_id'   => $id1,
            'sku'          => 'P-' . str_pad((string)$id1, 4, '0', STR_PAD_LEFT),
            'product_name' => $r['name1'],
            'category'     => $r['cat1'],
            'location'     => $r['loc1'],
            'quantity'     => (int)$r['qty1'],
            'unit'         => $r['unit1'],
            'price'        => (float)$r['price1'],
            'image_url'    => productImageUrl($id1)
        ];
        $prodB = [
            'product_id'   => $id2,
            'sku'          => 'P-' . str_pad((string)$id2, 4, '0', STR_PAD_LEFT),
            'product_name' => $r['name2'],
            'category'     => $r['cat2'],
            'location'     => $r['loc2'],
            'quantity'     => (int)$r['qty2'],
            'unit'         => $r['unit2'],
            'price'        => (float)$r['price2'],
            'image_url'    => productImageUrl($id2)
        ];

        $pairs[] = [
            'is_exact'       => true,
            'similarity_pct' => 100,
            'reason'         => 'Identical Product Name',
            'product_a' => $prodA,
            'product_b' => $prodB,
            'source' => $prodB, // higher ID duplicate to merge & archive
            'target' => $prodA, // lower ID master SKU to keep
            'combined_quantity' => (int)$r['qty1'] + (int)$r['qty2']
        ];
    }
}

echo json_encode([
    'success' => true,
    'total_duplicate_pairs' => count($pairs),
    'pairs' => $pairs
]);
