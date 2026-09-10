<?php
/**
 * services/insights-service/InsightsEngine.php
 * Pre-computes, caches, and serves analytics for the Insights Microservice.
 */

require_once __DIR__ . '/config/database.php';

class InsightsEngine
{
    private mysqli $conn;

    public function __construct(?mysqli $conn = null)
    {
        $this->conn = $conn ?: getInsightsDbConnection();
    }

    /**
     * Get summary metrics (KPIs, categories, sales trends, suppliers).
     * Reads directly from pre-calculated cache (<15ms).
     */
    public function getSummary(bool $autoGenerateIfEmpty = true): array
    {
        $stmt = $this->conn->prepare("SELECT summary_data, computed_at FROM insight_summary WHERE summary_key = 'main_dashboard_summary' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['summary_data'])) {
            $data = json_decode($row['summary_data'], true);
            if (is_array($data)) {
                $data['computed_at'] = $row['computed_at'];
                $data['last_updated_formatted'] = date('F j, Y, g:i A', strtotime($row['computed_at']));
                $data['source'] = 'cache';
                return $data;
            }
        }

        if ($autoGenerateIfEmpty) {
            return $this->computeAndStoreSummary();
        }

        return ['source' => 'empty', 'items' => []];
    }

    /**
     * Get period forecast (weekly, monthly, quarterly, semi-annual, annual).
     * Reads directly from pre-calculated cache (<15ms).
     */
    public function getForecast(string $period = 'monthly', bool $autoGenerateIfEmpty = true): array
    {
        $period = in_array($period, ['weekly', 'monthly', 'quarterly', 'semi-annual', 'annual'], true) ? $period : 'monthly';
        $currentKey = $this->resolvePeriodKey($period);

        $stmt = $this->conn->prepare("
            SELECT predictions_data, computed_at 
            FROM insight_predictions 
            WHERE period_type = ? AND period_key = ? 
            LIMIT 1
        ");
        $stmt->bind_param('ss', $period, $currentKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['predictions_data'])) {
            $data = json_decode($row['predictions_data'], true);
            if (is_array($data)) {
                $data['computed_at'] = $row['computed_at'];
                $data['last_updated_formatted'] = date('F j, Y, g:i A', strtotime($row['computed_at']));
                $data['source'] = 'cache';
                return $data;
            }
        }

        if ($autoGenerateIfEmpty) {
            $this->computeAndStoreForecasts();
            return $this->getForecast($period, false);
        }

        return ['source' => 'empty', 'items' => []];
    }

    /**
     * Get movement and risk analysis (reads from cache).
     */
    public function getMovementAndRisks(bool $autoGenerateIfEmpty = true): array
    {
        $stmt = $this->conn->prepare("SELECT anomaly_data, computed_at FROM anomaly_results WHERE analysis_type = 'movement_and_risks' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['anomaly_data'])) {
            $data = json_decode($row['anomaly_data'], true);
            if (is_array($data)) {
                $data['computed_at'] = $row['computed_at'];
                $data['last_updated_formatted'] = date('F j, Y, g:i A', strtotime($row['computed_at']));
                $data['source'] = 'cache';
                return $data;
            }
        }

        if ($autoGenerateIfEmpty) {
            return $this->computeAndStoreMovementAndRisks();
        }

        return ['source' => 'empty', 'product_movement' => [], 'expiry_risks' => []];
    }

    /**
     * Full background regeneration of all metrics.
     */
    public function regenerateAll(): array
    {
        $summary = $this->computeAndStoreSummary();
        $movement = $this->computeAndStoreMovementAndRisks();
        $forecasts = $this->computeAndStoreForecasts();

        return [
            'success'      => true,
            'computed_at'  => date('Y-m-d H:i:s'),
            'formatted_at' => date('F j, Y, g:i A'),
            'summary'      => 'All analytics and predictions successfully pre-calculated and cached.'
        ];
    }

