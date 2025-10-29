<?php
require_once 'config/db.php';

class InventoryDataMiner {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Perform comprehensive data mining analysis
     * Follows the exact specifications provided
     */
    public function performDataMining() {
        echo "=== STARTING DATA MINING ANALYSIS ===\n\n";
        
        $results = [
            'processed' => 0,
            'fast_moving' => 0,
            'low_stock' => 0,
            'overstocked' => 0,
            'inactive' => 0,
            'normal' => 0,
            'errors' => 0
        ];
        
        // Use MySQL cursor to iterate through inventory data
        $cursorQuery = "SELECT product_id, product_name, quantity as stock, sold_quantity, last_restock_date, category FROM products";
        $cursor = $this->conn->query($cursorQuery);
        
        if (!$cursor) {
            echo "✗ Error creating cursor: " . $this->conn->error . "\n";
            return $results;
        }
        
        echo "Processing inventory records...\n";
        
        while ($row = $cursor->fetch_assoc()) {
            $results['processed']++;
            
            // Extract key fields
            $productId = $row['product_id'];
            $productName = $row['product_name'];
            $stock = (int)$row['stock'];
            $sold = (int)$row['sold_quantity'];
            $lastRestock = $row['last_restock_date'];
            $category = $row['category'];
            
            // Compute derived metrics
            $daysSinceRestock = $this->calculateDaysSinceRestock($lastRestock);
            $soldPercentage = $stock > 0 ? ($sold / $stock) * 100 : 0;
            
            // Apply rule-based logic to categorize items
            $classification = $this->classifyItem($stock, $sold, $soldPercentage, $daysSinceRestock);
            
            // Update classification in database
            if ($this->updateClassification($productId, $classification)) {
                $results[$classification]++;
                echo "✓ {$productName}: {$classification} (Stock: {$stock}, Sold: {$sold}, Days since restock: {$daysSinceRestock})\n";
            } else {
                $results['errors']++;
                echo "✗ Failed to update {$productName}\n";
            }
        }
        
        $cursor->close();
        return $results;
    }
    
    /**
     * Calculate days since last restock
     */
    private function calculateDaysSinceRestock($lastRestockDate) {
        if (!$lastRestockDate || $lastRestockDate === '0000-00-00') {
            return 999; // No restock date = very old
        }
        
        $restockTime = strtotime($lastRestockDate);
        $currentTime = time();
        $daysDiff = ($currentTime - $restockTime) / (60 * 60 * 24);
        
        return max(0, round($daysDiff));
    }
    
    /**
     * Apply rule-based classification logic
     * Following the exact specifications provided
     */
    private function classifyItem($stock, $sold, $soldPercentage, $daysSinceRestock) {
        // Rule 1: If sold ≥ 80% of stock → classify as Fast-moving
        if ($soldPercentage >= 80) {
            return 'fast_moving';
        }
        
        // Rule 2: If stock < 10 → classify as Low-stock
        if ($stock < 10) {
            return 'low_stock';
        }
        
        // Rule 3: If stock > 200 → classify as Overstocked
        if ($stock > 200) {
            return 'overstocked';
        }
        
        // Rule 4: If days_since_restock > 90 → classify as Inactive
        if ($daysSinceRestock > 90) {
            return 'inactive';
        }
        
        // Otherwise, mark as Normal
        return 'normal';
    }
    
    /**
     * Update classification in database
     */
    private function updateClassification($productId, $classification) {
        $updateQuery = "UPDATE products SET classification = ? WHERE product_id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param("si", $classification, $productId);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get analysis summary
     */
    public function getAnalysisSummary() {
        $summaryQuery = "SELECT classification, COUNT(*) as count FROM products GROUP BY classification";
        $result = $this->conn->query($summaryQuery);
        
        $summary = [];
        while ($row = $result->fetch_assoc()) {
            $summary[$row['classification']] = $row['count'];
        }
        
        return $summary;
    }
    
    /**
     * Get detailed analysis for insights
     */
    public function getDetailedAnalysis() {
        $analysisQuery = "SELECT product_id, product_name, category, quantity, sold_quantity, 
                         last_restock_date, classification,
                         CASE 
                             WHEN last_restock_date IS NULL OR last_restock_date = '0000-00-00' THEN 999
                             ELSE DATEDIFF(CURDATE(), last_restock_date)
                         END as days_since_restock,
                         CASE 
                             WHEN quantity > 0 THEN ROUND((sold_quantity / quantity) * 100, 2)
                             ELSE 0
                         END as sold_percentage
                         FROM products ORDER BY classification, sold_percentage DESC";
        
        $result = $this->conn->query($analysisQuery);
        $analysis = [];
        
        while ($row = $result->fetch_assoc()) {
            $analysis[] = $row;
        }
        
        return $analysis;
    }
}

// Execute data mining
$miner = new InventoryDataMiner($conn);
$results = $miner->performDataMining();

echo "\n=== DATA MINING RESULTS ===\n";
echo "Total items processed: {$results['processed']}\n";
echo "Fast-moving items: {$results['fast_moving']}\n";
echo "Low-stock items: {$results['low_stock']}\n";
echo "Overstocked items: {$results['overstocked']}\n";
echo "Inactive items: {$results['inactive']}\n";
echo "Normal items: {$results['normal']}\n";
echo "Errors: {$results['errors']}\n";

echo "\n=== CURRENT CLASSIFICATION SUMMARY ===\n";
$summary = $miner->getAnalysisSummary();
foreach ($summary as $classification => $count) {
    echo ucfirst(str_replace('_', ' ', $classification)) . ": {$count} items\n";
}

$conn->close();
echo "\n=== DATA MINING COMPLETE ===\n";
?>


