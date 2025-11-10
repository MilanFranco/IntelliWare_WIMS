<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PythonHelper.php';

class ProductRecommendationManager {
    private $conn;
    private $python_path;
    private $script_path;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        
        // Auto-detect Python path, or use custom path if set
        // You can override by setting a custom path here:
        // $customPath = "C:\\Python39\\python.exe";
        $customPath = null; // Set this if you have a specific Python installation
        try {
            $this->python_path = PythonHelper::getPythonPath($customPath);
        } catch (Exception $e) {
            // If Python is not found, we'll handle it when it's actually needed
            $this->python_path = null;
        }
        
        $this->script_path = __DIR__ . "/product_recommender.py";
    }
    
    public function generateRecommendations() {
        try {
            // Check if Python executable exists
            if (!$this->python_path || !file_exists($this->python_path)) {
                // Try to find Python again
                try {
                    $this->python_path = PythonHelper::getPythonPath();
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
            
            // Check if Python script exists
            if (!file_exists($this->script_path)) {
                throw new Exception("Recommendation script not found at: " . $this->script_path);
            }
            
            // Execute Python script
            $command = escapeshellcmd($this->python_path) . " " . 
                      escapeshellarg($this->script_path) . " 2>&1";
            
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            // Parse the output
            $result = null;
            foreach ($output as $line) {
                if (strpos($line, '{') === 0) {
                    $result = json_decode($line, true);
                    break;
                }
            }
            
            return [
                'success' => $return_var === 0 && $result !== null,
                'data' => $result,
                'output' => $output
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getCurrentMonthRecommendations() {
        $query = "SELECT 
                    r.*,
                    p.product_name,
                    p.category,
                    p.quantity as current_stock,
                    p.unit as uom
                 FROM product_recommendations r
                 JOIN products p ON r.product_id = p.product_id
                 WHERE r.month = MONTH(CURRENT_DATE())
                 ORDER BY r.seasonal_strength DESC";
                 
        $result = $this->conn->query($query);
        
        if (!$result) {
            // Table might not exist or query failed
            return [];
        }
        
        $recommendations = [];
        
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'category' => $row['category'],
                'current_stock' => (int)$row['current_stock'],
                'uom' => $row['uom'],
                'avg_monthly_demand' => (float)$row['avg_monthly_demand'],
                'demand_std' => (float)$row['demand_std'],
                'seasonal_strength' => (float)$row['seasonal_strength'],
                'recommended_stock' => (int)$row['recommended_stock'],
                'restock_recommended' => (bool)$row['restock_recommended'],
                'last_updated' => $row['updated_at']
            ];
        }
        
        return $recommendations;
    }
    
    public function getRestockRecommendations() {
        $query = "SELECT 
                    r.*,
                    p.product_name,
                    p.category,
                    p.quantity as current_stock,
                    p.unit as uom
                 FROM product_recommendations r
                 JOIN products p ON r.product_id = p.product_id
                 WHERE r.month = MONTH(CURRENT_DATE())
                   AND r.restock_recommended = 1
                 ORDER BY (r.recommended_stock - p.quantity) DESC";
                 
        $result = $this->conn->query($query);
        
        if (!$result) {
            // Table might not exist or query failed
            return [];
        }
        
        $recommendations = [];
        
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'category' => $row['category'],
                'current_stock' => (int)$row['current_stock'],
                'uom' => $row['uom'],
                'recommended_stock' => (int)$row['recommended_stock'],
                'shortage' => (int)$row['recommended_stock'] - (int)$row['current_stock'],
                'avg_monthly_demand' => (float)$row['avg_monthly_demand'],
                'seasonal_strength' => (float)$row['seasonal_strength']
            ];
        }
        
        return $recommendations;
    }
}