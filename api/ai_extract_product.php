<?php
/**
 * api/ai_extract_product.php
 *
 * Multimodal Vision AI endpoint for packaging recognition and product detail extraction.
 * Extracts product name, brand, category, unit, and description from packaging photos
 * using Google Gemini Vision API (with automatic multi-model fallback).
 *
 * Request:
 *   POST JSON:
 *   {
 *     "image": "data:image/jpeg;base64,...",
 *     "action": "extract" | "test_connection"
 *   }
 *
 * Response:
 *   {
 *     "success": true,
 *     "data": {
 *       "product_name": "...",
 *       "brand": "...",
 *       "category": "...",
 *       "unit": "...",
 *       "description": "...",
 *       "confidence": 0.95
 *     }
 *   }
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json');
}

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notifications.php';

// Parse incoming request
$rawInput = file_get_contents('php://input');
if (!$rawInput) {
    $rawInput = file_get_contents('php://stdin');
}
$body     = json_decode($rawInput, true) ?? [];
$action   = $body['action'] ?? 'extract';

// Load AI vision settings from database
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'ai_vision_%'");
$visionSettings = [
    'ai_vision_enabled'  => '1',
    'ai_vision_provider' => 'gemini',
    'ai_vision_model'    => 'gemini-3.5-flash-lite',
    'ai_vision_api_key'  => ''
];
if ($settingsRes) {
    while ($row = $settingsRes->fetch_assoc()) {
        $visionSettings[$row['setting_key']] = $row['setting_value'];
    }
}

$apiKey  = trim($body['api_key'] ?? $visionSettings['ai_vision_api_key'] ?? '');
$enabled = ($visionSettings['ai_vision_enabled'] ?? '1') === '1';

// ── Action: Test Connection ───────────────────────────────────────────────────
if ($action === 'test_connection') {
    $conn->close();
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'API Key is empty. Please enter your Google Gemini API key.']);
        exit();
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'Connection verified successfully! Google Gemini Vision is active and ready.'
        ]);
    } else {
        $errObj = json_decode($response, true);
        $errMsg = $errObj['error']['message'] ?? "Connection failed with HTTP $httpCode.";
        echo json_encode(['success' => false, 'error' => $errMsg]);
    }
    exit();
}

// ── Action: Extract Product Details ──────────────────────────────────────────
if (!$enabled || empty($apiKey)) {
    echo json_encode([
        'success'  => false,
        'error'    => 'AI Vision is not enabled or API key is missing.',
        'fallback' => true
    ]);
    exit();
}

$rawImage = $body['image'] ?? '';
if (empty($rawImage)) {
    echo json_encode(['success' => false, 'error' => 'No image data provided.']);
    exit();
}

// Separate data URI header and base64 payload
$mimeType = 'image/jpeg';
if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $rawImage, $matches)) {
    $mimeType = $matches[1];
    $base64Data = $matches[2];
} else {
    $base64Data = $rawImage;
}

// Retrieve current categories from database for intelligent matching
$categories = [];
$catRes = $conn->query("SELECT name FROM categories ORDER BY name ASC");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categories[] = $r['name'];
    }
}
$conn->close();

if (empty($categories)) {
    $categories = ['Dairy Products', 'Beverages', 'Canned Goods', 'Dry Goods', 'Frozen Foods', 'Personal Care', 'Health & Wellness', 'Household Goods'];
}
$catListStr = implode(', ', array_map(fn($c) => '"' . addslashes($c) . '"', $categories));


// Build AI prompt
$prompt = "You are an expert retail packaging analyzer for an inventory management system.
Inspect this packaging photo carefully.
Identify the product and extract the key details.

Available Store Categories:
[{$catListStr}]

Instructions:
1. product_name: The clean, full, recognizable commercial product name as printed on packaging (e.g. 'Night Rider Premium Condoms', 'Coca-Cola Zero Sugar 330ml'). Do NOT output garbled characters or random letters.
2. brand: The brand or manufacturer name (e.g. 'Night Rider', 'Nestle', 'San Miguel'). If unknown, use the first word of product name.
3. category: Choose the single BEST matching category from the available store categories list above. If unsure, choose the closest fit (e.g., Personal Care or Dry Goods).
4. unit: Best packaging unit (e.g., 'Piece', 'Pack', 'Box', 'Bottle', 'Can', 'Sachet', 'Bag').
5. description: A concise 1-sentence description of the product.
6. confidence: A confidence score between 0.50 and 0.99 reflecting how clearly the product was identified.

Respond ONLY with a valid JSON object matching this exact schema:
{
  \"product_name\": \"...\",
  \"brand\": \"...\",
  \"category\": \"...\",
  \"unit\": \"...\",
  \"description\": \"...\",
  \"confidence\": 0.95
}";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt],
                [
                    "inline_data" => [
                        "mime_type" => $mimeType,
                        "data"      => $base64Data
                    ]
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature"        => 0.1,
        "response_mime_type" => "application/json"
    ]
];

// List of Gemini models to try in order of speed and capability
$selectedModel = $visionSettings['ai_vision_model'] ?? 'gemini-3.5-flash-lite';
$modelMap = [
    'gemini-1.5-flash' => 'gemini-3.5-flash-lite',
    'gemini-2.0-flash' => 'gemini-3.5-flash-lite',
    'gemini-2.5-flash' => 'gemini-3.5-flash-lite',
];
if (isset($modelMap[$selectedModel])) {
    $selectedModel = $modelMap[$selectedModel];
}

$modelsToTry = array_unique(array_filter([
    $selectedModel,
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-3.5-flash',
    'gemini-3.8-flash',
    'gemini-flash-latest'
]));

$extracted = null;
$lastError = 'Unknown error';
$usedModel = '';

foreach ($modelsToTry as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($response, true);
        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($rawText) {
            // Clean markdown blocks if any
            $cleanedJson = trim($rawText);
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $cleanedJson, $m)) {
                $cleanedJson = $m[1];
            }
            $parsed = json_decode($cleanedJson, true);
            if ($parsed && !empty($parsed['product_name'])) {
                $extracted = $parsed;
                $usedModel = $model;
                break;
            }
        }
    } else {
        $errObj = json_decode($response, true);
        $lastError = $errObj['error']['message'] ?? "HTTP $httpCode";
        // If 503 (temporary spike) or 404, loop will try next model
    }
}

if ($extracted) {
    echo json_encode([
        'success'  => true,
        'provider' => 'gemini',
        'model'    => $usedModel,
        'data'     => [
            'product_name' => trim($extracted['product_name'] ?? ''),
            'brand'        => trim($extracted['brand'] ?? ''),
            'category'     => trim($extracted['category'] ?? ''),
            'unit'         => trim($extracted['unit'] ?? 'Piece'),
            'description'  => trim($extracted['description'] ?? ''),
            'confidence'   => (float)($extracted['confidence'] ?? 0.90)
        ]
    ]);
} else {
    echo json_encode([
        'success'  => false,
        'error'    => $lastError,
        'fallback' => true
    ]);
}
