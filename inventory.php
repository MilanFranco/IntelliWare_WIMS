<?php
/**
 * inventory.php
 * Canonical redirect to products.php (preserves query parameters).
 */
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: products.php' . $query, true, 301);
exit();
