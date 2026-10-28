<?php
/**
 * src/services/ProductMergeService.php
 *
 * Dedicated business service for Duplicate Product Detection,
 * Real-Time Similarity Evaluation, and Safe Atomic SKU Merging.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/../helpers/product_image_url.php';

class ProductMergeService
{
    /**
     * Normalize a product string for phonetic and token comparison.
     */
    public static function normalize(string $str): string
    {
        $s = preg_replace('/[^a-zA-Z0-9\s]/', ' ', strtolower($str));
        return preg_replace('/\s+/', ' ', trim($s));
    }

    /**
     * Extract weight, volume, or size specifications from a product string.
     * Examples: 25g, 155g, 330ml, 1L, 1.5L, 500ml, 100g, 1kg, 2L, 50ml, etc.
     */
    public static function extractSizeSpecs(string $str): array
    {
        preg_match_all('/\b(\d+(?:\.\d+)?)\s*(g|kg|mg|ml|l|liter|liters|oz|lbs|pcs|pc|pack|sachet|can|bottle|bag|box)\b/i', $str, $matches, PREG_SET_ORDER);
        $specs = [];
        foreach ($matches as $m) {
            $num = (float)$m[1];
            $u = strtolower($m[2]);
            if (in_array($u, ['l', 'liter', 'liters'])) { $num *= 1000; $u = 'ml'; }
            if ($u === 'kg') { $num *= 1000; $u = 'g'; }
            $specs[] = $num . $u;
        }
        preg_match_all('/\b\d+\b/', $str, $nums);
        return [
            'specs' => array_values(array_unique($specs)),
            'nums'  => array_values(array_unique($nums[0] ?? []))
        ];
    }

    /**
     * Compute similarity percentage and match score between two product names,
     * enforcing strict size/unit variant separation so that different sizes
     * (e.g. 25g vs 155g or 25g vs no size) can NEVER match as exact.
     */
    public static function computeSimilarity(string $input, string $target, ?string $inputUnit = null, ?string $targetUnit = null): array
    {
        $s1 = self::normalize($input);
        $s2 = self::normalize($target);

        if ($s1 === '' || $s2 === '') {
            return ['score' => 0.0, 'is_exact' => false];
        }

        $spec1 = self::extractSizeSpecs($input);
        $spec2 = self::extractSizeSpecs($target);
        $sameSpecs = ($spec1['specs'] === $spec2['specs']);
        $sameNums  = ($spec1['nums'] === $spec2['nums']);

        // Strict Size Gate: If one item has a specific size (e.g. 25g) and the other doesn't,
        // or they have different sizes (e.g. 25g vs 155g), they CANNOT be exact matches!
        $hasSizeMismatch = (!$sameSpecs || !$sameNums);

        // Unit normalization & mismatch check (e.g. Sachet vs Bottle, Can vs Pouch)
        $hasUnitMismatch = false;
        if ($inputUnit !== null && $targetUnit !== null) {
            $normalizeUnit = function(?string $u): string {
                if ($u === null) return '';
                $u = strtolower(trim($u));
                $map = [
                    'cans' => 'can',
                    'pcs' => 'piece',
                    'pc' => 'piece',
                    'pieces' => 'piece',
                    'boxes' => 'box',
                    'box' => 'box',
                    'bottles' => 'bottle',
                    'packs' => 'pack',
                    'pouches' => 'pouch',
                    'sachets' => 'sachet',
                    'bags' => 'bag',
                    'tins' => 'tin',
                    'jars' => 'jar',
                    'tubes' => 'tube',
                    'rolls' => 'roll',
                ];
                if (isset($map[$u])) return $map[$u];
                return rtrim($u, 's');
            };
            $u1 = $normalizeUnit($inputUnit);
            $u2 = $normalizeUnit($targetUnit);
            if ($u1 !== '' && $u2 !== '' && $u1 !== $u2) {
                $hasUnitMismatch = true;
            }
        }

        if ($s1 === $s2 && !$hasSizeMismatch) {
            if ($hasUnitMismatch) {
                return ['score' => 0.70, 'is_exact' => false];
            }
            return ['score' => 1.0, 'is_exact' => true];
        }

        similar_text($s1, $s2, $percent);
        $simTextScore = $percent / 100.0;

        $words1 = array_filter(explode(' ', $s1), fn($w) => strlen($w) >= 2);
        $words2 = array_filter(explode(' ', $s2), fn($w) => strlen($w) >= 2);

        if (empty($words1) || empty($words2)) {
            $score = $simTextScore;
            if ($hasSizeMismatch) {
                $score = min(0.72, $score);
            }
            if ($hasUnitMismatch) {
                $score = min(0.70, $score);
            }
            return ['score' => round($score, 3), 'is_exact' => false];
        }

        $intersect = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        $jaccard = $union > 0 ? ($intersect / $union) : 0.0;

        $inputCoverage = $intersect / count($words1);

        $finalScore = max($simTextScore, $jaccard);
        if (count($words1) >= 2 && $inputCoverage >= 0.8 && !$hasSizeMismatch && !$hasUnitMismatch) {
            $finalScore = max($finalScore, 0.80 + ($inputCoverage * 0.18));
        }

        if ((str_contains($s2, $s1) || str_contains($s1, $s2)) && !$hasSizeMismatch && !$hasUnitMismatch) {
            $finalScore = max($finalScore, 0.85);
        }

        // Penalty for size/numeric mismatch: cap score at 0.72 so it is NEVER labeled exact or >72%
        if ($hasSizeMismatch) {
            $finalScore = min($finalScore, 0.72);
        }

        // Penalty for packaging/unit mismatch: cap score at 0.70
        if ($hasUnitMismatch) {
            $finalScore = min($finalScore, 0.70);
        }

        $isExact = ($s1 === $s2 && !$hasSizeMismatch && !$hasUnitMismatch);

        return [
            'score'    => round(min(1.0, $finalScore), 3),
            'is_exact' => $isExact
        ];
    }

    /**
     * Check if a product name matches any existing products in active catalog or unmerged archived items.
     * Compares name, size specifications, and unit to guarantee honest matching.
     * Also fetches active batch lists for candidate products.
     */
    public static function checkDuplicate(mysqli $conn, string $name, int $excludeId = 0, ?string $unit = null): array
    {
        $cleanInput = self::normalize($name);
        if ($cleanInput === '') {
            return ['has_duplicate' => false, 'matches' => []];
        }

        $inputSpecs = self::extractSizeSpecs($name);

        // 1. Check potential exact active match or unmerged archived match
        $exactStmt = $conn->prepare("
            SELECT product_id, product_name, category, location, quantity, unit, price, reorder_point, archived
            FROM products
            WHERE (archived = 0 OR (archived = 1 AND product_name NOT LIKE '%[MERGED%'))
              AND product_id != ? 
              AND LOWER(TRIM(product_name)) = LOWER(?)
            ORDER BY archived ASC, product_id ASC
            LIMIT 1
        ");
        $exactStmt->bind_param("is", $excludeId, $name);
        $exactStmt->execute();
        $exactRow = $exactStmt->get_result()->fetch_assoc();
        $exactStmt->close();

        $tokens = array_filter(explode(' ', $cleanInput), fn($t) => strlen($t) >= 3);
        $candidates = [];

        if ($exactRow) {
            $candSpecs = self::extractSizeSpecs($exactRow['product_name']);
            $exactMatches = ($inputSpecs['specs'] === $candSpecs['specs'] && $inputSpecs['nums'] === $candSpecs['nums']);
            if ($exactMatches) {
                $exactRow['score'] = 1.0;
                $exactRow['is_exact'] = true;
                $exactRow['is_archived'] = ((int)($exactRow['archived'] ?? 0) === 1);
                $candidates[] = $exactRow;
            } else {
                // Size differs even if base string was similar
                $exactRow['score'] = 0.72;
                $exactRow['is_exact'] = false;
                $exactRow['is_archived'] = ((int)($exactRow['archived'] ?? 0) === 1);
                $candidates[] = $exactRow;
            }
        }

        if (!empty($tokens)) {
            $likeClauses = [];
            $types = 'i';
            $binds = [$excludeId];

            foreach (array_slice($tokens, 0, 5) as $tok) {
                $likeClauses[] = "LOWER(product_name) LIKE ?";
                $types .= 's';
                $binds[] = '%' . $tok . '%';
            }

            $where = implode(' OR ', $likeClauses);
            $candSql = "
                SELECT product_id, product_name, category, location, quantity, unit, price, reorder_point, archived
                FROM products
                WHERE archived = 0 AND product_id != ? AND ({$where})
                LIMIT 40
            ";

            $stmt = $conn->prepare($candSql);
            if ($stmt) {
                $stmt->bind_param($types, ...$binds);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    if ($exactRow && (int)$row['product_id'] === (int)$exactRow['product_id']) {
                        continue;
                    }
                    $sim = self::computeSimilarity($name, $row['product_name'], $unit, $row['unit'] ?? null);
                    if ($sim['score'] >= 0.65) {
                        $row['score'] = $sim['score'];
                        $row['is_exact'] = $sim['is_exact'];
                        $row['is_archived'] = ((int)($row['archived'] ?? 0) === 1);
                        $candidates[] = $row;
                    }
                }
                $stmt->close();
            }
        }

        $matches = [];
        foreach ($candidates as $c) {
            $pid = (int)$c['product_id'];
            $isArchived = ((int)($c['archived'] ?? 0) === 1);
            
            $loc = trim($c['location'] ?? '');
            $shelf = preg_replace('/^Shelf\s+/i', '', $loc);
            if ($shelf === '') {
                $shelf = $loc !== '' ? $loc : 'Unassigned';
            }

            // Fetch active batches from product_batches (with inventory_batches fallback)
            $batches = [];
            $totalBatchQty = 0;
            $batchStmt = $conn->prepare("
                SELECT batch_id, batch_number, qty, expiry_date, received_at
                FROM product_batches
                WHERE product_id = ? AND qty > 0
                ORDER BY (expiry_date >= CURDATE()) DESC, expiry_date ASC, batch_id ASC
            ");
            if ($batchStmt) {
                $batchStmt->bind_param("i", $pid);
                $batchStmt->execute();
                $bRes = $batchStmt->get_result();
                $bIdx = 1;
                while ($bRow = $bRes->fetch_assoc()) {
                    $bQty = (int)$bRow['qty'];
                    $totalBatchQty += $bQty;
                    $expStr = !empty($bRow['expiry_date']) ? substr($bRow['expiry_date'], 0, 10) : 'No Expiry';
                    $batches[] = [
                        'batch_id'         => (int)$bRow['batch_id'],
                        'batch_number'     => $bRow['batch_number'] ?: ('Batch ' . $bIdx),
                        'qty'              => $bQty,
                        'expiry_date'      => $bRow['expiry_date'] ?: '',
                        'expiry_formatted' => $expStr,
                        'received_at'      => $bRow['received_at'] ?? ''
                    ];
                    $bIdx++;
                }
                $batchStmt->close();
            }

            // Fallback to inventory_batches if product_batches had 0 rows
            if (empty($batches)) {
                $ibStmt = $conn->prepare("
                    SELECT batch_id, batch_number, quantity, expiry_date, date_received
                    FROM inventory_batches
                    WHERE product_id = ? AND quantity > 0
                    ORDER BY (expiry_date >= CURDATE()) DESC, expiry_date ASC, batch_id ASC
                ");
                if ($ibStmt) {
                    $ibStmt->bind_param("i", $pid);
                    $ibStmt->execute();
                    $ibRes = $ibStmt->get_result();
                    $bIdx = 1;
                    while ($ibRow = $ibRes->fetch_assoc()) {
                        $bQty = (int)$ibRow['quantity'];
                        $totalBatchQty += $bQty;
                        $expStr = !empty($ibRow['expiry_date']) ? substr($ibRow['expiry_date'], 0, 10) : 'No Expiry';
                        $batches[] = [
                            'batch_id'         => (int)$ibRow['batch_id'],
                            'batch_number'     => $ibRow['batch_number'] ?: ('Batch ' . $bIdx),
                            'qty'              => $bQty,
                            'expiry_date'      => $ibRow['expiry_date'] ?: '',
                            'expiry_formatted' => $expStr,
                            'received_at'      => $ibRow['date_received'] ?? ''
                        ];
                        $bIdx++;
                    }
                    $ibStmt->close();
                }
            }

            $nearestExpiry = !empty($batches) ? $batches[0]['expiry_formatted'] : 'N/A';
            $computedStock = $totalBatchQty > 0 ? $totalBatchQty : (int)$c['quantity'];

            $matches[] = [
                'product_id'      => $pid,
                'sku'             => 'P-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT),
                'product_name'    => $c['product_name'],
                'category'        => $c['category'] ?? '',
                'location'        => $loc,
                'shelf'           => $shelf,
                'nearest_expiry'  => $nearestExpiry,
                'quantity'        => $computedStock,
                'unit'            => $c['unit'] ?? 'pcs',
                'price'           => (float)($c['price'] ?? 0),
                'score'           => (float)$c['score'],
                'similarity_pct'  => round($c['score'] * 100),
                'is_exact'        => (bool)$c['is_exact'],
                'is_archived'     => $isArchived,
                'existing_batches'=> $batches,
                'batch_count'     => count($batches),
                'image_url'       => productImageUrl($pid),
            ];
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        $matches = array_slice($matches, 0, 4);

        $topMatch = !empty($matches) ? $matches[0] : null;
        $hasDuplicate = $topMatch !== null && ($topMatch['is_exact'] || $topMatch['score'] >= 0.70);

        return [
            'has_duplicate' => $hasDuplicate,
            'is_exact'      => $topMatch ? $topMatch['is_exact'] : false,
            'is_archived'   => $topMatch ? (!empty($topMatch['is_archived'])) : false,
            'similarity_pct'=> $topMatch ? $topMatch['similarity_pct'] : 0,
            'top_match'     => $hasDuplicate ? $topMatch : null,
            'matches'       => $matches
        ];
    }

    /**
     * Central product registration service with duplicate prevention,
     * exact-match batch routing, near-match server guard, and archived SKU restoration.
     *
     * @param mysqli $conn
     * @param array  $data Product form fields
     * @param string $username Performing user
     * @return array Result descriptor with status ('created' | 'restocked' | 'exact_match_zero_qty' | 'near_match_blocked')
     */
    public static function processProductRegistration(mysqli $conn, array $data, string $username): array
    {
        $product_name        = trim($data['product_name'] ?? '');
        $category            = trim($data['category'] ?? '');
        $categoryId          = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $supplier            = trim($data['supplier'] ?? '');
        $location            = trim($data['location'] ?? '');
        $openingQty          = max(0, (int)($data['quantity'] ?? 0));
        $reorder_point       = max(0, (int)($data['reorder_point'] ?? 10));
        $unit                = trim($data['unit'] ?? 'pieces');
        $price               = max(0.0, (float)($data['price'] ?? 0.0));
        $shelf_life          = !empty($data['shelf_life']) ? (int)$data['shelf_life'] : null;
        $expiry_date         = !empty($data['expiry_date']) ? trim($data['expiry_date']) : null;
        $batch_number        = !empty($data['batch_number']) ? trim($data['batch_number']) : null;
        $product_image_b64   = trim($data['product_image_b64'] ?? '');
        $confirm_new_variant = !empty($data['confirm_new_variant']);

        if ($product_name === '') {
            throw new InvalidArgumentException('Product name is required.');
        }

        // Intelligent Expiry & Shelf Life Sync
        if ($openingQty > 0 && empty($expiry_date) && !empty($shelf_life) && $shelf_life > 0) {
            $expiry_date = date('Y-m-d', strtotime("+{$shelf_life} days"));
        }
        if (empty($shelf_life) && !empty($expiry_date)) {
            $daysDiff = (int)round((strtotime($expiry_date) - strtotime(date('Y-m-d'))) / 86400);
            if ($daysDiff > 0) {
                $shelf_life = $daysDiff;
            }
        }
        if ($openingQty > 0 && empty($batch_number)) {
            $batch_number = 'BATCH-' . date('Ymd') . '-INIT';
        }

        // 1. Run Duplicate Check
        $dupCheck = self::checkDuplicate($conn, $product_name, 0, $unit);

        // 2. Exact Match Branch (is_exact = true) and confirm_new_variant is NOT set
        if ($dupCheck['has_duplicate'] && $dupCheck['is_exact'] && !$confirm_new_variant) {
            $top = $dupCheck['top_match'];
            $matchedId   = (int)$top['product_id'];
            $sku         = $top['sku'];
            $matchedUnit = $top['unit'] ?? ($unit !== '' ? $unit : 'units');
            $wasArchived = !empty($top['is_archived']);

            if ($openingQty > 0) {
                // If expiry date is missing on restock, require it or default safely
                if (empty($expiry_date)) {
                    throw new InvalidArgumentException("Expiry date is required when restocking existing product {$sku}.");
                }

                // If the exact match is archived (and not merged), restore it
                if ($wasArchived) {
                    $uStmt = $conn->prepare("UPDATE products SET archived = 0 WHERE product_id = ?");
                    if ($uStmt) {
                        $uStmt->bind_param("i", $matchedId);
                        $uStmt->execute();
                        $uStmt->close();
                    }
                }

                $userId = StockMovementService::getUserId($conn, $username);

                // Fetch total current stock before restock
                $totStmt = $conn->prepare("SELECT COALESCE(SUM(qty), 0) AS total FROM product_batches WHERE product_id = ? AND qty > 0");
                $totStmt->bind_param("i", $matchedId);
                $totStmt->execute();
                $totRes = $totStmt->get_result()->fetch_assoc();
                $stockBefore = (int)($totRes['total'] ?? 0);
                $totStmt->close();

                // Merge Rule: Check if a batch with the SAME expiry date exists
                $bCheckStmt = $conn->prepare("
                    SELECT batch_id, qty, batch_number, expiry_date 
                    FROM product_batches 
                    WHERE product_id = ? AND expiry_date = ? AND qty > 0
                    ORDER BY batch_id ASC 
                    LIMIT 1
                ");
                $bCheckStmt->bind_param("is", $matchedId, $expiry_date);
                $bCheckStmt->execute();
                $existingBatch = $bCheckStmt->get_result()->fetch_assoc();
                $bCheckStmt->close();

                $isNewBatch = false;
                $batchId = 0;
                $batchBeforeQty = 0;
                $batchAfterQty = 0;
                $batchNum = '';
                $expFormatted = date('Y-m-d', strtotime($expiry_date));

                if ($existingBatch) {
                    // Batch with SAME expiry exists -> add qty to that batch
                    $batchId = (int)$existingBatch['batch_id'];
                    $batchBeforeQty = (int)$existingBatch['qty'];
                    $batchAfterQty = $batchBeforeQty + $openingQty;
                    $batchNum = $existingBatch['batch_number'] ?: ('Batch ' . $batchId);

                    $updPb = $conn->prepare("UPDATE product_batches SET qty = ?, received_at = CURRENT_TIMESTAMP WHERE batch_id = ?");
                    $updPb->bind_param("ii", $batchAfterQty, $batchId);
                    $updPb->execute();
                    $updPb->close();

                    // Sync inventory_batches
                    $updIb = $conn->prepare("UPDATE inventory_batches SET quantity = quantity + ?, initial_quantity = initial_quantity + ? WHERE batch_id = ?");
                    if ($updIb) {
                        $updIb->bind_param("iii", $openingQty, $openingQty, $batchId);
                        $updIb->execute();
                        $updIb->close();
                    }
                } else {
                    // NO batch with that expiry -> create NEW batch row
                    $isNewBatch = true;
                    $batchBeforeQty = 0;
                    $batchAfterQty = $openingQty;
                    if (empty($batch_number)) {
                        $bCountStmt = $conn->prepare("SELECT COUNT(*) AS c FROM product_batches WHERE product_id = ?");
                        $bCountStmt->bind_param("i", $matchedId);
                        $bCountStmt->execute();
                        $bCount = (int)($bCountStmt->get_result()->fetch_assoc()['c'] ?? 0) + 1;
                        $bCountStmt->close();
                        $batchNum = 'BATCH-' . date('Ymd') . '-B' . $bCount;
                    } else {
                        $batchNum = $batch_number;
                    }

                    $insPb = $conn->prepare("
                        INSERT INTO product_batches (product_id, qty, expiry_date, received_at, batch_number)
                        VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
                    ");
                    $insPb->bind_param("iiss", $matchedId, $openingQty, $expiry_date, $batchNum);
                    $insPb->execute();
                    $batchId = (int)$conn->insert_id;
                    $insPb->close();

                    // Sync inventory_batches
                    $insIb = $conn->prepare("
                        INSERT INTO inventory_batches (batch_id, product_id, batch_number, quantity, initial_quantity, expiry_date, date_received)
                        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), initial_quantity = VALUES(initial_quantity)
                    ");
                    if ($insIb) {
                        $insIb->bind_param("iisiis", $batchId, $matchedId, $batchNum, $openingQty, $openingQty, $expiry_date);
                        $insIb->execute();
                        $insIb->close();
                    }
                }

                // Compute total product stock strictly with SUM(batch.qty)
                $sumStmt = $conn->prepare("SELECT COALESCE(SUM(qty), 0) AS new_total FROM product_batches WHERE product_id = ? AND qty > 0");
                $sumStmt->bind_param("i", $matchedId);
                $sumStmt->execute();
                $newStock = (int)($sumStmt->get_result()->fetch_assoc()['new_total'] ?? ($stockBefore + $openingQty));
                $sumStmt->close();

                // Keep products.quantity in sync with derived sum
                $updP = $conn->prepare("UPDATE products SET quantity = ? WHERE product_id = ?");
                $updP->bind_param("ii", $newStock, $matchedId);
                $updP->execute();
                $updP->close();

                // Log Restock in transactions table
                $transSource = 'Restock via Add Product';
                $txRemarks = $isNewBatch
                    ? "Restock via Add Product: Created new batch #{$batchId} ({$batchNum}, Qty: {$openingQty}, Exp: {$expFormatted})"
                    : "Restock via Add Product: Merged into batch #{$batchId} ({$batchNum}, Qty: {$batchBeforeQty} → {$batchAfterQty}, Exp: {$expFormatted})";

                $txStmt = $conn->prepare("
                    INSERT INTO transactions 
                    (product_id, user_id, transaction_type, quantity, previous_stock, new_stock, remarks, transaction_source, transaction_date)
                    VALUES (?, ?, 'stock_in', ?, ?, ?, ?, ?, NOW())
                ");
                $txStmt->bind_param("iiiiiss", $matchedId, $userId, $openingQty, $stockBefore, $newStock, $txRemarks, $transSource);
                $txStmt->execute();
                $txStmt->close();

                // Log in system logs table: product, batch ID, expiry, qty before → after, source, user, timestamp
                $logAction = sprintf(
                    'Restock via Add Product for %s (%s): Batch #%d (%s, Exp: %s) qty %d → %d. Product total: %d → %d %s.',
                    $sku,
                    $top['product_name'],
                    $batchId,
                    $batchNum,
                    $expFormatted,
                    $batchBeforeQty,
                    $batchAfterQty,
                    $stockBefore,
                    $newStock,
                    $matchedUnit
                );
                $logStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($logStmt) {
                    $logStmt->bind_param("is", $userId, $logAction);
                    $logStmt->execute();
                    $logStmt->close();
                }

                // Invalidate analytics caches
                try {
                    require_once __DIR__ . '/../../services/insights-service/InsightsEngine.php';
                    InsightsEngine::invalidateCache($conn);
                } catch (Throwable $e) {}

                $expHuman = date('M Y', strtotime($expiry_date));
                if ($isNewBatch) {
                    $message = "A new batch ({$openingQty} {$matchedUnit}, exp {$expHuman}) was added to {$sku}. Existing batches are unchanged. Total stock is now {$newStock} {$matchedUnit}.";
                } else {
                    $message = "Merged {$openingQty} {$matchedUnit} into existing {$batchNum} ({$batchBeforeQty} → {$batchAfterQty} {$matchedUnit}, exp {$expHuman}) of {$sku}. Total stock is now {$newStock} {$matchedUnit}.";
                }

                return [
                    'status'        => 'restocked',
                    'product_id'    => $matchedId,
                    'sku'           => $sku,
                    'batch_id'      => $batchId,
                    'is_new_batch'  => $isNewBatch,
                    'is_archived'   => $wasArchived,
                    'added_qty'     => $openingQty,
                    'current_stock' => $newStock,
                    'unit'          => $matchedUnit,
                    'message'       => $message,
                    'top_match'     => $top
                ];
            }

            // Opening quantity is 0 or empty -> do NOT insert, do NOT receive
            $msg = $wasArchived
                ? "{$sku} already exists (archived, current stock: {$top['quantity']}). Enter a quantity and expiry date to restore and add a new batch."
                : "{$sku} already exists (current stock: {$top['quantity']}). Enter a quantity and expiry date to add a new batch.";

            return [
                'status'        => 'exact_match_zero_qty',
                'product_id'    => $matchedId,
                'sku'           => $sku,
                'is_archived'   => $wasArchived,
                'current_stock' => (int)$top['quantity'],
                'unit'          => $matchedUnit,
                'message'       => $msg,
                'top_match'     => $top
            ];
        }

        // 3. Near Match Branch (score >= 0.70, not exact) and confirm_new_variant is NOT set
        if ($dupCheck['has_duplicate'] && !$dupCheck['is_exact'] && !$confirm_new_variant) {
            $top = $dupCheck['top_match'];
            $loc = !empty($top['location']) ? $top['location'] : 'Unassigned';
            return [
                'status'    => 'near_match_blocked',
                'top_match' => $top,
                'matches'   => $dupCheck['matches'],
                'message'   => "⚠️ Similar item already in stock: \"{$top['product_name']}\" ({$top['sku']} · {$top['quantity']} {$top['unit']} in stock · {$loc}). Please restock the existing SKU or confirm 'Create as New Variant'."
            ];
        }

        // 4. Safe Insert (Brand New Product OR Confirmed New Variant)
        $stmt = $conn->prepare("INSERT INTO products (product_name, category, category_id, supplier, location, quantity, reorder_point, unit, price, shelf_life) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
        $stmt->bind_param("ssissisdi", $product_name, $category, $categoryId, $supplier, $location, $reorder_point, $unit, $price, $shelf_life);
        if (!$stmt->execute()) {
            $stmt->close();
            if ($conn->errno === 1062) {
                throw new Exception('A product with this information already exists.');
            }
            throw new Exception('Could not save product. Please try again.');
        }
        $newProductId = (int)$conn->insert_id;
        $stmt->close();

        // Save image if present
        if ($product_image_b64 !== '') {
            saveProductImageFromBase64($newProductId, $product_image_b64);
        }

        // Add opening stock as first batch if > 0
        if ($openingQty > 0) {
            $userId = StockMovementService::getUserId($conn, $username);
            $batchNum = !empty($batch_number) ? $batch_number : ('BATCH-' . date('Ymd') . '-INIT');

            $insPb = $conn->prepare("
                INSERT INTO product_batches (product_id, qty, expiry_date, received_at, batch_number)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
            ");
            $insPb->bind_param("iiss", $newProductId, $openingQty, $expiry_date, $batchNum);
            $insPb->execute();
            $batchId = (int)$conn->insert_id;
            $insPb->close();

            // Sync inventory_batches
            $insIb = $conn->prepare("
                INSERT INTO inventory_batches (batch_id, product_id, batch_number, quantity, initial_quantity, expiry_date, date_received)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            if ($insIb) {
                $insIb->bind_param("iisiis", $batchId, $newProductId, $batchNum, $openingQty, $openingQty, $expiry_date);
                $insIb->execute();
                $insIb->close();
            }

            // Sync products.quantity strictly from SUM(batch.qty)
            $conn->query("UPDATE products SET quantity = {$openingQty} WHERE product_id = {$newProductId}");

            // Record transaction
            $txRemarks = "Opening stock on product create (Batch #{$batchId}, {$batchNum})";
            $txStmt = $conn->prepare("
                INSERT INTO transactions 
                (product_id, user_id, transaction_type, quantity, previous_stock, new_stock, remarks, transaction_source, transaction_date)
                VALUES (?, ?, 'stock_in', ?, 0, ?, ?, 'opening_stock', NOW())
            ");
            $txStmt->bind_param("iiiis", $newProductId, $userId, $openingQty, $openingQty, $txRemarks);
            $txStmt->execute();
            $txStmt->close();

            $newSku = 'P-' . str_pad((string)$newProductId, 4, '0', STR_PAD_LEFT);
            $logAction = "Created product {$newSku} ({$product_name}) with initial batch #{$batchId} (Qty: {$openingQty}, Exp: " . ($expiry_date ?: 'None') . ") by {$username}";
            $conn->query("INSERT INTO logs (user_id, action) VALUES ({$userId}, " . json_encode($logAction) . ")");
        } else {
            $newSku = 'P-' . str_pad((string)$newProductId, 4, '0', STR_PAD_LEFT);
        }

        return [
            'status'     => 'created',
            'product_id' => $newProductId,
            'sku'        => $newSku,
            'message'    => 'Product added successfully.'
        ];
    }

    /**
     * Safely merge duplicate source SKU into target SKU.
     * Consolidates stock, moves transactions/batches, and archives source SKU.
     */
    public static function mergeSkus(mysqli $conn, int $sourceId, int $targetId, string $username): array
    {
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new Exception('Invalid source and target product IDs for merge.');
        }

        // 1. Fetch Source
        $sStmt = $conn->prepare("SELECT product_id, product_name, category, location, quantity, unit, price, archived FROM products WHERE product_id = ?");
        $sStmt->bind_param("i", $sourceId);
        $sStmt->execute();
        $source = $sStmt->get_result()->fetch_assoc();
        $sStmt->close();

        if (!$source) {
            throw new Exception("Source product (ID: {$sourceId}) not found.");
        }

        // 2. Fetch Target
        $tStmt = $conn->prepare("SELECT product_id, product_name, category, location, quantity, unit, price, archived FROM products WHERE product_id = ?");
        $tStmt->bind_param("i", $targetId);
        $tStmt->execute();
        $target = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();

        if (!$target) {
            throw new Exception("Target product (ID: {$targetId}) not found.");
        }

        $sourceSku = 'P-' . str_pad((string)$sourceId, 4, '0', STR_PAD_LEFT);
        $targetSku = 'P-' . str_pad((string)$targetId, 4, '0', STR_PAD_LEFT);

        $sourceQty = (int)$source['quantity'];
        $targetOldQty = (int)$target['quantity'];
        $combinedQty = $targetOldQty + $sourceQty;

        // Fetch User ID
        $userId = null;
        $uStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("s", $username);
            $uStmt->execute();
            $uRes = $uStmt->get_result()->fetch_assoc();
            if ($uRes) $userId = (int)$uRes['user_id'];
            $uStmt->close();
        }
        if (!$userId) {
            $firstUser = $conn->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1")->fetch_assoc();
            $userId = $firstUser ? (int)$firstUser['user_id'] : 9;
        }

        // Atomic Transaction
        $conn->begin_transaction();

        try {
            // Reassign transactions
            $txStmt = $conn->prepare("UPDATE transactions SET product_id = ? WHERE product_id = ?");
            $txStmt->bind_param("ii", $targetId, $sourceId);
            $txStmt->execute();
            $reassignedTxCount = $txStmt->affected_rows;
            $txStmt->close();

            // Reassign inventory batches
            $bStmt = $conn->prepare("UPDATE inventory_batches SET product_id = ? WHERE product_id = ?");
            $bStmt->bind_param("ii", $targetId, $sourceId);
            $bStmt->execute();
            $reassignedBatches = $bStmt->affected_rows;
            $bStmt->close();

            // Reassign product_batches
            $pbStmt = $conn->prepare("UPDATE product_batches SET product_id = ? WHERE product_id = ?");
            if ($pbStmt) {
                $pbStmt->bind_param("ii", $targetId, $sourceId);
                $pbStmt->execute();
                $pbStmt->close();
            }

            // Reassign scan logs
            $scanStmt = $conn->prepare("UPDATE tm_scan_log SET product_id = ? WHERE product_id = ?");
            if ($scanStmt) {
                $scanStmt->bind_param("ii", $targetId, $sourceId);
                $scanStmt->execute();
                $scanStmt->close();
            }

            // Update Target Stock
            $updTarget = $conn->prepare("UPDATE products SET quantity = ? WHERE product_id = ?");
            $updTarget->bind_param("ii", $combinedQty, $targetId);
            $updTarget->execute();
            $updTarget->close();

            // Zero out and archive source SKU
            $mergedTag = " [MERGED into {$targetSku}]";
            $updSource = $conn->prepare("
                UPDATE products 
                SET quantity = 0, 
                    archived = 1, 
                    product_name = CASE 
                        WHEN product_name LIKE '%[MERGED%' THEN product_name 
                        ELSE CONCAT(SUBSTRING(product_name, 1, 80), ?) 
                    END
                WHERE product_id = ?
            ");
            $updSource->bind_param("si", $mergedTag, $sourceId);
            $updSource->execute();
            $updSource->close();

            // Audit Log in transactions
            $auditRemark = "Merged duplicate SKU {$sourceSku} into {$targetSku} (+{$sourceQty} {$source['unit']} transferred)";
            $auditType = 'stock_in';
            $auditSource = 'manual';
            $auditStmt = $conn->prepare("
                INSERT INTO transactions (product_id, user_id, transaction_type, quantity, previous_stock, new_stock, transaction_date, remarks, transaction_source)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $auditStmt->bind_param("iisiiiss", $targetId, $userId, $auditType, $sourceQty, $targetOldQty, $combinedQty, $auditRemark, $auditSource);
            $auditStmt->execute();
            $auditStmt->close();

            $conn->commit();

            // Refresh ML cache if available
            try {
                if (class_exists('ApiClient')) {
                    ApiClient::getInsightsSummary(true);
                }
            } catch (Throwable $ignore) {}

            return [
                'success' => true,
                'message' => "Successfully merged {$sourceSku} ({$source['product_name']}) into {$targetSku} ({$target['product_name']}). Combined stock is now {$combinedQty} {$target['unit']}.",
                'source_sku' => $sourceSku,
                'target_sku' => $targetSku,
                'transferred_stock' => $sourceQty,
                'previous_target_stock' => $targetOldQty,
                'new_combined_stock' => $combinedQty,
                'unit' => $target['unit'],
                'reassigned_transactions' => $reassignedTxCount,
                'reassigned_batches' => $reassignedBatches
            ];

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
