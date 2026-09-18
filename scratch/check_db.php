<?php
require_once __DIR__ . '/../config/db.php';

echo "=== INSIGHT TABLES ===\n";
$r = $conn->query("SHOW TABLES LIKE '%insight%'");
while($row = $r->fetch_row()) echo $row[0] . "\n";

echo "\n=== ANOMALY TABLES ===\n";
$r = $conn->query("SHOW TABLES LIKE '%anomaly%'");
while($row = $r->fetch_row()) echo $row[0] . "\n";

echo "\n=== INSIGHT PREDICTIONS ROWS ===\n";
$r = $conn->query("SELECT period_type, period_key, computed_at FROM insight_predictions");
while($row = $r->fetch_assoc()) echo json_encode($row) . "\n";

echo "\n=== INSIGHT SUMMARY ROWS ===\n";
$r = $conn->query("SELECT summary_key, computed_at FROM insight_summary");
while($row = $r->fetch_assoc()) echo json_encode($row) . "\n";

echo "\n=== ANOMALY RESULTS ROWS ===\n";
$r = $conn->query("SELECT analysis_type, computed_at FROM anomaly_results");
while($row = $r->fetch_assoc()) echo json_encode($row) . "\n";

echo "\n=== LAST TRANSACTION ===\n";
$r = $conn->query("SELECT MAX(transaction_date) as last_txn FROM transactions");
$row = $r->fetch_assoc();
echo "Last Txn: " . $row['last_txn'] . "\n";
