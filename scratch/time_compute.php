<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/insights-service/InsightsEngine.php';

$engine = new InsightsEngine($conn);

$t0 = microtime(true);
$resF = $engine->computeAndStoreForecasts();
$t1 = microtime(true);
echo "computeAndStoreForecasts took: " . round(($t1 - $t0) * 1000, 2) . " ms\n";

$t2 = microtime(true);
$resS = $engine->computeAndStoreSummary();
$t3 = microtime(true);
echo "computeAndStoreSummary took: " . round(($t3 - $t2) * 1000, 2) . " ms\n";
