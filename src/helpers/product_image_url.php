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
    function getProductImageUrl(int $productId, bool $withCacheBuster = true): ?string
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
                $filePath = $baseDir . $name . '.' . $ext;
                if (is_file($filePath)) {
                    $mtime = @filemtime($filePath);
                    return $webBase . $name . '.' . $ext . ($withCacheBuster && $mtime ? '?v=' . $mtime : '');
                }
            }
        }
        return null;
    }
}

if (!function_exists('deleteProductImageFiles')) {
    function deleteProductImageFiles(int $productId): void
    {
        $baseDir = dirname(__FILE__, 3) . '/uploads/products/';
        $patterns = [
            (string)$productId,
            'product_' . $productId,
            'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
        ];
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach ($patterns as $name) {
            foreach ($extensions as $ext) {
                $filePath = $baseDir . $name . '.' . $ext;
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }
    }
}

if (!function_exists('saveProductImageFromBase64')) {
    function saveProductImageFromBase64(int $productId, string $rawB64): ?string
    {
        $rawB64 = trim($rawB64);
        if ($rawB64 === '') {
            return null;
        }

        // Strip data URI scheme prefix if present
        $cleanB64 = preg_replace('#^data:image/[^;]+;base64,#i', '', $rawB64);
        // Replace spaces with + caused by application/x-www-form-urlencoded form submission
        $cleanB64 = str_replace(' ', '+', $cleanB64);

        $imgData = base64_decode($cleanB64, true);
        if ($imgData === false || strlen($imgData) === 0) {
            throw new InvalidArgumentException('Invalid image encoding. Please upload a valid image.');
        }

        // Limit size to 8MB
        if (strlen($imgData) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('Image file size exceeds the 8MB limit.');
        }

        // Detect MIME type and validate image integrity
        $ext = null;
        $info = @getimagesizefromstring($imgData);
        if ($info !== false && !empty($info['mime'])) {
            $mimeMap = [
                'image/jpeg'  => 'jpg',
                'image/jpg'   => 'jpg',
                'image/pjpeg' => 'jpg',
                'image/png'   => 'png',
                'image/webp'  => 'webp',
            ];
            $mime = strtolower($info['mime']);
            if (isset($mimeMap[$mime])) {
                $ext = $mimeMap[$mime];
            }
        }

        // Fallback detection using fileinfo buffer if getimagesize didn't recognize it (e.g. some WEBP variations)
        if ($ext === null && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $imgData);
            finfo_close($finfo);
            if ($mime === 'image/webp') {
                $ext = 'webp';
            } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                $ext = 'jpg';
            } elseif ($mime === 'image/png') {
                $ext = 'png';
            }
        }

        if ($ext === null) {
            throw new InvalidArgumentException('The uploaded file is not a supported image format. Allowed: JPG, PNG, WEBP.');
        }

        // Clean up all existing image variants for this product before saving new one
        deleteProductImageFiles($productId);

        $baseDir = dirname(__FILE__, 3) . '/uploads/products/';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $destPath = $baseDir . $productId . '.' . $ext;
        if (file_put_contents($destPath, $imgData) === false) {
            throw new RuntimeException('Failed to save the image to disk. Please check directory permissions.');
        }

        return 'uploads/products/' . $productId . '.' . $ext;
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


