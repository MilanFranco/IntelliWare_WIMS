<?php
/**
 * src/services/NotificationService.php
 *
 * Central Notification Orchestrator for IntelliWare IMS.
 * Handles:
 * - Real-time threshold crossing detection:
 *     * Low Stock: Drops from > reorder_point to <= reorder_point (> 0)
 *     * Out of Stock: Drops from > 0 to = 0 (urgent)
 * - Cooldown & anti-spam enforcement (e.g. 24-hour cooldown per product)
 * - Multi-product batch digest consolidation for bulk stock-outs
 * - Multi-channel dispatch:
 *     1. In-System notifications (stored in notifications table for UI bell/badges)
 *     2. Email notifications via EmailService (PHPMailer with dynamic recipient resolution)
 * - Fail-safe non-blocking execution & audit logging
 * - Idempotent database schema upgrades
 */

require_once __DIR__ . '/../../config/notifications.php';
require_once __DIR__ . '/EmailService.php';

class NotificationService
{
    private static bool $tablesEnsured = false;

    /**
     * Idempotent table check and schema upgrade.
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

            // 2. Ensure users table has notification preferences
            $userColsRes = $conn->query("SHOW COLUMNS FROM `users`");
            $existingUserCols = [];
            if ($userColsRes) {
                while ($c = $userColsRes->fetch_assoc()) {
                    $existingUserCols[strtolower($c['Field'])] = true;
                }
            }

            if (!isset($existingUserCols['notif_email_enabled'])) {
                $conn->query("ALTER TABLE `users` ADD COLUMN `notif_email_enabled` TINYINT(1) NULL DEFAULT NULL AFTER `email`");
            }
            if (!isset($existingUserCols['notification_email'])) {
                $conn->query("ALTER TABLE `users` ADD COLUMN `notification_email` VARCHAR(100) NULL DEFAULT NULL AFTER `notif_email_enabled`");
            }

            // 3. Ensure notifications table has all required columns
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

            // Index for cooldown & fast lookups
            $idxRes = $conn->query("SHOW INDEX FROM `notifications` WHERE Key_name = 'idx_notif_cooldown'");
            if (!$idxRes || $idxRes->num_rows === 0) {
                @$conn->query("CREATE INDEX `idx_notif_cooldown` ON `notifications` (`product_id`, `channel`, `status`, `created_at`)");
            }

            // 4. Ensure notification_queue table for reliable queuing
            $conn->query("
                CREATE TABLE IF NOT EXISTS `notification_queue` (
                    `queue_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT(11) NOT NULL,
                    `alert_type` VARCHAR(32) NOT NULL,
                    `previous_stock` INT(11) NOT NULL,
                    `new_stock` INT(11) NOT NULL,
                    `reorder_point` INT(11) NOT NULL,
                    `payload` LONGTEXT NULL,
                    `status` ENUM('PENDING','PROCESSING','SENT','FAILED','DISCARDED') NOT NULL DEFAULT 'PENDING',
                    `error_message` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `processed_at` TIMESTAMP NULL DEFAULT NULL,
                    INDEX `idx_queue_status` (`status`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            self::$tablesEnsured = true;
        } catch (Throwable $e) {
            error_log('[NotificationService] ensureTables error: ' . $e->getMessage());
        }
    }

    /**
     * Determine whether an inventory movement crossed an alert threshold.
     *
     * Returns:
     * - 'OUT_OF_STOCK': When quantity reaches 0 from > 0
     * - 'LOW_STOCK': When quantity drops from > reorder_point to <= reorder_point (> 0)
     * - null: If already below threshold, or replenishing, or stock didn't cross
     *
     * @param int $previousStock
     * @param int $newStock
     * @param int $reorderPoint
     * @return string|null
     */
    public static function evaluateThresholdCrossing(int $previousStock, int $newStock, int $reorderPoint): ?string
    {
        // 1. Most urgent: reached 0
        if ($previousStock > 0 && $newStock === 0) {
            return 'OUT_OF_STOCK';
        }

        // 2. Crossed to or below reorder point (and still > 0)
        if ($previousStock > $reorderPoint && $newStock <= $reorderPoint && $newStock > 0) {
            return 'LOW_STOCK';
        }

        return null;
    }

