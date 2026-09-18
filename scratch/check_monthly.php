<?php
require_once __DIR__ . '/../config/db.php';

$r = $conn->query("SELECT predictions_data FROM insight_predictions WHERE period_type='monthly' AND period_key='2026-09'");
$row = $r->fetch_assoc();
$data = json_decode($row['predictions_data'], true);
echo "Items count in monthly forecast: " . count($data['items'] ?? []) . "\n";
$found = false;
foreach ($data['items'] as $it) {
    echo "- " . $it['product_name'] . " | Stock: " . $it['current_stock'] . " | Type: " . $it['type'] . "\n";
    if (stripos($it['product_name'], 'Originote') !== false) {
        $found = true;
    }
}
if (!$found) {
    echo "Originote is NOT in the monthly forecast items!\n";
}
