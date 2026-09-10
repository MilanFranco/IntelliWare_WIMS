<?php
/**
 * src/services/NotificationService.php
 *
 * Central Notification Orchestrator for IntelliWare.
 * Handles:
 * - Real-time transition detection (quantity > 0 -> 0)
 * - Deduplication (prevents repeated alerts when already at 0)
 * - Multi-channel dispatch:
 *   1. In-System notifications (stored in notifications table)
 *   2. Email notifications via EmailService (PHPMailer)
 *   3. SMS notifications via SmsService (Semaphore/Twilio)
 * - Audit logging of channel dispatch outcomes (SENT, FAILED)
 * - Idempotent database migrations
 */

require_once __DIR__ . '/../../config/notifications.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/SmsService.php';

class NotificationService
{
    private static bool $tablesEnsured = false;

    /**
     * Idempotent table check and upgrade.
     */
    public static function ensureTables(mysqli $conn): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        try {
            // 1. Ensure system_settings table
            $conn->query("
                CREATE TABLE IF NOT EXISTS `system_settings` (
                    `setting_key` VARCHAR(64) PRIMARY KEY,
                    `setting_value` TEXT NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 2. Ensure notifications table has all required columns
            $colsRes = $conn->query("SHOW COLUMNS FROM `notifications`");
            $existingCols = [];
            if ($colsRes) {
                while ($c = $colsRes->fetch_assoc()) {
                    $existingCols[strtolower($c['Field'])] = true;
                }
            }

            if (!isset($existingCols['product_id'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `product_id` INT(11) NULL AFTER `id`");
            }
            if (!isset($existingCols['notification_type'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `notification_type` VARCHAR(50) NOT NULL DEFAULT 'OUT_OF_STOCK' AFTER `user_id`");
            }
            if (!isset($existingCols['title'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `title` VARCHAR(255) NULL AFTER `notification_type`");
            }
            if (!isset($existingCols['channel'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `channel` ENUM('SYSTEM','EMAIL','SMS') NOT NULL DEFAULT 'SYSTEM' AFTER `message`");
            }
            if (!isset($existingCols['status'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `status` ENUM('PENDING','SENT','FAILED','READ') NOT NULL DEFAULT 'SENT' AFTER `channel`");
            }
            if (!isset($existingCols['sent_at'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`");
            }
            if (!isset($existingCols['error_message'])) {
                $conn->query("ALTER TABLE `notifications` ADD COLUMN `error_message` TEXT NULL AFTER `sent_at`");
            }

            self::$tablesEnsured = true;
        } catch (Throwable $e) {
            error_log('[NotificationService] ensureTables error: ' . $e->getMessage());
        }
    }

    /**
     * Trigger real-time Out-of-Stock alert across all enabled channels.
     * Guaranteed:
     * - Only triggers when transitioning to 0.
     * - Does NOT roll back inventory if external email/SMS fails.
     * - Dispatched after MySQL commit.
     *
     * @return array Summary of channel dispatch results
     */
    public static function triggerOutOfStockAlert(
        mysqli $conn,
        array $product,
        string $username,
        int $transactionId = 0
    ): array {
        self::ensureTables($conn);

        $productId   = (int)($product['product_id'] ?? 0);
        $productName = $product['product_name'] ?? 'Product';
        $userId      = self::getUserIdByUsername($conn, $username);

        if ($productId <= 0) {
            return ['status' => 'ignored', 'reason' => 'Invalid product ID'];
        }

        // Deduplication Check:
        // Prevent repeated alerts if this product already had an OUT_OF_STOCK alert
        // created since its last stock_in replenishment.
        if (self::isDuplicateOutOfStock($conn, $productId)) {
            return ['status' => 'skipped', 'reason' => 'Product already marked out of stock without replenishment.'];
        }

        $settings = getAllNotificationSettings($conn);
        $results = [
            'product_id'   => $productId,
            'product_name' => $productName,
            'channels'     => []
        ];

        $now = date('Y-m-d H:i:s');
        $systemMessage = "OUT OF STOCK: {$productName} is now out of stock. Current stock: 0.";
        $metaJson = json_encode([
            'product_id'     => $productId,
            'product_name'   => $productName,
            'sku'            => $product['sku'] ?? ('P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT)),
            'barcode'        => $product['barcode'] ?? null,
            'location'       => $product['location'] ?? null,
            'transaction_id' => $transactionId,
            'stock'          => 0,
            'triggered_by'   => $username
        ]);

        // 1. IN-SYSTEM NOTIFICATION CHANNEL
        $systemEnabled = ($settings['notif_system_enabled'] ?? '1') === '1';
        if ($systemEnabled) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at)
                    VALUES (?, ?, 'OUT_OF_STOCK', 'OUT OF STOCK', ?, 'SYSTEM', 'SENT', ?, 0, ?, ?)
                ");
                $stmt->bind_param('iissss', $productId, $userId, $systemMessage, $metaJson, $now, $now);
                $stmt->execute();
                $systemNotifId = (int)$conn->insert_id;
                $stmt->close();

                $results['channels']['SYSTEM'] = [
                    'success' => true,
                    'id'      => $systemNotifId,
                    'status'  => 'SENT'
                ];
            } catch (Throwable $e) {
                error_log('[NotificationService] Failed to insert in-system notification: ' . $e->getMessage());
                $results['channels']['SYSTEM'] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'status'  => 'FAILED'
                ];
            }
        }

        // 2. EMAIL NOTIFICATION CHANNEL
        $emailEnabled = ($settings['notif_email_enabled'] ?? '1') === '1';
        if ($emailEnabled) {
            try {
                $emailRes = EmailService::sendOutOfStockAlert($conn, $product);
                $emailStatus = $emailRes['success'] ? 'SENT' : 'FAILED';
                $emailErr = $emailRes['error'] ?? null;
                $sentAt = $emailRes['success'] ? $now : null;

                $emailTitle = '[IntelliWare] Product Out of Stock Alert';
                $emailMsg = "Product Out of Stock: {$productName} is currently out of stock (Stock: 0).";

                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at, error_message)
                    VALUES (?, ?, 'OUT_OF_STOCK', ?, ?, 'EMAIL', ?, ?, 0, ?, ?, ?)
                ");
                $stmt->bind_param('iisssssss', $productId, $userId, $emailTitle, $emailMsg, $emailStatus, $metaJson, $now, $sentAt, $emailErr);
                $stmt->execute();
                $stmt->close();

                $results['channels']['EMAIL'] = [
                    'success' => $emailRes['success'],
                    'error'   => $emailErr,
                    'sent_to' => $emailRes['sent_to'] ?? []
                ];
            } catch (Throwable $e) {
                error_log('[NotificationService] Email dispatch exception: ' . $e->getMessage());
                $results['channels']['EMAIL'] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // 3. SMS NOTIFICATION CHANNEL
        $smsEnabled = ($settings['notif_sms_enabled'] ?? '1') === '1';
        if ($smsEnabled) {
            try {
                $smsRes = SmsService::sendOutOfStockAlert($conn, $product);
                $smsStatus = $smsRes['success'] ? 'SENT' : 'FAILED';
                $smsErr = $smsRes['error'] ?? null;
                $sentAt = $smsRes['success'] ? $now : null;

                $smsTitle = 'IntelliWare Alert';
                $smsMsg = "IntelliWare Alert: {$productName} is OUT OF STOCK. Current stock: 0. Please restock.";

                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at, error_message)
                    VALUES (?, ?, 'OUT_OF_STOCK', ?, ?, 'SMS', ?, ?, 0, ?, ?, ?)
                ");
                $stmt->bind_param('iisssssss', $productId, $userId, $smsTitle, $smsMsg, $smsStatus, $metaJson, $now, $sentAt, $smsErr);
                $stmt->execute();
                $stmt->close();

                $results['channels']['SMS'] = [
                    'success'  => $smsRes['success'],
                    'provider' => $smsRes['provider'] ?? 'none',
                    'error'    => $smsErr,
                    'sent_to'  => $smsRes['sent_to'] ?? []
                ];
            } catch (Throwable $e) {
                error_log('[NotificationService] SMS dispatch exception: ' . $e->getMessage());
                $results['channels']['SMS'] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Deduplication checker: Returns true if product is already in an active out-of-stock state.
     */
    private static function isDuplicateOutOfStock(mysqli $conn, int $productId): bool
    {
        // Find the date of the latest stock_in for this product
        $lastStockInDate = null;
        $inStmt = $conn->prepare("
            SELECT transaction_date 
            FROM transactions 
            WHERE product_id = ? AND transaction_type = 'stock_in' 
            ORDER BY transaction_date DESC, transaction_id DESC 
            LIMIT 1
        ");
        if ($inStmt) {
            $inStmt->bind_param('i', $productId);
            $inStmt->execute();
            $inRes = $inStmt->get_result()->fetch_assoc();
            $inStmt->close();
            if ($inRes) {
                $lastStockInDate = $inRes['transaction_date'];
            }
        }

        // If there was a previous OUT_OF_STOCK notification, check if it happened AFTER the last stock_in
        if ($lastStockInDate !== null) {
            $notifStmt = $conn->prepare("
                SELECT id FROM notifications 
                WHERE product_id = ? AND notification_type = 'OUT_OF_STOCK' AND channel = 'SYSTEM' AND created_at >= ?
                LIMIT 1
            ");
            $notifStmt->bind_param('is', $productId, $lastStockInDate);
        } else {
            // No stock_in ever recorded; check if any OUT_OF_STOCK notification already exists
            $notifStmt = $conn->prepare("
                SELECT id FROM notifications 
                WHERE product_id = ? AND notification_type = 'OUT_OF_STOCK' AND channel = 'SYSTEM'
                LIMIT 1
            ");
            $notifStmt->bind_param('i', $productId);
        }

        if (!$notifStmt) {
            return false;
        }

        $notifStmt->execute();
        $exists = $notifStmt->get_result()->num_rows > 0;
        $notifStmt->close();

        return $exists;
    }

    /**
     * Resolve user ID safely.
     */
    private static function getUserIdByUsername(mysqli $conn, string $username): ?int
    {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['user_id'])) {
            return (int)$row['user_id'];
        }
        $fallback = $conn->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
        if ($fallback && $fRow = $fallback->fetch_assoc()) {
            return (int)$fRow['user_id'];
        }
        return null;
    }
}
