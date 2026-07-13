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

        // Fetch products
        $sql = "SELECT p.product_id, p.product_name, p.category, p.quantity, p.reorder_point, p.unit, (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date, p.created_at FROM products p";
        $res = $this->conn->query($sql);
        if (!$res) return ['error' => $this->conn->error];

        while ($p = $res->fetch_assoc()) {
            $pid = (int)$p['product_id'];
            $stock = (int)$p['quantity'];

            // sold this month (stock_out)
            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity),0) as sold FROM transactions WHERE product_id = ? AND transaction_type = 'stock_out' AND YEAR(transaction_date)=? AND MONTH(transaction_date)=?");
            $stmt->bind_param('iii', $pid, $year, $month);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $soldThisMonth = (int)$r['sold'];
            $stmt->close();

            // last restock date (latest stock_in)
            $stmt2 = $this->conn->prepare("SELECT MAX(transaction_date) as last_restock FROM transactions WHERE product_id = ? AND transaction_type = 'stock_in'");
            $stmt2->bind_param('i', $pid);
            $stmt2->execute();
            $lr = $stmt2->get_result()->fetch_assoc();
            $lastRestock = $lr['last_restock'];
            $stmt2->close();

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