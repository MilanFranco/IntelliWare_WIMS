<?php
// Set a longer timeout for this script, as AI tasks can be slow.
set_time_limit(300); // 300 seconds = 5 minutes
header('Content-Type: application/json');

// --- DB Connection ---
$db_path = '../../config/db.php';
if (!file_exists($db_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'AI forecast failed: Server configuration error. Missing file at: ' . $db_path]);
    exit();
}
require_once $db_path; // $conn is now available

// Check if the $conn variable exists and if the connection was successful
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'AI forecast failed: Database connection error.', 'details' => isset($conn) ? $conn->connect_error : 'Connection variable not found after including db.php.']);
    exit();
}

$run_id = null;
$error_message = null;

try {
    // 1. Log the START of the forecast run
    $stmt_start = $conn->prepare("INSERT INTO forecast_runs (status) VALUES ('running')");
    $stmt_start->execute();
    $run_id = $conn->insert_id;
    $stmt_start->close();

    // 2. --- THIS IS WHERE WE CALL THE REAL AI LOGIC (PYTHON) ---
    
    // **IMPORTANT:** Update these paths to match your server setup!
    // $pythonPath = '/usr/bin/python3'; // Example for Linux
    $pythonPath = 'python'; // Assumes 'python' is in your server's PATH
    $scriptPath = __DIR__ . '/forecast.py'; // Path to the new Python script

    // Pass database credentials to the Python script as arguments
    $dbHost = escapeshellarg($dbHost);
    $dbUser = escapeshellarg($dbUser);
    $dbPass = escapeshellarg($dbPass);
    $dbName = escapeshellarg($dbName);

    // Build the command to execute
    $command = $pythonPath . ' ' . $scriptPath . ' ' . $dbHost . ' ' . $dbUser . ' ' . $dbPass . ' ' . $dbName;
    
    $output = null;
    $return_code = null;
    exec($command, $output, $return_code);

    // Check if the Python script executed successfully
    if ($return_code !== 0) {
        // Python script failed
        throw new Exception('Python script execution failed. Return code: ' . $return_code . '. Output: ' . implode(' ', $output));
    }

    // --- PYTHON SCRIPT SUCCEEDED ---
    // The Python script will have updated the 'product_recommendations' table.
    // We can get some stats from its output if we want, but for now, we just
    // parse its JSON output to get the number of products processed.
    
    $pyOutput = json_decode(implode('', $output), true);
    $products_processed = $pyOutput['products_processed'] ?? 0;
    $anomalies_detected = $pyOutput['anomalies_detected'] ?? 0; // Future use

    // 3. Log the COMPLETION of the forecast run
    $stmt_finish = $conn->prepare("
        UPDATE forecast_runs 
        SET 
            finished_at = NOW(), 
            status = 'completed', 
            products_processed = ?, 
            forecasts_generated = ?, 
            anomalies_detected = ?
        WHERE run_id = ?
    ");
    // We use $products_processed from Python, others are static for now
    $forecasts_generated = $products_processed * 12; // 12 months per product
    $stmt_finish->bind_param("iiii", $products_processed, $forecasts_generated, $anomalies_detected, $run_id);
    $stmt_finish->execute();
    $stmt_finish->close();
    
    $conn->close();

    // 4. Send a success response back to the JavaScript
    echo json_encode([
        'success' => true,
        'message' => 'AI forecast completed successfully. Processed ' . $products_processed . ' products.',
        'run_id' => $run_id,
        'products' => $products_processed,
        'forecasts' => $forecasts_generated
    ]);

} catch (Exception $e) {
    $error_message = $e->getMessage();
    
    if ($run_id) {
        $stmt_error = $conn->prepare("UPDATE forecast_runs SET finished_at = NOW(), status = 'error', error_message = ? WHERE run_id = ?");
        $stmt_error->bind_param("si", $error_message, $run_id);
        $stmt_error->execute();
        $stmt_error->close();
    }
    
    $conn->close();

    // Send an error response back to the JavaScript
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'AI forecast failed: ' . $error_message,
        'run_id' => $run_id
    ]);
}

exit();
?>