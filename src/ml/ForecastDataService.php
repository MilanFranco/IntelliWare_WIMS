<?php
/**
 * Read-only service for loading ML forecast results into the dashboard.
 * PHP never trains models — it only reads saved predictions from MySQL.
 */

require_once __DIR__ . '/../../config/db.php';

class ForecastDataService
{
    private $conn;
    private static $tablesEnsured = false;

    public function __construct($connection = null)
    {
        global $conn;
        $this->conn = $connection ?? $conn;
        $this->ensureMlTables();
    }

    /**
     * Create ML tables if the migration was never run.
     */
    private function ensureMlTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        $statements = [
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "CREATE TABLE IF NOT EXISTS `ml_training_state` (
              `id` int(11) NOT NULL DEFAULT 1,
              `last_training_at` timestamp NULL DEFAULT NULL,
              `last_transaction_count` int(11) DEFAULT 0,
              `auto_retrain_threshold` int(11) DEFAULT 50,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "INSERT IGNORE INTO `ml_training_state` (`id`, `auto_retrain_threshold`) VALUES (1, 50)",
        ];

        foreach ($statements as $sql) {
            try {
                $this->conn->query($sql);
            } catch (mysqli_sql_exception $e) {
                // Table creation failed; safeQuery will handle missing tables gracefully.
            }
        }

