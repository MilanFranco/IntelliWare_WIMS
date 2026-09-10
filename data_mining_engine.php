<?php
class InventoryDataMiner {
    private $conn;
    private $detailed = [];
    private $summary = [
        'high_demand' => 0,
        'low_demand' => 0,
        'out_of_stock' => 0,
        'low_stock' => 0,
        'overstocked' => 0,
        'inactive' => 0,
        'normal' => 0,
    ];

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Perform data mining analysis focusing on the active rolling 30-day period.
     * - Computes units sold in rolling 30-day window from transactions (stock_out)
     * - Compares sold vs current stock to identify High/Low demand, Out of Stock, Low Stock
     * - Produces a detailed array stored in $this->detailed and a summary in $this->summary
     */
    public function performDataMining() {
        $this->detailed = [];
        // Reset summary counters at the beginning of each run
        $this->summary = array_fill_keys(array_keys($this->summary), 0);
        
        // Dynamic rolling 30-day window (no hardcoded dates or calendar month cutoffs)
        $dateStart = date('Y-m-d H:i:s', strtotime('-30 days'));
        $dateEnd = date('Y-m-d H:i:s');

        $sql = "
            SELECT 
                p.product_id, p.product_name, p.category, p.quantity, p.reorder_point, p.unit, p.created_at,
                (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
                COALESCE(t_out.sold, 0) as sold_this_month,
                t_in.last_restock
            FROM products p
            LEFT JOIN (
                SELECT product_id, SUM(quantity) as sold 
                FROM transactions 
                WHERE transaction_type = 'stock_out' AND transaction_date >= ? AND transaction_date <= ?
                GROUP BY product_id
            ) t_out ON p.product_id = t_out.product_id
            LEFT JOIN (
                SELECT product_id, MAX(transaction_date) as last_restock 
                FROM transactions 
                WHERE transaction_type = 'stock_in'
                GROUP BY product_id
            ) t_in ON p.product_id = t_in.product_id
            WHERE p.archived = 0
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $dateStart, $dateEnd);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if (!$res) return ['error' => $this->conn->error];

        while ($p = $res->fetch_assoc()) {
            $pid = (int)$p['product_id'];
            $stock = (int)$p['quantity'];
            $soldThisMonth = (int)$p['sold_this_month'];
            $lastRestock = $p['last_restock'];

            $daysSinceRestock = $this->calculateDaysSinceRestock($lastRestock);

            $soldPercentage = $stock > 0 ? ($soldThisMonth / $stock) * 100 : ($soldThisMonth > 0 ? 100 : 0);

            // --- START ENHANCED CLASSIFICATION ---

            // 1. Define all classification rules
            // Rule 0: Out of Stock (stock is 0 or depleted)
            $isOutOfStock = $stock <= 0;

            // Rule 1: High Demand
            $isHighDemand = $soldPercentage >= 80 && $soldThisMonth > 0;

            // Rule 2: Low Demand
            $isLowDemand = $soldPercentage < 20 && $soldThisMonth > 0;

            // Rule 3: Low Stock (Stock is > 0 and <= reorder_point)
            $reorderPoint = (int)$p['reorder_point'] > 0 ? (int)$p['reorder_point'] : 10;
            $isLowStock = $stock > 0 && $stock <= $reorderPoint;

            // Rule 4: Overstocked
            $isOverstocked = $stock > 200;

            // Rule 5: Inactive (No restock AND no sales for 90+ days and not out/low stock)
            $isInactive = $daysSinceRestock > 90 && $soldThisMonth == 0 && !$isOutOfStock && !$isLowStock;


            // 2. Update Summary Counters (for Metric Cards)
            if ($isOutOfStock)  $this->summary['out_of_stock']++;
            if ($isLowStock)    $this->summary['low_stock']++;
            if ($isHighDemand)  $this->summary['high_demand']++;
            if ($isLowDemand)   $this->summary['low_demand']++;
            if ($isOverstocked) $this->summary['overstocked']++;
            if ($isInactive)    $this->summary['inactive']++;

            
            // 3. Assign a *single* Primary Classification (for Table Display)
            // Priority: out_of_stock > low_stock > high_demand > overstocked > inactive > low_demand > normal
            $classification = 'normal'; // Default
            
            if ($isOutOfStock) {
                $classification = 'out_of_stock';
            } elseif ($isLowStock) {
                $classification = 'low_stock';
            } elseif ($isHighDemand) {
                $classification = 'high_demand';
            } elseif ($isOverstocked) {
                $classification = 'overstocked';
            } elseif ($isInactive) {
                $classification = 'inactive';
            } elseif ($isLowDemand) {
                $classification = 'low_demand';
            }

            // Only increment 'normal' if no other classification was met
            if ($classification === 'normal') {
                $this->summary['normal']++;
            }
            
            // --- END ENHANCED CLASSIFICATION ---

            $this->detailed[] = [
                'product_id' => $pid,
                'product_name' => $p['product_name'],
                'category' => $p['category'],
                'quantity' => $stock,
                'reorder_point' => (int)$p['reorder_point'],
                'unit' => $p['unit'],
                'expiry_date' => $p['expiry_date'],
                'sold_quantity' => $soldThisMonth,
                'sold_percentage' => round($soldPercentage,2),
                'classification' => $classification,
                'days_since_restock' => $daysSinceRestock,
                'recommended_action' => $this->recommendAction($classification, $stock, $soldThisMonth)
            ];
        }

        // Prioritize out_of_stock and low_stock at the top of the detailed list
        usort($this->detailed, function($a, $b) {
            $prio = function($c) {
                if ($c === 'out_of_stock') return 1;
                if ($c === 'low_stock') return 2;
                if ($c === 'high_demand') return 3;
                if ($c === 'overstocked') return 4;
                if ($c === 'inactive') return 5;
                if ($c === 'low_demand') return 6;
                return 7;
            };
            $pa = $prio($a['classification']);
            $pb = $prio($b['classification']);
            if ($pa !== $pb) return $pa - $pb;
            return $a['quantity'] - $b['quantity'];
        });

        return [
            'processed' => count($this->detailed),
            'summary' => $this->summary
        ];
    }

    private function calculateDaysSinceRestock($lastRestockDate) {
        if (!$lastRestockDate) return 999;
        $restockTime = strtotime($lastRestockDate);
        if (!$restockTime) return 999;
        $days = (time() - $restockTime) / (60*60*24);
        return max(0, (int)round($days));
    }

    private function recommendAction($classification, $stock, $soldThisMonth) {
        switch ($classification) {
            case 'out_of_stock':
                return 'Urgent replenishment required (0 units in stock)';
            case 'high_demand':
                return 'Replenish immediately (high demand)';
            case 'low_demand':
                return 'Delay or reduce restock (low demand)';
            case 'low_stock':
                return 'Reorder - stock below reorder threshold';
            case 'overstocked':
                return 'Hold off restock - overstocked';
            case 'inactive':
                return 'Review expiry/marketing - inactive';
            default:
                return 'No immediate action';
        }
    }

    public function getDetailedAnalysis() {
        return $this->detailed;
    }

    public function getAnalysisSummary() {
        return $this->summary;
    }
}
?>