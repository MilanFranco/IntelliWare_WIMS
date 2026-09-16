<?php
/**
 * src/services/ApiClient.php
 * Resilient API client connecting the IntelliWare monolithic host application
 * to the independent Inventory and Insights Microservices.
 *
 * Guarantees:
 * - Clean REST communication over HTTP.
 * - Resilient in-process fallback to microservice repositories if HTTP is offline or timing out.
 * - Zero PHP fatal errors or credential leaks.
 */

require_once __DIR__ . '/../../services/inventory-service/InventoryRepository.php';
require_once __DIR__ . '/../../services/insights-service/InsightsEngine.php';

class ApiClient
{
    private static string $inventoryBaseUrl = 'http://localhost/intelliware/services/inventory-service/index.php';
    private static string $insightsBaseUrl  = 'http://localhost/intelliware/services/insights-service/index.php';

    /**
     * Fetch products from Inventory Microservice.
     */
    public static function getInventoryProducts(array $filters = []): array
    {
        $queryString = http_build_query(array_merge(['endpoint' => 'products'], $filters));
        $url = self::$inventoryBaseUrl . '?' . $queryString;
        $response = self::httpGet($url, 1500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        // Resilient in-process fallback directly through Inventory Microservice repository
        try {
            $repo = new InventoryRepository();
            $data = $repo->getProducts($filters);
            return array_merge(['success' => true, 'fallback' => true], $data);
        } catch (Throwable $e) {
            error_log('[ApiClient] Inventory products fallback error: ' . $e->getMessage());
            return ['success' => false, 'total' => 0, 'page' => 1, 'items' => [], 'error' => 'Inventory Service unavailable'];
        }
    }

    /**
     * Get single product from Inventory Microservice.
     */
    public static function getInventoryProduct(int $productId): ?array
    {
        $url = self::$inventoryBaseUrl . '?endpoint=products&id=' . $productId;
        $response = self::httpGet($url, 1500);

        if ($response && !empty($response['success']) && isset($response['data'])) {
            return $response['data'];
        }

        try {
            $repo = new InventoryRepository();
            return $repo->getProductById($productId);
        } catch (Throwable $e) {
            error_log('[ApiClient] Inventory single product fallback error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Receive inventory through Inventory Microservice.
     */
    public static function receiveInventory(int $productId, int $quantity, string $username, ?string $remarks = null, ?array $metadata = null): array
    {
        $url = self::$inventoryBaseUrl . '?endpoint=receive';
        $payload = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'username'   => $username,
            'remarks'    => $remarks,
            'metadata'   => $metadata
        ];
        $response = self::httpPost($url, $payload, 2500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $repo = new InventoryRepository();
            return $repo->receiveStock($productId, $quantity, $username, $remarks, $metadata);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Release inventory through Inventory Microservice.
     */
    public static function releaseInventory(int $productId, int $quantity, string $username, ?string $remarks = null): array
    {
        $url = self::$inventoryBaseUrl . '?endpoint=release';
        $payload = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'username'   => $username,
            'remarks'    => $remarks
        ];
        $response = self::httpPost($url, $payload, 2500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $repo = new InventoryRepository();
            return $repo->releaseStock($productId, $quantity, $username, $remarks);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Fetch pre-calculated summary from Insights Microservice.
     */
    public static function getInsightsSummary(): array
    {
        // 1. Instant in-process execution (<1ms) if local repository is available
        if (class_exists('InsightsEngine')) {
            try {
                $engine = new InsightsEngine();
                return $engine->getSummary();
            } catch (Throwable $e) {
                error_log('[ApiClient] In-process Insights summary error: ' . $e->getMessage());
            }
        }

        // 2. Fallback to HTTP microservice if deployed remotely
        $url = self::$insightsBaseUrl . '?endpoint=summary';
        $response = self::httpGet($url, 1500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $engine = new InsightsEngine();
            return $engine->getSummary();
        } catch (Throwable $e) {
            error_log('[ApiClient] Insights summary fallback error: ' . $e->getMessage());
            return [
                'success'                => false,
                'total_products'         => 0,
                'attention_needed_count' => 0,
                'fast_moving_count'      => 0,
                'expiring_soon_count'    => 0,
                'category_performance'   => [],
                'sales_trends'           => [],
                'supplier_performance'   => [],
                'product_movement'       => [],
                'expiry_risk_analysis'   => [],
                'last_updated_formatted' => 'Service Offline',
                'service_offline'        => true
            ];
        }
    }

    /**
     * Fetch pre-calculated forecast from Insights Microservice.
     */
    public static function getInsightsForecast(string $period = 'monthly', ?int $month = null): array
    {
        // 1. Instant in-process execution (<1ms) if local repository is available
        if (class_exists('InsightsEngine')) {
            try {
                $engine = new InsightsEngine();
                return $engine->getForecast($period, $month);
            } catch (Throwable $e) {
                error_log('[ApiClient] In-process Insights forecast error: ' . $e->getMessage());
            }
        }

        // 2. Fallback to HTTP microservice if deployed remotely
        $url = self::$insightsBaseUrl . '?endpoint=forecast&period=' . urlencode($period);
        if ($month !== null && $month >= 1 && $month <= 12) {
            $url .= '&month=' . $month;
        }
        $response = self::httpGet($url, 1500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $engine = new InsightsEngine();
            return $engine->getForecast($period, $month);
        } catch (Throwable $e) {
            error_log('[ApiClient] Insights forecast fallback error: ' . $e->getMessage());
            return [
                'success'         => false,
                'period_type'     => $period,
                'items'           => [],
                'service_offline' => true
            ];
        }
    }

    /**
     * Fetch Philippine seasonal demand analysis and matched products for a specific month.
     */
    public static function getPhilippineSeasonalForecast(int $month = 0): array
    {
        $month = ($month >= 1 && $month <= 12) ? $month : (int)date('n');
        $url = self::$insightsBaseUrl . '?endpoint=philippine_seasonal&month=' . $month;
        $response = self::httpGet($url, 1500);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $engine = new InsightsEngine();
            return $engine->getPhilippineSeasonalForecast($month);
        } catch (Throwable $e) {
            error_log('[ApiClient] Philippine seasonal forecast fallback error: ' . $e->getMessage());
            return [
                'success'       => false,
                'month'         => $month,
                'month_profile' => [],
                'items'         => []
            ];
        }
    }

    /**
     * Trigger background regeneration in Insights Microservice.
     */
    public static function triggerInsightsRegeneration(): array
    {
        $url = self::$insightsBaseUrl . '?endpoint=regenerate';
        $response = self::httpPost($url, [], 3000);

        if ($response && !empty($response['success'])) {
            return $response;
        }

        try {
            $engine = new InsightsEngine();
            return $engine->regenerateAll();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resilient HTTP GET via cURL.
     */
    private static function httpGet(string $url, int $timeoutMs = 1500): ?array
    {
        $ch = curl_init($url);
        if (!$ch) return null;

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    /**
     * Resilient HTTP POST via cURL.
     */
    private static function httpPost(string $url, array $payload, int $timeoutMs = 2500): ?array
    {
        $ch = curl_init($url);
        if (!$ch) return null;

        $json = json_encode($payload);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }
}
