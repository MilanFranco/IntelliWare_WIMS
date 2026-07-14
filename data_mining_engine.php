<?php
class InventoryDataMiner {
    private $conn;
    private $detailed = [];
    private $summary = [
        'high_demand' => 0,
        'low_demand' => 0,
        'low_stock' => 0,
        'overstocked' => 0,
        'inactive' => 0,
        'normal' => 0,
    ];

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Perform data mining analysis focusing on the current month.
     * - Computes units sold this month per product from transactions (stock_out)
     * - Compares sold vs current stock to identify High/Low demand
     * - Produces a detailed array stored in $this->detailed and a summary in $this->summary
     */
    public function performDataMining() {
        $this->detailed = [];
        // Reset summary counters at the beginning of each run
        $this->summary = array_fill_keys(array_keys($this->summary), 0);
        
        // Get current month/year
        $year = (int)date('Y');
        $month = (int)date('m');

        // Fetch products with bulk aggregations
        $dateStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $dateEnd = date('Y-m-d H:i:s', strtotime("+1 month", strtotime($dateStart)));

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
                WHERE transaction_type = 'stock_out' AND transaction_date >= ? AND transaction_date < ?
                GROUP BY product_id
            ) t_out ON p.product_id = t_out.product_id
            LEFT JOIN (
                SELECT product_id, MAX(transaction_date) as last_restock 
                FROM transactions 
                WHERE transaction_type = 'stock_in'
                GROUP BY product_id
            ) t_in ON p.product_id = t_in.product_id
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
            // Rule 1: High Demand
            $isHighDemand = $soldPercentage >= 80;

            // Rule 2: Low Demand
            $isLowDemand = $soldPercentage < 20 && $soldThisMonth > 0;

            // Rule 3: Low Stock (Use DB reorder_point, fallback to 10)
            // This is smarter than the original hardcoded '10'
            $reorderPoint = (int)$p['reorder_point'] > 0 ? (int)$p['reorder_point'] : 10;
            $isLowStock = $stock < $reorderPoint;

            // Rule 4: Overstocked
            $isOverstocked = $stock > 200; // Your original rule

            // Rule 5: Inactive (No restock AND no sales for 90+ days)
            $isInactive = $daysSinceRestock > 90 && $soldThisMonth == 0;


            // 2. Update Summary Counters (for Metric Cards)
            // This is the fix: check each condition independently.
            // A product can be in multiple summary categories (e.g., Low Stock AND High Demand)
            if ($isHighDemand)  $this->summary['high_demand']++;
            if ($isLowDemand)   $this->summary['low_demand']++;
            if ($isLowStock)    $this->summary['low_stock']++;
            if ($isOverstocked) $this->summary['overstocked']++;
            if ($isInactive)    $this->summary['inactive']++;

            
            // 3. Assign a *single* Primary Classification (for Table Display)
            // We use a priority list. Low stock is the most critical alert.
            $classification = 'normal'; // Default
            
            if ($isLowStock) {
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
            case 'high_demand':
                return 'Replenish immediately (high demand)';
            case 'low_demand':
                return 'Delay or reduce restock (low demand)';
            case 'low_stock':
                return 'Reorder - stock below threshold';
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