    /**
     * Check if a product is under email notification cooldown.
     *
     * Rules:
     * - Minimum cooldown period (default 24 hours, or notif_cooldown_hours).
     * - For LOW_STOCK: checks if any email alert (LOW_STOCK or OUT_OF_STOCK) was sent in the last N hours.
     * - For OUT_OF_STOCK: checks if an OUT_OF_STOCK email alert was sent in the last N hours.
     *   (If a low stock email was sent earlier, reaching 0 is more urgent and permitted once).
     *
     * @param mysqli $conn
     * @param int $productId
     * @param string $alertType 'OUT_OF_STOCK' or 'LOW_STOCK'
     * @param int|null $cooldownHours
     * @return bool True if under cooldown (suppress email), false otherwise
     */
    public static function isUnderCooldown(
        mysqli $conn,
        int $productId,
        string $alertType,
        ?int $cooldownHours = null
    ): bool {
        if ($productId <= 0) return false;

        if ($cooldownHours === null) {
            $settings = getAllNotificationSettings($conn);
            $cooldownHours = max(1, (int)($settings['notif_cooldown_hours'] ?? 24));
        }

        try {
            if ($alertType === 'OUT_OF_STOCK') {
                $stmt = $conn->prepare("
                    SELECT id FROM notifications 
                    WHERE product_id = ? 
                      AND channel = 'EMAIL' 
                      AND status = 'SENT'
                      AND notification_type = 'OUT_OF_STOCK'
                      AND created_at >= (NOW() - INTERVAL ? HOUR)
                    LIMIT 1
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT id FROM notifications 
                    WHERE product_id = ? 
                      AND channel = 'EMAIL' 
                      AND status = 'SENT'
                      AND created_at >= (NOW() - INTERVAL ? HOUR)
                    LIMIT 1
                ");
            }

            if (!$stmt) return false;
            $stmt->bind_param('ii', $productId, $cooldownHours);
            $stmt->execute();
            $hasRow = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            return $hasRow;
        } catch (Throwable $e) {
            error_log('[NotificationService] isUnderCooldown error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle real-time alert for a single inventory stock movement.
     *
     * Dispatches:
     * - In-System notification (for bell badge / dropdown)
     * - SMTP Email notification (if threshold crossed and not in cooldown)
     *
     * @param mysqli $conn
     * @param array $product
     * @param int $previousStock
     * @param int $newStock
     * @param string $username
     * @param int $transactionId
     * @return array
     */
    public static function handleStockMovementAlert(
        mysqli $conn,
        array $product,
        int $previousStock,
        int $newStock,
        string $username,
        int $transactionId = 0
    ): array {
        self::ensureTables($conn);

        $productId    = (int)($product['product_id'] ?? 0);
        $productName  = $product['product_name'] ?? 'Product';
        $reorderPoint = (int)($product['reorder_point'] ?? 10);
        $userId       = self::getUserIdByUsername($conn, $username);

        $alertType = self::evaluateThresholdCrossing($previousStock, $newStock, $reorderPoint);
        if (!$alertType) {
            return ['status' => 'no_threshold_crossed'];
        }

        $now = date('Y-m-d H:i:s');
        $settings = getAllNotificationSettings($conn);
        $metaJson = json_encode([
            'product_id'     => $productId,
            'product_name'   => $productName,
            'category'       => $product['category'] ?? 'General',
            'sku'            => $product['sku'] ?? ('P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT)),
            'location'       => $product['location'] ?? null,
            'previous_stock' => $previousStock,
            'new_stock'      => $newStock,
            'reorder_point'  => $reorderPoint,
            'transaction_id' => $transactionId,
            'alert_type'     => $alertType,
            'triggered_by'   => $username
        ]);

        $results = [
            'product_id'   => $productId,
            'product_name' => $productName,
            'alert_type'   => $alertType,
            'channels'     => []
        ];

        // 1. IN-SYSTEM NOTIFICATION (powers top-bar bell & badge)
        $systemEnabled = ($settings['notif_system_enabled'] ?? '1') === '1';
        if ($systemEnabled) {
            try {
                $sysTitle = ($alertType === 'OUT_OF_STOCK') ? 'OUT OF STOCK' : 'LOW STOCK';
                $sysMsg = ($alertType === 'OUT_OF_STOCK')
                    ? "OUT OF STOCK: {$productName} is now out of stock (Stock: 0)."
                    : "LOW STOCK: {$productName} is at or below reorder point (Stock: {$newStock}, Reorder: {$reorderPoint}).";

                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at)
                    VALUES (?, ?, ?, ?, ?, 'SYSTEM', 'SENT', ?, 0, ?, ?)
                ");
                $stmt->bind_param('iissssss', $productId, $userId, $alertType, $sysTitle, $sysMsg, $metaJson, $now, $now);
                $stmt->execute();
                $sysNotifId = (int)$conn->insert_id;
                $stmt->close();

                $results['channels']['SYSTEM'] = ['success' => true, 'id' => $sysNotifId];
            } catch (Throwable $e) {
                error_log('[NotificationService] System notif insert error: ' . $e->getMessage());
                $results['channels']['SYSTEM'] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // 2. EMAIL NOTIFICATION CHANNEL
        $emailEnabled = ($settings['notif_email_enabled'] ?? '1') === '1';
        if ($emailEnabled) {
            // Check Cooldown
            if (self::isUnderCooldown($conn, $productId, $alertType)) {
                $results['channels']['EMAIL'] = [
                    'success' => false,
                    'status'  => 'COOLDOWN_SUPPRESSED',
                    'reason'  => 'Notification suppressed by 24h cooldown period.'
                ];
            } else {
                try {
                    $product['quantity'] = $newStock;
                    $product['reorder_point'] = $reorderPoint;

                    $emailRes = EmailService::sendStockAlertEmail($conn, $product, $alertType);
                    $emailStatus = $emailRes['success'] ? 'SENT' : 'FAILED';
                    $emailErr = $emailRes['error'] ?? null;
                    $sentAt = $emailRes['success'] ? $now : null;

                    $emailTitle = ($alertType === 'OUT_OF_STOCK')
                        ? "IntelliWare Alert: {$productName} is OUT OF STOCK"
                        : "IntelliWare Alert: {$productName} is LOW ON STOCK ({$newStock} remaining)";
                    $emailMsg = ($alertType === 'OUT_OF_STOCK')
                        ? "Product Out of Stock: {$productName} reached 0 units."
                        : "Low Stock: {$productName} has {$newStock} units left (Reorder point: {$reorderPoint}).";

                    $stmt = $conn->prepare("
                        INSERT INTO notifications 
                        (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at, error_message)
                        VALUES (?, ?, ?, ?, ?, 'EMAIL', ?, ?, 0, ?, ?, ?)
                    ");
                    $stmt->bind_param('iissssssss', $productId, $userId, $alertType, $emailTitle, $emailMsg, $emailStatus, $metaJson, $now, $sentAt, $emailErr);
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
        }

        return $results;
    }

    /**
     * Handle batch stock movement alerts.
     *
     * Consolidates qualifying items from a bulk stock-out into a single digest email.
     *
     * @param mysqli $conn
     * @param array<int, array> $movementResults
     * @param string $username
     * @return array
     */
    public static function handleBatchMovementAlert(
        mysqli $conn,
        array $movementResults,
        string $username
    ): array {
        self::ensureTables($conn);

        $now = date('Y-m-d H:i:s');
        $settings = getAllNotificationSettings($conn);
        $userId = self::getUserIdByUsername($conn, $username);

        $emailBatchItems = [];
        $recordedTransitions = [];

        foreach ($movementResults as $res) {
            $prod = $res['product'] ?? [];
            $productId = (int)($prod['product_id'] ?? 0);
            if ($productId <= 0) continue;

            $prev = (int)($res['previous_stock'] ?? 0);
            $new  = (int)($res['new_stock'] ?? 0);
            $rp   = (int)($prod['reorder_point'] ?? 10);

            $alertType = self::evaluateThresholdCrossing($prev, $new, $rp);
            if (!$alertType) continue;

            // 1. In-System notification recorded for every qualifying item
            $sysEnabled = ($settings['notif_system_enabled'] ?? '1') === '1';
            if ($sysEnabled) {
                try {
                    $prodName = $prod['product_name'] ?? 'Product';
                    $sysTitle = ($alertType === 'OUT_OF_STOCK') ? 'OUT OF STOCK' : 'LOW STOCK';
                    $sysMsg = ($alertType === 'OUT_OF_STOCK')
                        ? "OUT OF STOCK: {$prodName} is now out of stock (Stock: 0)."
                        : "LOW STOCK: {$prodName} is at or below reorder point (Stock: {$new}, Reorder: {$rp}).";
                    $meta = json_encode([
                        'product_id'     => $productId,
                        'product_name'   => $prodName,
                        'previous_stock' => $prev,
                        'new_stock'      => $new,
                        'reorder_point'  => $rp,
                        'transaction_id' => $res['transaction_id'] ?? 0,
                        'alert_type'     => $alertType,
                        'triggered_by'   => $username
                    ]);

                    $stmt = $conn->prepare("
                        INSERT INTO notifications 
                        (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at)
                        VALUES (?, ?, ?, ?, ?, 'SYSTEM', 'SENT', ?, 0, ?, ?)
                    ");
                    $stmt->bind_param('iissssss', $productId, $userId, $alertType, $sysTitle, $sysMsg, $meta, $now, $now);
                    $stmt->execute();
                    $stmt->close();
                } catch (Throwable $e) {
                    error_log('[NotificationService] Batch in-system alert insert error: ' . $e->getMessage());
                }
            }

            // 2. Check cooldown for email
            if (!self::isUnderCooldown($conn, $productId, $alertType)) {
                $emailBatchItems[] = [
                    'product'    => $prod,
                    'alert_type' => $alertType,
                    'transaction_id' => $res['transaction_id'] ?? 0
                ];
                $recordedTransitions[] = [
                    'product_id' => $productId,
                    'alert_type' => $alertType,
                    'product_name' => $prod['product_name'] ?? 'Product'
                ];
            }
        }

        // If no items qualify for email (or all in cooldown), finish
        $emailEnabled = ($settings['notif_email_enabled'] ?? '1') === '1';
        if (!$emailEnabled || empty($emailBatchItems)) {
            return [
                'success' => true,
                'email_dispatched' => false,
                'items_count' => count($recordedTransitions)
            ];
        }

        // Dispatch email: single if 1, or consolidated digest if > 1
        try {
            $emailRes = (count($emailBatchItems) === 1)
                ? EmailService::sendStockAlertEmail($conn, $emailBatchItems[0]['product'], $emailBatchItems[0]['alert_type'])
                : EmailService::sendBatchStockAlertEmail($conn, $emailBatchItems);

            $status = $emailRes['success'] ? 'SENT' : 'FAILED';
            $err = $emailRes['error'] ?? null;
            $sentAt = $emailRes['success'] ? $now : null;

            // Audit record in notifications table for each product included in email
            foreach ($emailBatchItems as $bItem) {
                $pId = (int)($bItem['product']['product_id'] ?? 0);
                $pName = $bItem['product']['product_name'] ?? 'Product';
                $aType = $bItem['alert_type'] ?? 'LOW_STOCK';
                $title = "IntelliWare Batch Alert: {$pName}";
                $msg = "Batch notification: {$pName} ({$aType})";
                $meta = json_encode($bItem);

                $stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (product_id, user_id, notification_type, title, message, channel, status, meta, is_read, created_at, sent_at, error_message)
                    VALUES (?, ?, ?, ?, ?, 'EMAIL', ?, ?, 0, ?, ?, ?)
                ");
                $stmt->bind_param('iissssssss', $pId, $userId, $aType, $title, $msg, $status, $meta, $now, $sentAt, $err);
                $stmt->execute();
                $stmt->close();
            }

            return [
                'success' => $emailRes['success'],
                'email_dispatched' => true,
                'items_count' => count($emailBatchItems),
                'error' => $err
            ];
        } catch (Throwable $e) {
            error_log('[NotificationService] Batch email dispatch exception: ' . $e->getMessage());
            return [
                'success' => false,
                'email_dispatched' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backward compatibility wrapper for existing Out-of-Stock alert calls.
     */
    public static function triggerOutOfStockAlert(
        mysqli $conn,
        array $product,
        string $username,
        int $transactionId = 0
    ): array {
        return self::handleStockMovementAlert(
            $conn,
            $product,
            max(1, (int)($product['quantity'] ?? 1)),
            0,
            $username,
            $transactionId
        );
    }

    /**
     * Resolve user ID safely.
     */
    public static function getUserIdByUsername(mysqli $conn, string $username): ?int
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