    /**
     * Compute and store main dashboard summary metrics.
     * Uses optimized bulk aggregation queries instead of correlated subqueries.
     */
    public function computeAndStoreSummary(): array
    {
        // 1. Bulk Category Performance (Single Group-By query)
        $catSql = "
            SELECT 
                p.category AS category_name,
                COUNT(p.product_id) AS product_count,
                SUM(p.quantity) AS total_stock,
                AVG(p.quantity) AS avg_stock,
                COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) AS low_stock_count,
                COALESCE(sales.total_sales, 0) AS monthly_sales
            FROM products p
            LEFT JOIN (
                SELECT p2.category, COUNT(*) AS total_sales
                FROM transactions t
                JOIN products p2 ON t.product_id = p2.product_id
                WHERE t.transaction_type = 'stock_out' AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p2.category
            ) sales ON p.category = sales.category
            WHERE p.category IS NOT NULL AND p.category != ''
            GROUP BY p.category
            ORDER BY monthly_sales DESC
        ";
        $catRes = $this->conn->query($catSql);
        $categoryPerformance = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

        // 2. Sales Trends (Single indexed query)
        $trendSql = "
            SELECT 
                DATE(t.transaction_date) as sale_date, 
                DAYNAME(t.transaction_date) as day_name,
                COUNT(DISTINCT t.product_id) as unique_products_sold, 
                SUM(t.quantity) as total_quantity_sold,
                AVG(t.quantity) as avg_quantity_per_transaction
            FROM transactions t 
            WHERE t.transaction_type = 'stock_out' AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(t.transaction_date), DAYNAME(t.transaction_date)
            ORDER BY sale_date DESC 
            LIMIT 30
        ";
        $trendRes = $this->conn->query($trendSql);
        $salesTrends = $trendRes ? $trendRes->fetch_all(MYSQLI_ASSOC) : [];

