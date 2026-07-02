<?php
/**
 * Migration script to create ML-related tables
 * Run this script once to set up the product_recommendations, forecast_runs, and anomalies tables
 */

require_once 'config/db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Setting up ML tables...\n\n";

// SQL statements to create tables
$sql_statements = [
    // Create product_recommendations table
    "CREATE TABLE IF NOT EXISTS `product_recommendations` (
      `recommendation_id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `month` int(11) NOT NULL,
      `avg_monthly_demand` decimal(10,2) DEFAULT NULL,
      `demand_std` decimal(10,2) DEFAULT NULL,
      `seasonal_strength` decimal(10,2) DEFAULT NULL,
      `recommended_stock` int(11) DEFAULT NULL,
      `restock_recommended` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`recommendation_id`),
      UNIQUE KEY `product_month` (`product_id`,`month`),
      KEY `product_id` (`product_id`),
      KEY `month` (`month`),
      CONSTRAINT `product_recommendations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // Create forecast_runs table
    "CREATE TABLE IF NOT EXISTS `forecast_runs` (
      `run_id` int(11) NOT NULL AUTO_INCREMENT,
      `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `finished_at` timestamp NULL DEFAULT NULL,
      `status` enum('running','completed','error') DEFAULT 'running',
      `products_processed` int(11) DEFAULT NULL,
      `forecasts_generated` int(11) DEFAULT NULL,
      `anomalies_detected` int(11) DEFAULT NULL,
      `error_message` text DEFAULT NULL,
      PRIMARY KEY (`run_id`),
      KEY `started_at` (`started_at`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    // Create anomalies table
    "CREATE TABLE IF NOT EXISTS `anomalies` (
      `anomaly_id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `detection_date` date NOT NULL,
      `quantity` decimal(10,2) DEFAULT NULL,
      `anomaly_score` decimal(10,4) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`anomaly_id`),
      KEY `product_id` (`product_id`),
      KEY `detection_date` (`detection_date`),
      KEY `anomaly_score` (`anomaly_score`),
      CONSTRAINT `anomalies_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // forecast_results — ML prediction outputs
    "CREATE TABLE IF NOT EXISTS `forecast_results` (
      `result_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL DEFAULT 0,
      `forecast_date` date NOT NULL,
      `predicted_quantity` decimal(12,2) NOT NULL,
      `confidence_score` decimal(6,4) DEFAULT NULL,
      `model_version` varchar(20) DEFAULT '1.0.0',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`result_id`),
      UNIQUE KEY `unique_forecast_result` (`product_id`,`forecast_date`),
      KEY `idx_forecast_date` (`forecast_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // forecast_metrics — training evaluation metrics
    "CREATE TABLE IF NOT EXISTS `forecast_metrics` (
      `metric_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `training_date` timestamp NOT NULL DEFAULT current_timestamp(),
      `mae` decimal(12,4) DEFAULT NULL,
      `rmse` decimal(12,4) DEFAULT NULL,
      `mape` decimal(8,4) DEFAULT NULL,
      `forecast_accuracy` decimal(6,2) DEFAULT NULL,
      `error_rate` decimal(6,2) DEFAULT NULL,
      `model_type` varchar(50) DEFAULT NULL,
      `model_version` varchar(20) DEFAULT '1.0.0',
      `products_processed` int(11) DEFAULT 0,
      `forecasts_generated` int(11) DEFAULT 0,
      `segment_accuracy_json` text DEFAULT NULL,
      PRIMARY KEY (`metric_id`),
      KEY `idx_training_date` (`training_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    // ml_training_state — auto-retrain tracking
    "CREATE TABLE IF NOT EXISTS `ml_training_state` (
      `id` int(11) NOT NULL DEFAULT 1,
      `last_training_at` timestamp NULL DEFAULT NULL,
      `last_transaction_count` int(11) DEFAULT 0,
      `auto_retrain_threshold` int(11) DEFAULT 50,
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "INSERT IGNORE INTO `ml_training_state` (`id`, `auto_retrain_threshold`) VALUES (1, 50);",
    
];

// Execute each SQL statement
$success_count = 0;
$error_count = 0;

foreach ($sql_statements as $index => $sql) {
    // For CREATE TABLE statements
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        $table_name = '';
        if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $sql, $matches)) {
            $table_name = $matches[1];
        }
        
        echo "✓ Created/verified table: $table_name\n";
        $success_count++;
    } else {
        $table_name = '';
        if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $sql, $matches)) {
            $table_name = $matches[1];
        }
        // Ignore "table already exists" errors
        if (strpos($conn->error, 'already exists') === false) {
            echo "✗ Error creating table $table_name: " . $conn->error . "\n";
            $error_count++;
        } else {
            echo "✓ Table $table_name already exists\n";
            $success_count++;
        }
    }
}

// Ensure forecast table has all required columns
echo "\nChecking forecast table columns...\n";
$check_forecast = $conn->query("SHOW TABLES LIKE 'forecast'");
if ($check_forecast && $check_forecast->num_rows > 0) {
    $forecast_columns_to_add = [
        'predicted_quantity' => "ALTER TABLE `forecast` ADD COLUMN `predicted_quantity` decimal(10,2) DEFAULT NULL AFTER `predicted_qty`",
        'lower_bound' => "ALTER TABLE `forecast` ADD COLUMN `lower_bound` decimal(10,2) DEFAULT NULL",
        'upper_bound' => "ALTER TABLE `forecast` ADD COLUMN `upper_bound` decimal(10,2) DEFAULT NULL",
        'model_used' => "ALTER TABLE `forecast` ADD COLUMN `model_used` varchar(50) DEFAULT NULL"
    ];

    foreach ($forecast_columns_to_add as $column_name => $alter_sql) {
        $check_col = $conn->query("SHOW COLUMNS FROM `forecast` LIKE '$column_name'");
        if ($check_col && $check_col->num_rows == 0) {
            if ($conn->query($alter_sql)) {
                echo "✓ Added column '$column_name' to forecast table\n";
                $success_count++;
            } else {
                // Check if error is because column already exists or other issue
                if (strpos($conn->error, 'Duplicate column name') === false) {
                    echo "✗ Error adding column '$column_name': " . $conn->error . "\n";
                    $error_count++;
                } else {
                    echo "✓ Column '$column_name' already exists in forecast table\n";
                    $success_count++;
                }
            }
        } else {
            echo "✓ Column '$column_name' already exists in forecast table\n";
            $success_count++;
        }
    }
} else {
    echo "Forecast table does not exist yet. It will be created when you run the main database setup.\n";
}

echo "\n=== Migration Summary ===\n";
echo "Successful operations: $success_count\n";
echo "Errors: $error_count\n";

if ($error_count == 0) {
    echo "\n✓ All ML tables have been set up successfully!\n";
} else {
    echo "\n⚠ Some errors occurred. Please check the messages above.\n";
}

$conn->close();
?>

