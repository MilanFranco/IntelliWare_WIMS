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
     * Compute similarity percentage and match score between two product names.
     */
    public static function computeSimilarity(string $input, string $target): array
    {
        $s1 = self::normalize($input);
        $s2 = self::normalize($target);

        if ($s1 === '' || $s2 === '') {
            return ['score' => 0.0, 'is_exact' => false];
        }

        if ($s1 === $s2) {
            return ['score' => 1.0, 'is_exact' => true];
        }

        similar_text($s1, $s2, $percent);
        $simTextScore = $percent / 100.0;

        $words1 = array_filter(explode(' ', $s1), fn($w) => strlen($w) >= 2);
        $words2 = array_filter(explode(' ', $s2), fn($w) => strlen($w) >= 2);

        if (empty($words1) || empty($words2)) {
            return ['score' => round($simTextScore, 3), 'is_exact' => false];
        }

        $intersect = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        $jaccard = $union > 0 ? ($intersect / $union) : 0.0;

        $inputCoverage = $intersect / count($words1);

        $finalScore = max($simTextScore, $jaccard);
        if (count($words1) >= 2 && $inputCoverage >= 0.8) {
            $finalScore = max($finalScore, 0.80 + ($inputCoverage * 0.18));
        }

        if (str_contains($s2, $s1) || str_contains($s1, $s2)) {
            $finalScore = max($finalScore, 0.85);
        }

        // Check numeric package/size conflicts (e.g. 500ml vs 330ml, 1L vs 2L)
        preg_match_all('/\d+/', $s1, $dig1);
        preg_match_all('/\d+/', $s2, $dig2);
        $d1 = $dig1[0] ?? [];
        $d2 = $dig2[0] ?? [];
        if (!empty($d1) && !empty($d2) && $d1 !== $d2) {
            $finalScore = min($finalScore, 0.70);
        }

        return [
            'score' => round(min(1.0, $finalScore), 3),
            'is_exact' => ($s1 === $s2)
        ];
    }

    /**
     * Check if a product name matches any existing products in active catalog or unmerged archived items.
     */
    public static function checkDuplicate(mysqli $conn, string $name, int $excludeId = 0): array
    {
        $cleanInput = self::normalize($name);
        if ($cleanInput === '') {
            return ['has_duplicate' => false, 'matches' => []];
        }

        // 1. Check exact active match or unmerged archived match
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
            $exactRow['score'] = 1.0;
            $exactRow['is_exact'] = true;
            $exactRow['is_archived'] = ((int)($exactRow['archived'] ?? 0) === 1);
            $candidates[] = $exactRow;
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
                    $sim = self::computeSimilarity($name, $row['product_name']);
                    if ($sim['score'] >= 0.70) {
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

            $nearestExpiry = 'N/A';
            $expStmt = $conn->prepare("
                SELECT expiry_date 
                FROM inventory_batches 
                WHERE product_id = ? 
                  AND expiry_date IS NOT NULL 
                  AND expiry_date != '' 
                  AND expiry_date != '0000-00-00'
                ORDER BY (expiry_date >= CURDATE()) DESC, expiry_date ASC 
                LIMIT 1
            ");
            if ($expStmt) {
                $expStmt->bind_param("i", $pid);
                $expStmt->execute();
                $expRes = $expStmt->get_result()->fetch_assoc();
                if ($expRes && !empty($expRes['expiry_date'])) {
                    $nearestExpiry = substr($expRes['expiry_date'], 0, 10);
                }
                $expStmt->close();
            }

            $matches[] = [
                'product_id'    => $pid,
                'sku'           => 'P-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT),
                'product_name'  => $c['product_name'],
                'category'      => $c['category'] ?? '',
                'location'      => $loc,
                'shelf'         => $shelf,
                'nearest_expiry'=> $nearestExpiry,
                'quantity'      => (int)$c['quantity'],
                'unit'          => $c['unit'] ?? 'pcs',
                'price'         => (float)($c['price'] ?? 0),
                'score'         => (float)$c['score'],
                'similarity_pct'=> round($c['score'] * 100),
                'is_exact'      => (bool)$c['is_exact'],
                'is_archived'   => $isArchived,
                'image_url'     => productImageUrl($pid),
            ];
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        $matches = array_slice($matches, 0, 4);

        $topMatch = !empty($matches) ? $matches[0] : null;
        $hasDuplicate = $topMatch !== null && ($topMatch['is_exact'] || $topMatch['score'] >= 0.78);

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
        $dupCheck = self::checkDuplicate($conn, $product_name);

        // 2. Exact Match Branch (is_exact = true) and confirm_new_variant is NOT set
        if ($dupCheck['has_duplicate'] && $dupCheck['is_exact'] && !$confirm_new_variant) {
            $top = $dupCheck['top_match'];
            $matchedId   = (int)$top['product_id'];
            $sku         = $top['sku'];
            $matchedUnit = $top['unit'] ?? ($unit !== '' ? $unit : 'units');
            $wasArchived = !empty($top['is_archived']);

            if ($openingQty > 0) {
                // If the exact match is archived (and not merged), restore it
                if ($wasArchived) {
                    $uStmt = $conn->prepare("UPDATE products SET archived = 0 WHERE product_id = ?");
                    if ($uStmt) {
                        $uStmt->bind_param("i", $matchedId);
                        $uStmt->execute();
                        $uStmt->close();
                    }
                }

                $metadata = ['source' => 'add_product_exact_match'];
                if (!empty($expiry_date)) {
                    $metadata['expiry_date'] = $expiry_date;
                }
                if (!empty($batch_number)) {
                    $metadata['batch_number'] = $batch_number;
                }

                ApiClient::receiveInventory(
                    $matchedId,
                    $openingQty,
                    $username,
                    'Restocked via Add Product (duplicate prevented)',
                    $metadata
                );

                // Fetch current refreshed quantity
                $qStmt = $conn->prepare("SELECT quantity FROM products WHERE product_id = ?");
                $qStmt->bind_param("i", $matchedId);
                $qStmt->execute();
                $qRow = $qStmt->get_result()->fetch_assoc();
                $qStmt->close();
                $currentStock = (int)($qRow['quantity'] ?? ($top['quantity'] + $openingQty));

                $message = $wasArchived
                    ? "Restored archived product {$sku}. Added {$openingQty} {$matchedUnit} as a new batch."
                    : "Already exists as {$sku}. Added {$openingQty} {$matchedUnit} as a new batch.";

                return [
                    'status'        => 'restocked',
                    'product_id'    => $matchedId,
                    'sku'           => $sku,
                    'is_archived'   => $wasArchived,
                    'added_qty'     => $openingQty,
                    'current_stock' => $currentStock,
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

        // 3. Near Match Branch (score >= 0.78, not exact) and confirm_new_variant is NOT set
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

        // Add opening stock if > 0
        if ($openingQty > 0) {
            $metadata = ['source' => 'opening_stock'];
            if (!empty($expiry_date)) {
                $metadata['expiry_date'] = $expiry_date;
            }
            if (!empty($batch_number)) {
                $metadata['batch_number'] = $batch_number;
            }
            ApiClient::receiveInventory(
                $newProductId,
                $openingQty,
                $username,
                'Opening stock on product create',
                $metadata
            );
        }

        $newSku = 'P-' . str_pad((string)$newProductId, 4, '0', STR_PAD_LEFT);
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
