<?php
/**
 * config/app.php
 *
 * Central Application and Microservice URL Configuration.
 * Dynamically resolves application base URL and microservice endpoints,
 * supporting environment variables (.env / getenv) and automatic host/subfolder detection.
 */

date_default_timezone_set('Asia/Manila');

if (!defined('INTELLIWARE_APP_CONFIG_LOADED')) {
    define('INTELLIWARE_APP_CONFIG_LOADED', true);
}

if (!function_exists('formatActivityDate')) {
    /**
     * Format transaction/activity timestamps consistently as "M j, g:ia" (e.g. "Oct 17, 7:58am").
     * Shows the year only if the entry is from a different/previous year ("M j, Y, g:ia").
     *
     * @param string|int|DateTimeInterface|null $timestamp
     * @param bool $storedInUtc Set to true only if the input value is an unadjusted UTC string.
     * @return string
     */
    function formatActivityDate($timestamp, bool $storedInUtc = false): string
    {
        if (empty($timestamp)) {
            return '—';
        }

        try {
            if ($timestamp instanceof DateTimeInterface) {
                $dt = clone $timestamp;
                $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            } elseif (is_numeric($timestamp)) {
                $dt = new DateTime('@' . (int)$timestamp);
                $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            } else {
                $rawStr = trim((string)$timestamp);
                if ($storedInUtc) {
                    $dt = new DateTime($rawStr, new DateTimeZone('UTC'));
                    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                } else {
                    $dt = new DateTime($rawStr, new DateTimeZone('Asia/Manila'));
                }
            }

            $currentYear = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y');
            $entryYear = $dt->format('Y');

            if ($entryYear !== $currentYear) {
                return $dt->format('M j, Y, g:ia');
            }

            return $dt->format('M j, g:ia');
        } catch (Throwable $e) {
            return (string)$timestamp;
        }
    }
}

if (!function_exists('loadIntelliwareEnv')) {
    function loadIntelliwareEnv(): void
    {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) return;

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            $v = trim($v, "\"'");
            if (getenv($k) === false) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}
loadIntelliwareEnv();

if (!function_exists('getAppBaseUrl')) {
    function getAppBaseUrl(): string
    {
        // 1. Check environment variable (e.g. APP_URL)
        $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? null);
        if (!empty($envUrl)) {
            return rtrim($envUrl, '/');
        }

        // 2. Auto-detect from HTTP request
        if (!empty($_SERVER['HTTP_HOST'])) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $isHttps ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];

            // Find project directory segment from SCRIPT_NAME if hosted in a subfolder (e.g. /IntelliWare)
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $segments = array_values(array_filter(explode('/', $script)));
            $subfolder = '';
            if (!empty($segments)) {
                $first = $segments[0];
                if (!in_array(strtolower($first), ['api', 'services', 'src', 'tests', 'database', 'uploads', 'assets'])
                    && !str_ends_with(strtolower($first), '.php')) {
                    $subfolder = '/' . $first;
                }
            }

            return $scheme . $host . $subfolder;
        }

        // 3. CLI default fallback
        return 'http://localhost/IntelliWare';
    }
}

if (!function_exists('getInventoryServiceUrl')) {
    function getInventoryServiceUrl(): string
    {
        $custom = getenv('INVENTORY_SERVICE_URL') ?: ($_ENV['INVENTORY_SERVICE_URL'] ?? null);
        if (!empty($custom)) {
            return $custom;
        }
        return getAppBaseUrl() . '/services/inventory-service/index.php';
    }
}

if (!function_exists('getInsightsServiceUrl')) {
    function getInsightsServiceUrl(): string
    {
        $custom = getenv('INSIGHTS_SERVICE_URL') ?: ($_ENV['INSIGHTS_SERVICE_URL'] ?? null);
        if (!empty($custom)) {
            return $custom;
        }
        return getAppBaseUrl() . '/services/insights-service/index.php';
    }
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', getAppBaseUrl());
}

if (!defined('INVENTORY_SERVICE_URL')) {
    define('INVENTORY_SERVICE_URL', getInventoryServiceUrl());
}

if (!defined('INSIGHTS_SERVICE_URL')) {
    define('INSIGHTS_SERVICE_URL', getInsightsServiceUrl());
}
