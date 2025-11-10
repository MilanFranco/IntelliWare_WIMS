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
        // Get current month/year
        $year = (int)date('Y');
        $month = (int)date('m');

        // Fetch products
        $sql = "SELECT product_id, product_name, category, quantity, reorder_point, unit, expiry_date, created_at FROM products";
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

            // Classification rules per your spec
            if ($soldPercentage >= 80) {
                $classification = 'high_demand';
                $this->summary['high_demand']++;
            } elseif ($soldPercentage < 20 && $soldThisMonth > 0) {
                $classification = 'low_demand';
                $this->summary['low_demand']++;
            } elseif ($stock < 10) {
                $classification = 'low_stock';
                $this->summary['low_stock']++;
            } elseif ($stock > 200) {
                $classification = 'overstocked';
                $this->summary['overstocked']++;
            } elseif ($daysSinceRestock > 90) {
                $classification = 'inactive';
                $this->summary['inactive']++;
            } else {
                $classification = 'normal';
                $this->summary['normal']++;
            }

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


