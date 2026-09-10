<?php
/**
 * services/inventory-service/config/database.php
 * Independent database connection for the Inventory Microservice.
 */

function getInventoryDbConnection(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $host = getenv('INVENTORY_DB_HOST') ?: 'localhost';
        $user = getenv('INVENTORY_DB_USER') ?: 'root';
        $pass = getenv('INVENTORY_DB_PASS') !== false ? getenv('INVENTORY_DB_PASS') : '';
        $name = getenv('INVENTORY_DB_NAME') ?: 'warehouse_system';
        $port = (int)(getenv('INVENTORY_DB_PORT') ?: 3306);

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, $pass, $name, $port);

        if ($conn->connect_error) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Inventory Service: Unable to connect to database.',
                'service' => 'inventory-service'
            ]);
            exit();
        }

        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
