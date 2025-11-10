<?php
// Set error reporting for development (disable for production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, we'll handle them

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');
session_start();

// Function to send JSON error response
function sendError($code, $message) {
    http_response_code($code);
    ob_clean();
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Function to send JSON success response
function sendSuccess($data) {
    ob_clean();
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

try {
    // Check if user is logged in and has appropriate role
    if (!isset($_SESSION['Username'])) {
        sendError(401, 'Unauthorized');
    }

    $role = $_SESSION['Role'] ?? 'staff';
    if (!in_array($role, ['admin', 'manager'])) {
        sendError(403, 'Access denied');
    }

    // Check database connection file
    $dbPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbPath)) {
        sendError(500, 'Database configuration not found');
    }

    require_once $dbPath;
    require_once __DIR__ . '/MLForecastManager.php';
    require_once __DIR__ . '/ProductRecommendationManager.php';

    // Parse JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError(400, 'Invalid JSON input: ' . json_last_error_msg());
    }
    
    if (!isset($input['action']) || $input['action'] !== 'run_forecast') {
        sendError(400, 'Invalid request: action must be "run_forecast"');
    }

    // Initialize our ML managers
    $forecastManager = new MLForecastManager();
    $recommendationManager = new ProductRecommendationManager();
    
    // Start forecast run
    $runId = null;
    try {
        $runId = $forecastManager->startForecastRun();
    } catch (Exception $e) {
        sendError(500, 'Failed to start forecast run: ' . $e->getMessage());
    }
    
    // Run forecasts
    $forecastResult = null;
    try {
        $forecastResult = $forecastManager->generateForecasts();
    } catch (Exception $e) {
        // Mark run as error
        if ($runId) {
            try {
                $forecastManager->recordForecastError($runId, $e->getMessage());
            } catch (Exception $e2) {
                // Ignore errors in error handling
            }
        }
        sendError(500, 'Forecast generation failed: ' . $e->getMessage());
    }
    
    // Generate recommendations (optional, don't fail if this doesn't work)
    $recommendationResult = null;
    try {
        $recommendationResult = $recommendationManager->generateRecommendations();
    } catch (Exception $e) {
        // Log but don't fail - recommendations are optional
        error_log('Recommendation generation failed: ' . $e->getMessage());
    }
    
    // Mark forecast run as completed with results
    try {
        if ($forecastResult && $forecastResult['success']) {
            $forecastManager->completeForecastRun($runId, $forecastResult);
        } else {
            // Mark as error if forecast didn't succeed
            $errorMsg = 'Forecast completed but result indicates failure';
            if (isset($forecastResult['result']['error'])) {
                $errorMsg = $forecastResult['result']['error'];
            }
            $forecastManager->recordForecastError($runId, $errorMsg);
        }
    } catch (Exception $e) {
        error_log('Failed to complete forecast run: ' . $e->getMessage());
        // Try to record error if completion fails
        try {
            $forecastManager->recordForecastError($runId, 'Failed to complete: ' . $e->getMessage());
        } catch (Exception $e2) {
            // Ignore
        }
    }
    
    sendSuccess([
        'message' => 'Forecast completed successfully',
        'runId' => $runId,
        'forecast' => $forecastResult,
        'recommendations' => $recommendationResult
    ]);

} catch (Exception $e) {
    // Mark forecast run as error if runId exists
    if (isset($runId) && isset($forecastManager)) {
        try {
            $forecastManager->recordForecastError($runId, $e->getMessage());
        } catch (Exception $e2) {
            // Ignore errors in error handling
        }
    }
    
    sendError(500, 'Failed to run forecast: ' . $e->getMessage());
} catch (Error $e) {
    // Handle PHP 7+ errors (TypeError, etc.)
    sendError(500, 'Fatal error: ' . $e->getMessage());
}