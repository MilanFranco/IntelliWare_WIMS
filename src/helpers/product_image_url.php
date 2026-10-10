<?php
/**
 * src/helpers/product_image_url.php
 *
 * Single shared helper: resolve the web-accessible URL for a product image.
 * Replaces 5 identical copy-pasted functions that were spread across the API
 * layer (get_product_by_id, search_products, search_products_by_text,
 * get_all_products_for_scanner, inventory_transaction_view).
 *
 * Scans uploads/products/ using three filename patterns
 * (numeric ID, "product_{id}", SKU "P-XXXX") x five extensions.
 *
 * Returns a relative web path (e.g. "uploads/products/42.jpg") or null.
 *
 * Include path from /api/ files:
 *   require_once __DIR__ . '/../src/helpers/product_image_url.php';
 *
 * Include path from /src/ files:
 *   require_once __DIR__ . '/helpers/product_image_url.php';
 */

if (!function_exists('getProductImageUrl')) {
    function getProductImageUrl(int $productId): ?string
    {
        // This file lives at src/helpers/ — project root is two levels up
        $baseDir = dirname(__FILE__, 3) . '/uploads/products/';
        $webBase = 'uploads/products/';

        $patterns = [
            (string)$productId,
            'product_' . $productId,
            'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
        ];
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach ($patterns as $name) {
            foreach ($extensions as $ext) {
                if (is_file($baseDir . $name . '.' . $ext)) {
                    return $webBase . $name . '.' . $ext;
                }
            }
        }
        return null;
    }
}

// Backward-compatibility aliases for legacy callers
if (!function_exists('productImageUrl')) {
    function productImageUrl(int $productId): ?string {
        return getProductImageUrl($productId);
    }
}

if (!function_exists('spGetProductImageUrl')) {
    function spGetProductImageUrl(int $productId): ?string {
        return getProductImageUrl($productId);
    }
}

if (!function_exists('scannerGetProductImageUrl')) {
    function scannerGetProductImageUrl(int $productId): ?string {
        return getProductImageUrl($productId);
    }
}

if (!function_exists('tmGetProductImageUrl')) {
    function tmGetProductImageUrl(int $productId): ?string {
        return getProductImageUrl($productId);
    }
}

