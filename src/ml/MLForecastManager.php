<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PythonHelper.php';

class MLForecastManager {
    private $conn;
    private $python_path;
    private $script_path;
    private $model_path;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        
        
        Set $customPath = "C:\\YourPythonPath\\python.exe";
        $customPath = "C:\\YourPythonPath\\python.exe";
            $this->python_path = PythonHelper::getPythonPath($customPath);
        } catch (Exception $e) {
            // If Python is not found, we'll handle it when it's actually needed
            $this->python_path = null;
        }
        
        $this->script_path = __DIR__ . "/forecast_engine.py";
        $this->model_path = __DIR__ . "/models";
        
        // Create models directory if it doesn't exist
        if (!file_exists($this->model_path)) {
            mkdir($this->model_path, 0777, true);
        }
    }
    
    public function startForecastRun() {
        $query = "INSERT INTO forecast_runs (started_at, status) VALUES (NOW(), 'running')";
        $this->conn->query($query);
        return $this->conn->insert_id;
    }
    
    public function generateForecasts() {
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
                throw new Exception("Forecast script not found at: " . $this->script_path);
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
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue; // Try next line if JSON is invalid
                    }
                    break;
                }
            }
            
            if ($return_var !== 0) {
                $error = implode("\n", $output);
                throw new Exception("Python script execution failed (exit code: $return_var): " . $error);
            }
            
            if (!$result) {
                $error = implode("\n", $output);
                throw new Exception("No valid JSON output from Python script. Output: " . $error);
            }
            
            if (isset($result['status']) && $result['status'] !== 'success') {
                $error = $result['error'] ?? implode("\n", $output);
                throw new Exception("Forecast generation failed: " . $error);
            }
            
            return [
                'success' => true,
                'output' => $output,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function completeForecastRun($run_id, $result = null) {
        if (!$run_id) {
            throw new Exception("Invalid run_id provided");
        }
        
        if ($result && isset($result['result'])) {
            // Update with forecast results
            $this->recordForecastSuccess($run_id, $result['result']);
        } else {
            // Just mark as completed without stats
            $query = "UPDATE forecast_runs SET 
                        finished_at = NOW(),
                        status = 'completed'
                     WHERE id = ?";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $run_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    public function runForecast() {
        try {
            // Record forecast run start
            $run_id = $this->startForecastRun();
            
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
            
            // Update forecast run record
            if ($return_var === 0 && $result && $result['status'] === 'success') {
                $this->recordForecastSuccess($run_id, $result);
            } else {
                $error = implode("\n", $output);
                $this->recordForecastError($run_id, $error);
            }
            
            return [
                'success' => $return_var === 0,
                'output' => $output,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            $this->recordForecastError($run_id ?? null, $e->getMessage());
            throw $e;
        }
    }
    
    private function recordForecastSuccess($run_id, $result) {
        $query = "UPDATE forecast_runs SET 
                    finished_at = NOW(),
                    status = 'completed',
                    products_processed = ?,
                    forecasts_generated = ?,
                    anomalies_detected = ?
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiii", 
            $result['products_processed'] ?? 0,
            $result['forecasts_generated'] ?? 0,
            $result['anomalies_detected'] ?? 0,
            $run_id
        );
        $stmt->execute();
        $stmt->close();
    }
    
    public function recordForecastError($run_id, $error) {
        if (!$run_id) return;
        
        $query = "UPDATE forecast_runs SET 
                    finished_at = NOW(),
                    status = 'error',
                    error_message = ?
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $error, $run_id);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getLatestForecasts($product_id = null, $limit = 30) {
        $query = "SELECT 
                    f.*,
                    p.product_name,
                    p.category,
                    p.quantity as current_stock
                 FROM forecast f
                 JOIN products p ON f.product_id = p.product_id
                 WHERE f.forecast_date >= CURDATE()";
                 
        if ($product_id) {
            $query .= " AND f.product_id = " . intval($product_id);
        }
        
        $query .= " ORDER BY f.forecast_date ASC LIMIT " . intval($limit);
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            // Table might not exist or query failed
            return [];
        }
        
        $forecasts = [];
        
        while ($row = $result->fetch_assoc()) {
            $forecasts[] = $row;
        }
        
        return $forecasts;
    }
    
    public function getRecentAnomalies($days = 7) {
        $query = "SELECT 
                    a.*,
                    p.product_name,
                    p.category,
                    p.quantity as current_stock
                 FROM anomalies a
                 JOIN products p ON a.product_id = p.product_id
                 WHERE a.detection_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 ORDER BY a.anomaly_score ASC";
                 
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            // Table might not exist or query failed
            return [];
        }
        
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $anomalies = [];
        while ($row = $result->fetch_assoc()) {
            $anomalies[] = $row;
        }
        
        $stmt->close();
        return $anomalies;
    }
    
    public function getLastRunStatus() {
        $query = "SELECT * FROM forecast_runs 
                 ORDER BY started_at DESC LIMIT 1";
        $result = $this->conn->query($query);
        
        if (!$result) {
            // Table might not exist or query failed
            return null;
        }
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}