        // 3. Supplier Performance (Single bulk query)
        $suppSql = "
            SELECT 
                p.supplier, 
                COUNT(p.product_id) as product_count, 
                SUM(p.quantity) as total_stock_value,
                AVG(p.quantity) as avg_stock_per_product,
                COALESCE(s_sales.sales_count, 0) as monthly_sales,
                COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) as low_stock_count
            FROM products p 
            LEFT JOIN (
                SELECT p2.supplier, COUNT(*) as sales_count
                FROM transactions t
                JOIN products p2 ON t.product_id = p2.product_id
                WHERE t.transaction_type = 'stock_out' AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p2.supplier
            ) s_sales ON p.supplier = s_sales.supplier
            WHERE p.supplier IS NOT NULL AND p.supplier != ''
            GROUP BY p.supplier 
            ORDER BY monthly_sales DESC
        ";
        $suppRes = $this->conn->query($suppSql);
        $supplierPerformance = $suppRes ? $suppRes->fetch_all(MYSQLI_ASSOC) : [];

        // 4. Movement Analysis & KPI totals
        $movementData = $this->computeAndStoreMovementAndRisks();
        $productMovement = $movementData['product_movement'] ?? [];
        $expiryRisks = $movementData['expiry_risks'] ?? [];

        $totalProducts = count($productMovement);
        $attentionNeededCount = count(array_filter($productMovement, function($p) {
            return ($p['stock_status'] ?? '') === 'low_stock' || ($p['stock_status'] ?? '') === 'out_of_stock';
        }));
        $fastMovingCount = count(array_filter($productMovement, function($p) {
            return ($p['movement_class'] ?? '') === 'fast_moving';
        }));
        $expiringSoonCount = count(array_filter($expiryRisks, function($e) {
            return ($e['expiry_risk'] ?? '') === 'high';
        }));

        $summaryData = [
            'total_products'         => $totalProducts,
            'attention_needed_count' => $attentionNeededCount,
            'fast_moving_count'      => $fastMovingCount,
            'expiring_soon_count'    => $expiringSoonCount,
            'category_performance'   => $categoryPerformance,
            'sales_trends'           => $salesTrends,
            'supplier_performance'   => $supplierPerformance,
            'product_movement'       => $productMovement, // Full active catalog with out_of_stock and low_stock prioritized
            'expiry_risk_analysis'   => array_slice($expiryRisks, 0, 50),
            'computed_at'            => date('Y-m-d H:i:s'),
            'last_updated_formatted' => date('F j, Y, g:i A'),
            'source'                 => 'computed'
        ];

        // Store into insight_summary
        $json = json_encode($summaryData);
        $stmt = $this->conn->prepare("
            INSERT INTO insight_summary (summary_key, summary_data) 
            VALUES ('main_dashboard_summary', ?) 
            ON DUPLICATE KEY UPDATE summary_data = VALUES(summary_data)
        ");
        $stmt->bind_param('s', $json);
        $stmt->execute();
        $stmt->close();

        return $summaryData;
    }

    /**
     * Compute and store movement classification and expiry risks.
     */
    public function computeAndStoreMovementAndRisks(): array
    {
        // Optimized Movement query using bulk subquery
        $movSql = "
            SELECT 
                p.product_id, 
                p.product_name, 
                p.category AS category_name, 
                p.quantity AS current_stock, 
                p.reorder_point,
                COALESCE(t.total_sales, 0) AS monthly_sales
            FROM products p
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS total_sales
                FROM transactions
                WHERE transaction_type = 'stock_out' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY product_id
            ) t ON p.product_id = t.product_id
            WHERE p.archived = 0
            ORDER BY monthly_sales DESC
        ";
        $movRes = $this->conn->query($movSql);
        $products = [];
        if ($movRes) {
            while ($row = $movRes->fetch_assoc()) {
                $stock = (int)$row['current_stock'];
                $reorder = (int)$row['reorder_point'];
                $sales = (int)$row['monthly_sales'];

                $row['daily_sales_velocity'] = $sales > 0 ? $sales / 30 : 0;
                $row['days_of_supply'] = $row['daily_sales_velocity'] > 0 ? round($stock / $row['daily_sales_velocity'], 1) : 999;

                if ($stock <= 0) {
                    $row['stock_status'] = 'out_of_stock';
                } elseif ($stock <= $reorder) {
                    $row['stock_status'] = 'low_stock';
                } elseif ($row['days_of_supply'] > 90) {
                    $row['stock_status'] = 'overstocked';
                } else {
                    $row['stock_status'] = 'normal';
                }

                if ($sales >= 50) {
                    $row['movement_class'] = 'fast_moving';
                } elseif ($sales >= 10) {
                    $row['movement_class'] = 'medium_moving';
                } elseif ($sales > 0) {
                    $row['movement_class'] = 'slow_moving';
                } else {
                    $row['movement_class'] = 'non_moving';
                }

                $products[] = $row;
            }

            // Prioritize out_of_stock and low_stock at the top of the table
            usort($products, function($a, $b) {
                $aOut = ($a['stock_status'] === 'out_of_stock') ? 1 : 0;
                $bOut = ($b['stock_status'] === 'out_of_stock') ? 1 : 0;
                if ($aOut !== $bOut) return $bOut - $aOut;

                $aLow = ($a['stock_status'] === 'low_stock') ? 1 : 0;
                $bLow = ($b['stock_status'] === 'low_stock') ? 1 : 0;
                if ($aLow !== $bLow) return $bLow - $aLow;

                return ($b['monthly_sales'] ?? 0) - ($a['monthly_sales'] ?? 0);
            });
        }

        // Optimized Expiry Risk query
        $expSql = "
            SELECT 
                p.product_id, 
                p.product_name, 
                p.category AS category_name, 
                b.quantity, 
                b.expiry_date,
                DATEDIFF(b.expiry_date, CURDATE()) AS days_until_expiry,
                COALESCE(t.monthly_sales, 0) AS monthly_sales
            FROM inventory_batches b
            JOIN products p ON b.product_id = p.product_id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS monthly_sales
                FROM transactions
                WHERE transaction_type = 'stock_out' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY product_id
            ) t ON p.product_id = t.product_id
            WHERE b.expiry_date IS NOT NULL AND b.expiry_date > CURDATE() AND b.quantity > 0
            ORDER BY days_until_expiry ASC
            LIMIT 100
        ";
        $expRes = $this->conn->query($expSql);
        $expiryRisks = [];
        if ($expRes) {
            while ($row = $expRes->fetch_assoc()) {
                $days = (int)$row['days_until_expiry'];
                if ($days <= 30) {
                    $row['expiry_risk'] = 'high';
                } elseif ($days <= 90) {
                    $row['expiry_risk'] = 'medium';
                } else {
                    $row['expiry_risk'] = 'low';
                }
                $daily = ((int)$row['monthly_sales']) / 30;
                $row['sell_through_days'] = $daily > 0 ? round(((int)$row['quantity']) / $daily, 1) : 999;
                $expiryRisks[] = $row;
            }
        }

        $resultData = [
            'product_movement' => $products,
            'expiry_risks'     => $expiryRisks,
            'computed_at'      => date('Y-m-d H:i:s')
        ];

        $json = json_encode($resultData);
        $stmt = $this->conn->prepare("
            INSERT INTO anomaly_results (analysis_type, anomaly_data) 
            VALUES ('movement_and_risks', ?) 
            ON DUPLICATE KEY UPDATE anomaly_data = VALUES(anomaly_data)
        ");
        $stmt->bind_param('s', $json);
        $stmt->execute();
        $stmt->close();

        return $resultData;
    }

    /**
     * Compute and store demand forecasts for all periods.
     * Integrates with ML results (forecast_results table) when available.
     */
    public function computeAndStoreForecasts(): array
    {
        $periods = ['weekly', 'monthly', 'quarterly', 'semi-annual', 'annual'];
        $results = [];

        foreach ($periods as $period) {
            $forecastDays = 30;
            switch ($period) {
                case 'weekly':      $forecastDays = 7; break;
                case 'monthly':     $forecastDays = 30; break;
                case 'quarterly':   $forecastDays = 90; break;
                case 'semi-annual': $forecastDays = 180; break;
                case 'annual':      $forecastDays = 365; break;
            }

            // Check if ML model results exist in forecast_results
            $mlItems = [];
            $mlQuery = "
                SELECT 
                    p.product_id,
                    p.product_name,
                    p.quantity as current_stock,
                    p.reorder_point,
                    p.category AS category_name,
                    SUM(fr.predicted_quantity) AS total_historical_sales
                FROM forecast_results fr
                JOIN products p ON fr.product_id = p.product_id
                WHERE fr.product_id > 0
                  AND fr.forecast_date >= CURDATE()
                  AND fr.forecast_date < DATE_ADD(CURDATE(), INTERVAL {$forecastDays} DAY)
                GROUP BY p.product_id
                ORDER BY total_historical_sales DESC
                LIMIT 10
            ";
            $mlRes = @$this->conn->query($mlQuery);
            if ($mlRes && $mlRes->num_rows > 0) {
                while ($r = $mlRes->fetch_assoc()) {
                    $mlItems[] = $this->enrichForecastRow($r, true, $forecastDays);
                }
            }

            // 1. Critical Inventory Items: Out of Stock and Low Stock MUST be analyzed in Smart Restock
            $attentionItems = [];
            $attentionQuery = "
                SELECT 
                    p.product_id,
                    p.product_name,
                    p.quantity as current_stock,
                    p.reorder_point,
                    p.category AS category_name,
                    COALESCE(t_recent.recent_sales, 0) AS recent_sales,
                    COALESCE(t_all.all_time_sales, 0) AS all_time_sales,
                    COALESCE(t_all.sale_count, 0) AS sale_count
                FROM products p
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) AS recent_sales
                    FROM transactions
                    WHERE transaction_type = 'stock_out'
                      AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL {$forecastDays} DAY)
                    GROUP BY product_id
                ) t_recent ON p.product_id = t_recent.product_id
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) AS all_time_sales, COUNT(*) as sale_count
                    FROM transactions
                    WHERE transaction_type = 'stock_out'
                    GROUP BY product_id
                ) t_all ON p.product_id = t_all.product_id
                WHERE p.archived = 0 
                  AND (p.quantity <= 0 OR p.quantity <= p.reorder_point)
                ORDER BY p.quantity ASC, p.reorder_point DESC
            ";
            $attRes = $this->conn->query($attentionQuery);
            if ($attRes && $attRes->num_rows > 0) {
                while ($r = $attRes->fetch_assoc()) {
                    $stock = (int)$r['current_stock'];
                    $reorder = (int)$r['reorder_point'];
                    $recent = (float)$r['recent_sales'];
                    $allTime = (float)$r['all_time_sales'];
                    $cnt = (int)$r['sale_count'];

                    if ($recent > 0) {
                        $demand = $recent;
                    } elseif ($allTime > 0 && $cnt > 0) {
                        $demand = max(1, round(($allTime / $cnt) * max(1, $forecastDays / 30)));
                    } else {
                        $demand = max(5, (float)$reorder);
                    }

                    $r['total_historical_sales'] = $demand;
                    $attentionItems[] = $this->enrichForecastRow($r, false, $forecastDays);
                }
            }

            // 2. Fallback to transaction patterns if ML results are empty
            if (empty($mlItems)) {
                $histQuery = "
                    SELECT 
                        p.product_id,
                        p.product_name,
                        p.quantity as current_stock,
                        p.reorder_point,
                        p.category AS category_name,
                        SUM(t.quantity) AS total_historical_sales
                    FROM transactions t
                    JOIN products p ON t.product_id = p.product_id
                    WHERE t.transaction_type = 'stock_out'
                      AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL {$forecastDays} DAY)
                      AND p.archived = 0
                    GROUP BY p.product_id
                    ORDER BY total_historical_sales DESC
                    LIMIT 15
                ";
                $histRes = $this->conn->query($histQuery);
                if ($histRes && $histRes->num_rows > 0) {
                    while ($r = $histRes->fetch_assoc()) {
                        $mlItems[] = $this->enrichForecastRow($r, false, $forecastDays);
                    }
                }
            }

            // Merge critical attention items with general forecast items (deduplicated by product_id)
            $mergedItems = [];
            $seenIds = [];
            foreach ($attentionItems as $item) {
                $pid = (int)$item['product_id'];
                if (!isset($seenIds[$pid])) {
                    $seenIds[$pid] = true;
                    $mergedItems[] = $item;
                }
            }
            foreach ($mlItems as $item) {
                $pid = (int)$item['product_id'];
                if (!isset($seenIds[$pid])) {
                    $seenIds[$pid] = true;
                    $mergedItems[] = $item;
                }
            }

            // Sort with Critical Outage (out of stock) and High Risk (low stock) at the top
            usort($mergedItems, function($a, $b) {
                $score = function($type) {
                    if (strpos($type, 'Critical') !== false) return 1;
                    if (strpos($type, 'Risk') !== false) return 2;
                    if (strpos($type, 'Cover') !== false) return 3;
                    if (strpos($type, 'Optimal') !== false) return 4;
                    if (strpos($type, 'Slow') !== false) return 5;
                    return 6;
                };
                $sa = $score($a['type'] ?? '');
                $sb = $score($b['type'] ?? '');
                if ($sa !== $sb) return $sa - $sb;
                return ((int)($a['current_stock'] ?? 0)) - ((int)($b['current_stock'] ?? 0));
            });

            $periodKey = $this->resolvePeriodKey($period);
            $payload = [
                'period_type'            => $period,
                'period_key'             => $periodKey,
                'forecast_days'          => $forecastDays,
                'items'                  => $mergedItems,
                'computed_at'            => date('Y-m-d H:i:s'),
                'last_updated_formatted' => date('F j, Y, g:i A')
            ];

            $json = json_encode($payload);
            $stmt = $this->conn->prepare("
                INSERT INTO insight_predictions (period_type, period_key, predictions_data)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE predictions_data = VALUES(predictions_data)
            ");
            $stmt->bind_param('sss', $period, $periodKey, $json);
            $stmt->execute();
            $stmt->close();

            $results[$period] = $payload;
        }

        return $results;
    }

    /**
     * Convert days into natural, easily understandable human duration.
     */
    public static function formatHumanDuration(int $days): string
    {
        if ($days <= 0) return 'less than 24 hours';
        if ($days === 1) return '1 day';
        if ($days < 7) return "{$days} days";
        if ($days < 30) {
            $weeks = round($days / 7, 1);
            return ($weeks == 1.0) ? '1 week' : "approx. {$weeks} weeks";
        }
        $months = round($days / 30, 1);
        if ($months < 24) {
            return ($months == 1.0) ? '1 month' : "approx. " . round($months) . " months";
        }
        $years = round($days / 365, 1);
        if ($years > 20) {
            return 'more than 20 years of supply';
        }
        return ($years == 1.0) ? 'over 1 year of supply' : "over " . round($years) . " years of supply";
    }

    /**
     * Generate clear, human-understandable Descriptive, Predictive, and Prescriptive insight.
     */
    public static function buildSmartInsight(int $stock, float $demand, int $forecastDays = 30, ?int $reorderPoint = null, bool $isMl = false): array
    {
        $demandRound = (int)round($demand);
        $stockFormatted = number_format($stock);
        $demandFormatted = number_format($demandRound);
        $forecastDays = max(1, $forecastDays);

        $dailyRate = ($forecastDays > 0 && $demand > 0) ? ($demand / $forecastDays) : 0;
        $daysRemaining = ($dailyRate > 0) ? (int)round($stock / $dailyRate) : ($stock > 0 ? 9999 : 0);
        $humanDuration = self::formatHumanDuration($daysRemaining);

        $coverage = ($demand > 0) ? ($stock / $demand) * 100 : ($stock > 0 ? 999 : 100);

        if ($stock <= 0 && $demandRound > 0) {
            $type = 'Critical Outage';
            $actionNeeded = 'urgent';
            $recOrder = max(10, (int)round($demandRound * 1.25));
            $descriptive = "Currently completely out of stock (0 units on hand).";
            $predictive = "Projected customer demand is <strong>{$demandFormatted} units</strong> for the upcoming period, risking immediate lost sales.";
            $prescriptive = "Place an urgent restock order of at least <strong>" . number_format($recOrder) . " units</strong> immediately to restore shelf availability.";
        } elseif ($stock <= 0 && $demandRound <= 0) {
            $type = 'Critical Outage';
            $actionNeeded = 'urgent';
            $recOrder = max(10, (int)($reorderPoint ?? 10));
            $descriptive = "Item is depleted (0 units on hand) with no recent sales recorded.";
            $predictive = "Customer demand is dormant under current stockout conditions.";
            $prescriptive = "Place a sample replenishment order of at least <strong>" . number_format($recOrder) . " units</strong> or review customer search trends.";
        } elseif ($stock > 0 && ($demandRound <= 0 || $demand < 0.5)) {
            $type = 'Slow Moving';
            $actionNeeded = 'pause';
            $descriptive = "High surplus inventory: Warehouse currently holds <strong>{$stockFormatted} units</strong> with minimal turnover.";
            $predictive = "Customer demand is virtually flat (less than 1 unit expected for this period), leaving current stock dormant.";
            $prescriptive = "Pause all new purchase orders. Audit retail pricing, verify display placement, or bundle with popular products to stimulate sales.";
        } elseif ($coverage < 50 || ($reorderPoint !== null && $stock <= $reorderPoint)) {
            $type = 'High Risk';
            $actionNeeded = 'restock';
            $deficit = max(1, $demandRound - $stock);
            $recOrder = max($deficit, (int)round($demandRound * 0.5));
            $descriptive = "Critically low inventory: Only <strong>{$stockFormatted} units</strong> remaining on hand.";
            $predictive = "Projected demand is <strong>{$demandFormatted} units</strong>, meaning current stock will be exhausted in <strong>{$humanDuration}</strong>.";
            $prescriptive = "Initiate a restock order for at least <strong>" . number_format($recOrder) . " units</strong> this week to prevent a total stockout.";
        } elseif ($coverage < 100) {
            $type = 'Low Cover';
            $actionNeeded = 'monitor';
            $deficit = max(1, $demandRound - $stock);
            $recOrder = max($deficit, 5);
            $descriptive = "Lean inventory buffer: Holding <strong>{$stockFormatted} units</strong> against an expected demand of <strong>{$demandFormatted} units</strong>.";
            $predictive = "Stock will cover <strong>{$humanDuration}</strong> of sales, leaving very little margin for sudden demand spikes or delivery delays.";
            $prescriptive = "Prepare a replenishment order of <strong>" . number_format($recOrder) . " units</strong> with your supplier to restore safe buffer levels.";
        } elseif ($coverage > 300 || $daysRemaining > 90) {
            $type = 'Slow Moving';
            $actionNeeded = 'pause';
            $descriptive = "High surplus inventory: Warehouse currently holds <strong>{$stockFormatted} units</strong> in storage.";
            $predictive = "Demand is sluggish at <strong>{$demandFormatted} units</strong> for the period, meaning existing stock represents <strong>{$humanDuration}</strong>.";
            $prescriptive = "Pause all new purchase orders. Consider promotional bundling, featured placement, or discounts to accelerate turnover and free up warehouse space.";
        } else {
            $type = 'Optimal';
            $actionNeeded = 'none';
            $descriptive = "Well-balanced inventory: Currently holding <strong>{$stockFormatted} units</strong> in good condition.";
            $predictive = "Projected demand is <strong>{$demandFormatted} units</strong>, providing comfortable <strong>{$humanDuration}</strong> coverage without excess holding costs.";
            $prescriptive = "Maintain regular reorder schedules; no emergency purchasing is required at this time.";
        }

        $insightHtml = '<div style="margin-bottom: 4px;"><strong>Current Situation:</strong> ' . $descriptive . '</div>' .
                       '<div style="margin-bottom: 4px;"><strong>Forecast:</strong> ' . $predictive . '</div>' .
                       '<div><strong>Recommended Action:</strong> ' . $prescriptive . '</div>';


        return [
            'type'          => $type,
            'action_needed' => $actionNeeded,
            'coverage_pct'  => round($coverage, 1),
            'descriptive'   => $descriptive,
            'predictive'    => $predictive,
            'prescriptive'  => $prescriptive,
            'insight'       => $insightHtml
        ];
    }

    /**
     * Format row with insight messages and actions.
     */
    private function enrichForecastRow(array $row, bool $isMl, int $forecastDays = 30): array
    {
        $stock = (int)$row['current_stock'];
        $demand = (float)$row['total_historical_sales'];
        $reorderPoint = isset($row['reorder_point']) ? (int)$row['reorder_point'] : null;

        $insightData = self::buildSmartInsight($stock, $demand, $forecastDays, $reorderPoint, $isMl);

        $row['type'] = $insightData['type'];
        $row['action_needed'] = $insightData['action_needed'];
        $row['coverage_pct'] = $insightData['coverage_pct'];
        $row['descriptive'] = $insightData['descriptive'];
        $row['predictive'] = $insightData['predictive'];
        $row['prescriptive'] = $insightData['prescriptive'];
        $row['insight'] = $insightData['insight'];

        return $row;
    }


    private function resolvePeriodKey(string $period): string
    {
        switch ($period) {
            case 'weekly':
                return date('Y-W');
            case 'monthly':
                return date('Y-m');
            case 'quarterly':
                return date('Y-') . 'Q' . ceil(date('n') / 3);
            case 'semi-annual':
                return date('Y-') . 'S' . (date('n') <= 6 ? '1' : '2');
            case 'annual':
                return date('Y');
            default:
                return date('Y-m');
        }
    }
}