        self::$tablesEnsured = true;
    }

    /**
     * Run a query, returning null on failure instead of throwing.
     */
    private function safeQuery(string $sql): ?mysqli_result
    {
        try {
            return $this->conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            return null;
        }
    }

    /**
     * Whether a trained model has produced results.
     */
    public function hasTrainedModel(): bool
    {
        $result = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM forecast_metrics
             WHERE model_type NOT LIKE '%_segments'"
        );
        if (!$result) {
            return false;
        }
        $row = $result->fetch_assoc();
        return (int)($row['cnt'] ?? 0) > 0;
    }

    /**
     * Latest training metrics (MAE, RMSE, MAPE, accuracy, error rate).
     */
    public function getLatestMetrics(): ?array
    {
        $result = $this->safeQuery(
            "SELECT * FROM forecast_metrics
             WHERE model_type NOT LIKE '%_segments'
             ORDER BY training_date DESC LIMIT 1"
        );
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }

    /**
     * Previous training run metrics for comparison.
     */
    public function getPreviousMetrics(): ?array
    {
        $result = $this->safeQuery(
            "SELECT * FROM forecast_metrics
             WHERE model_type NOT LIKE '%_segments'
             ORDER BY training_date DESC LIMIT 1 OFFSET 1"
        );
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }

    /**
     * Accuracy change vs previous training run.
     */
    public function getAccuracyTrend(): array
    {
        $current = $this->getLatestMetrics();
        $previous = $this->getPreviousMetrics();

        if (!$current) {
            return ['current' => null, 'previous' => null, 'change' => null, 'direction' => 'none'];
        }

        $currAcc = (float)($current['forecast_accuracy'] ?? 0);
        $prevAcc = $previous ? (float)($previous['forecast_accuracy'] ?? 0) : null;
        $change = $prevAcc !== null ? round($currAcc - $prevAcc, 2) : null;

        $direction = 'none';
        if ($change !== null) {
            $direction = $change > 0 ? 'improved' : ($change < 0 ? 'declined' : 'stable');
        }

        return [
            'current' => $currAcc,
            'previous' => $prevAcc,
            'change' => $change,
            'direction' => $direction,
            'error_rate' => (float)($current['error_rate'] ?? 0),
            'mae' => (float)($current['mae'] ?? 0),
            'rmse' => (float)($current['rmse'] ?? 0),
            'mape' => (float)($current['mape'] ?? 0),
            'model_type' => $current['model_type'] ?? 'unknown',
            'training_date' => $current['training_date'] ?? null,
        ];
    }

    /**
     * Segment accuracy for radar chart (Fast Moving, Medium, etc.).
     */
    public function getSegmentAccuracy(): array
    {
        $result = $this->safeQuery(
            "SELECT segment_accuracy_json FROM forecast_metrics
             WHERE segment_accuracy_json IS NOT NULL
             ORDER BY training_date DESC LIMIT 1"
        );

        $defaults = [
            'Fast Moving' => 0,
            'Medium' => 0,
            'Slow' => 0,
            'Seasonal' => 0,
            'New' => 0,
            'Promo' => 0,
        ];

        if (!$result || $result->num_rows === 0) {
            return $defaults;
        }

        $row = $result->fetch_assoc();
        $decoded = json_decode($row['segment_accuracy_json'], true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    /**
     * Seasonal demand chart data: historical + forecast + trend.
     */
    public function getSeasonalChartData(): array
    {
        $empty = [
            'available' => false,
            'labels' => [],
            'historical' => [],
            'forecasted' => [],
            'trend' => [],
            'seasonal_index' => array_fill(0, 12, 1.0),
            'month_labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ];

        if (!$this->hasTrainedModel()) {
            return $empty;
        }

        // Historical: last 6 months of actual sales
        $histResult = $this->conn->query(
            "SELECT DATE_FORMAT(t.transaction_date, '%b %Y') AS label,
                    MONTH(t.transaction_date) AS month_num,
                    YEAR(t.transaction_date) AS year_num,
                    SUM(t.quantity) AS total_qty
             FROM transactions t
             WHERE t.transaction_type = 'stock_out'
               AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY YEAR(t.transaction_date), MONTH(t.transaction_date)
             ORDER BY year_num, month_num"
        );

        $labels = [];
        $historical = [];
        $monthTotals = array_fill(1, 12, []);

        if ($histResult) {
            while ($row = $histResult->fetch_assoc()) {
                $labels[] = $row['label'];
                $historical[] = (float)$row['total_qty'];
                $monthTotals[(int)$row['month_num']][] = (float)$row['total_qty'];
            }
        }

        // Forecast: next 6 months from aggregate predictions (product_id = 0)
        $forecastResult = $this->conn->query(
            "SELECT DATE_FORMAT(forecast_date, '%b %Y') AS label,
                    MONTH(forecast_date) AS month_num,
                    YEAR(forecast_date) AS year_num,
                    SUM(predicted_quantity) AS predicted_qty
             FROM forecast_results
             WHERE product_id = 0
               AND forecast_date >= CURDATE()
             GROUP BY YEAR(forecast_date), MONTH(forecast_date)
             ORDER BY year_num, month_num
             LIMIT 6"
        );

        $forecasted = array_fill(0, count($historical), null);
        $forecastValues = [];

        if ($forecastResult) {
            while ($row = $forecastResult->fetch_assoc()) {
                $labels[] = $row['label'] . ' (F)';
                $historical[] = null;
                $val = (float)$row['predicted_qty'];
                $forecasted[] = $val;
                $forecastValues[] = $val;
            }
        }

        // Pad forecasted array to match labels length
        while (count($forecasted) < count($labels)) {
            $forecasted[] = end($forecastValues) ?: null;
        }

        // Trend line: simple linear projection over all non-null historical values
        $trend = $this->computeTrendLine($historical, count($forecastValues));

        // Seasonal index from historical monthly averages
        $seasonalIndex = [];
        $allAvgs = [];
        foreach ($monthTotals as $month => $vals) {
            if (!empty($vals)) {
                $allAvgs[$month] = array_sum($vals) / count($vals);
            }
        }
        $overallAvg = !empty($allAvgs) ? array_sum($allAvgs) / count($allAvgs) : 1;
        for ($m = 1; $m <= 12; $m++) {
            $seasonalIndex[] = isset($allAvgs[$m])
                ? round($allAvgs[$m] / $overallAvg, 2)
                : 1.0;
        }

        return [
            'available' => !empty($labels),
            'labels' => $labels,
            'historical' => $historical,
            'forecasted' => $forecasted,
            'trend' => $trend,
            'seasonal_index' => $seasonalIndex,
            'month_labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ];
    }

    /**
     * Sales forecast chart: historical daily + predicted daily (aggregate).
     */
    public function getSalesForecastChartData(int $daysLookback = 30): array
    {
        $empty = ['available' => false, 'labels' => [], 'historical' => [], 'predicted' => []];

        // Historical daily sales
        $stmt = $this->conn->prepare(
            "SELECT DATE(transaction_date) AS sale_date, SUM(quantity) AS total_qty
             FROM transactions
             WHERE transaction_type = 'stock_out'
               AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(transaction_date)
             ORDER BY sale_date ASC"
        );
        $stmt->bind_param('i', $daysLookback);
        $stmt->execute();
        $histResult = $stmt->get_result();

        $histLabels = [];
        $histValues = [];
        while ($row = $histResult->fetch_assoc()) {
            $histLabels[] = $row['sale_date'];
            $histValues[] = (float)$row['total_qty'];
        }
        $stmt->close();

        // Predicted daily (aggregate product_id = 0)
        $predResult = $this->conn->query(
            "SELECT forecast_date, predicted_quantity
             FROM forecast_results
             WHERE product_id = 0 AND forecast_date >= CURDATE()
             ORDER BY forecast_date ASC
             LIMIT 30"
        );

        $predLabels = [];
        $predValues = [];
        if ($predResult) {
            while ($row = $predResult->fetch_assoc()) {
                $predLabels[] = $row['forecast_date'];
                $predValues[] = (float)$row['predicted_quantity'];
            }
        }

        $hasPredictions = !empty($predValues);

        return [
            'available' => !empty($histValues) || $hasPredictions,
            'has_predictions' => $hasPredictions,
            'labels' => array_merge($histLabels, $predLabels),
            'historical' => array_merge($histValues, array_fill(0, count($predValues), null)),
            'predicted' => array_merge(array_fill(0, count($histValues), null), $predValues),
            'hist_count' => count($histValues),
        ];
    }

    /**
     * Product demand heatmap from real per-product forecasts.
     */
    public function getProductForecastDemand(int $limit = 8): array
    {
        $result = $this->conn->query(
            "SELECT p.product_name,
                    SUM(fr.predicted_quantity) AS forecast_demand
             FROM forecast_results fr
             INNER JOIN products p ON fr.product_id = p.product_id
             WHERE fr.product_id > 0
               AND fr.forecast_date >= CURDATE()
               AND fr.forecast_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY fr.product_id, p.product_name
             ORDER BY forecast_demand DESC
             LIMIT " . intval($limit)
        );

        $products = [];
        $demand = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row['product_name'];
                $demand[] = round((float)$row['forecast_demand'], 1);
            }
        }

        return [
            'available' => !empty($products),
            'labels' => $products,
            'demand' => $demand,
        ];
    }

    /**
     * Check if auto-retrain threshold is met (optional feature).
     */
    public function shouldAutoRetrain(): bool
    {
        $state = $this->conn->query("SELECT * FROM ml_training_state WHERE id = 1");
        if (!$state || $state->num_rows === 0) {
            return false;
        }
        $row = $state->fetch_assoc();

        $countResult = $this->conn->query("SELECT COUNT(*) AS cnt FROM transactions");
        $currentCount = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;
        $lastCount = (int)($row['last_transaction_count'] ?? 0);
        $threshold = (int)($row['auto_retrain_threshold'] ?? 50);

        return ($currentCount - $lastCount) >= $threshold;
    }

    public function updateTrainingState(): void
    {
        $countResult = $this->conn->query("SELECT COUNT(*) AS cnt FROM transactions");
        $currentCount = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;

        $stmt = $this->conn->prepare(
            "UPDATE ml_training_state
             SET last_training_at = NOW(), last_transaction_count = ?
             WHERE id = 1"
        );
        $stmt->bind_param('i', $currentCount);
        $stmt->execute();
        $stmt->close();
    }

    private function computeTrendLine(array $historical, int $forecastCount): array
    {
        $values = [];
        foreach ($historical as $v) {
            if ($v !== null) {
                $values[] = (float)$v;
            }
        }

        if (count($values) < 2) {
            $mean = !empty($values) ? $values[0] : 0;
            return array_fill(0, count($historical) + $forecastCount, $mean);
        }

        $n = count($values);
        $sumX = array_sum(range(0, $n - 1));
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }
        $denom = ($n * $sumX2 - $sumX * $sumX);
        $slope = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $totalPoints = count($historical) + $forecastCount;
        $trend = [];
        for ($i = 0; $i < $totalPoints; $i++) {
            $trend[] = round($intercept + $slope * $i, 2);
        }
        return $trend;
    }
}
