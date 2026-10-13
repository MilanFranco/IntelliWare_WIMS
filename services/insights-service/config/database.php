<?php
/**
 * services/insights-service/config/database.php
 * Independent database connection for the Insights Microservice.
 */

function getInsightsDbConnection(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $host = getenv('INSIGHTS_DB_HOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $user = getenv('INSIGHTS_DB_USER') ?: (getenv('DB_USER') ?: 'root');
        $pass = getenv('INSIGHTS_DB_PASS') !== false ? getenv('INSIGHTS_DB_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''));
        $name = getenv('INSIGHTS_DB_NAME') ?: (getenv('DB_NAME') ?: 'warehouse_system');
        $port = (int)(getenv('INSIGHTS_DB_PORT') ?: (getenv('DB_PORT') ?: 3306));

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, $pass, $name, $port);

        if ($conn->connect_error) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Insights Service: Unable to connect to database.',
                'service' => 'insights-service'
            ]);
            exit();
        }

        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
