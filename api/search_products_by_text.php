<?php
/**
 * POST /api/search_products_by_text.php
 *
 * Fuzzy text-based product search for the OCR+DB fusion scanner.
 *
 * Accepts raw OCR text (or a cleaned product name string) and returns ranked
 * product matches from the database using a combination of:
 *   1. Full-name exact / prefix match (highest score)
 *   2. Individual keyword LIKE matching with score accumulation
 *   3. Category + brand token matching
 *
 * Request (POST JSON):
 *   { "text": "Chili Mansi Sardines 155g" }
 *
 * Response:
 *   { "success": true, "matches": [
 *       { "product_id", "product_name", "category", "supplier", "unit", "price",
 *         "quantity", "reorder_point", "image_url", "score" }, ...
 *   ]}
 *
 * Matches are sorted by score DESC, capped at 5.
 * score is in [0.0, 1.0] — 1.0 means exact name match.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// ── Parse request ─────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true);
$text    = trim($body['text'] ?? '');

if ($text === '') {
    echo json_encode(['success' => true, 'matches' => []]);
    exit();
}

// ── Tokenise the OCR text into meaningful keywords ────────────────────────────
// Strip noise characters, normalise whitespace, lowercase
$cleaned  = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text);
$cleaned  = preg_replace('/\s+/', ' ', strtolower(trim($cleaned)));

// Split into tokens; discard stop-words and very short tokens
$stopWords = ['the','a','an','of','in','is','it','to','on','for','and','or','with','per',
              'net','wt','oz','ml','cl','fl','ltr','kg','gm','pc','pk','pcs','packs'];
$tokens    = array_filter(
    explode(' ', $cleaned),
    function ($t) use ($stopWords) {
        return strlen($t) >= 3 && !in_array($t, $stopWords, true);
    }
);
$tokens = array_values(array_unique($tokens));

if (empty($tokens)) {
    echo json_encode(['success' => true, 'matches' => []]);
    exit();
}

// ── Pull candidate products from DB ──────────────────────────────────────────
// Build a LIKE clause for each token so we hit the DB once
$likeClauses = [];
$bindTypes   = '';
$bindValues  = [];

foreach ($tokens as $tok) {
    $likeClauses[] = 'LOWER(p.product_name) LIKE ?';
    $likeClauses[] = 'LOWER(p.category) LIKE ?';
    $likeClauses[] = 'LOWER(p.supplier) LIKE ?';
    $pattern       = '%' . $tok . '%';
    $bindTypes    .= 'sss';
    $bindValues[]  = $pattern;
    $bindValues[]  = $pattern;
    $bindValues[]  = $pattern;
}

$whereClause = implode(' OR ', $likeClauses);

$sql = "
    SELECT p.product_id, p.product_name, p.category, p.supplier, p.unit,
           p.price, p.quantity, p.reorder_point
    FROM   products p
    WHERE  p.archived = 0 AND ({$whereClause})
    ORDER  BY p.product_name ASC
    LIMIT  50
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query preparation failed']);
    exit();
}

// Bind params dynamically
$bindRef = [&$bindTypes];
foreach ($bindValues as &$val) {
    $bindRef[] = &$val;
}
call_user_func_array([$stmt, 'bind_param'], $bindRef);
$stmt->execute();
$result = $stmt->get_result();

// ── Score each candidate ──────────────────────────────────────────────────────
function productImageUrl(int $productId): ?string {
    $baseDir = __DIR__ . '/../uploads/products/';
    $webBase = 'uploads/products/';
    $patterns = [
        (string)$productId,
        'product_' . $productId,
        'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
    ];
    foreach ($patterns as $name) {
        foreach (['jpg','jpeg','png','webp','gif'] as $ext) {
            if (is_file($baseDir . $name . '.' . $ext)) {
                return $webBase . $name . '.' . $ext;
            }
        }
    }
    return null;
}

/**
 * Normalised word-overlap score between two strings.
 * Returns 0.0–1.0.
 */
function wordOverlapScore(string $a, string $b): float {
    $tokA = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', '', strtolower($a))), fn($t) => strlen($t) >= 3);
    $tokB = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', '', strtolower($b))), fn($t) => strlen($t) >= 3);
    if (empty($tokA) || empty($tokB)) return 0.0;
    $intersection = count(array_intersect(array_values($tokA), array_values($tokB)));
    $union        = count(array_unique(array_merge(array_values($tokA), array_values($tokB))));
    return $union > 0 ? round($intersection / $union, 4) : 0.0;
}

$matches = [];
while ($row = $result->fetch_assoc()) {
    $nameLower = strtolower($row['product_name']);

    // 1. Exact full-name match → 1.0
    if ($nameLower === $cleaned) {
        $score = 1.0;
    }
    // 2. OCR text starts with / contains the full product name → high score
    elseif (str_contains($cleaned, $nameLower) || str_contains($nameLower, $cleaned)) {
        $score = 0.90;
    }
    // 3. Word-overlap Jaccard score between OCR text and product name
    else {
        $score = wordOverlapScore($cleaned, $row['product_name']);
        // Bonus if category token appears in OCR text
        if ($row['category'] && str_contains($cleaned, strtolower($row['category']))) {
            $score = min(1.0, $score + 0.10);
        }
        // Bonus if supplier/brand token appears in OCR text
        if ($row['supplier'] && str_contains($cleaned, strtolower($row['supplier']))) {
            $score = min(1.0, $score + 0.08);
        }
    }

    if ($score < 0.15) continue;  // discard very weak matches

    $matches[] = [
        'product_id'   => (int)$row['product_id'],
        'product_name' => $row['product_name'],
        'category'     => $row['category'] ?? '',
        'supplier'     => $row['supplier'] ?? '',
        'unit'         => $row['unit'] ?? '',
        'price'        => (float)($row['price'] ?? 0),
        'quantity'     => (int)$row['quantity'],
        'reorder_point'=> (int)($row['reorder_point'] ?? 10),
        'image_url'    => productImageUrl((int)$row['product_id']),
        'score'        => $score,
    ];
}

$stmt->close();
$conn->close();

// Sort by score DESC, cap at top 5
usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
$matches = array_slice($matches, 0, 5);

echo json_encode(['success' => true, 'matches' => $matches]);
