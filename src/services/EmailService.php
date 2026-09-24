<?php
/**
 * src/services/EmailService.php
 *
 * Enterprise SMTP email notification service for IntelliWare WIMS using PHPMailer.
 * Features:
 * - Direct SMTP sending (Gmail, Outlook, SendGrid, Amazon SES, or custom SMTP).
 * - Edge-triggered stock alerts: Low Stock (<= Reorder Point) and Out of Stock (0).
 * - Multi-product batch digest consolidation for bulk transactions.
 * - Dynamic recipient management: All Admins enrolled by default; per-user opt-in/opt-out
 *   and custom notification email address overrides.
 * - Clean inline-styled HTML templates readable across all major email clients.
 * - Non-blocking error handling and audit logging (never fails inventory updates).
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../config/notifications.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    /**
     * Resolve eligible recipient email addresses.
     *
     * Rules:
     * - Admins are opted in by default (unless notif_email_enabled is explicitly 0).
     * - Staff / Managers are opted in if notif_email_enabled is explicitly 1.
     * - Uses notification_email if specified, otherwise account email.
     * - Includes global extra recipients from system_settings if configured.
     *
     * @return array<string, array{email:string, name:string, role:string}>
     */
    public static function getRecipients(?mysqli $conn): array
    {
        $recipients = [];

        if ($conn) {
            try {
                // Ensure columns exist first
                $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'notif_email_enabled'");
                $hasNotifCol = $colCheck && $colCheck->num_rows > 0;

                $sql = $hasNotifCol
                    ? "SELECT user_id, username, full_name, role, email, notification_email, notif_email_enabled FROM users"
                    : "SELECT user_id, username, full_name, role, email FROM users";

                $res = $conn->query($sql);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $role = strtolower($row['role'] ?? 'staff');
                        $enabledFlag = $row['notif_email_enabled'] ?? null;

                        $isEnrolled = false;
                        if ($enabledFlag === null || $enabledFlag === '') {
                            // Default: all users with role 'admin' are enrolled
                            $isEnrolled = ($role === 'admin');
                        } else {
                            $isEnrolled = ((int)$enabledFlag === 1);
                        }

                        if ($isEnrolled) {
                            $targetEmail = trim(!empty($row['notification_email']) ? $row['notification_email'] : ($row['email'] ?? ''));
                            if (filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                                $name = trim(!empty($row['full_name']) ? $row['full_name'] : $row['username']);
                                $recipients[$targetEmail] = [
                                    'email' => $targetEmail,
                                    'name'  => $name,
                                    'role'  => $role
                                ];
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('[EmailService] getRecipients DB error: ' . $e->getMessage());
            }
        }

        // Merge extra global recipients from system_settings
        $settings = getAllNotificationSettings($conn);
        if (!empty($settings['notif_email_recipients'])) {
            $extra = array_filter(array_map('trim', explode(',', $settings['notif_email_recipients'])));
            foreach ($extra as $ex) {
                if (filter_var($ex, FILTER_VALIDATE_EMAIL) && !isset($recipients[$ex])) {
                    $recipients[$ex] = [
                        'email' => $ex,
                        'name'  => 'Notification Subscriber',
                        'role'  => 'subscriber'
                    ];
                }
            }
        }

        return array_values($recipients);
    }

    /**
     * Retrieve all active inventory products from the database, sorted with critical stock first.
     *
     * @param mysqli|null $conn
     * @return array{items:array, total:int, out_count:int, low_count:int, in_count:int}
     */
    public static function getAllInventoryProducts(?mysqli $conn): array
    {
        $products = [];
        $outCount = 0;
        $lowCount = 0;
        $inCount  = 0;

        if ($conn) {
            try {
                $res = $conn->query("
                    SELECT product_id, product_name, category, location, quantity, reorder_point 
                    FROM products 
                    WHERE archived = 0 
                    ORDER BY 
                        CASE 
                            WHEN quantity <= 0 THEN 0 
                            WHEN quantity <= reorder_point THEN 1 
                            ELSE 2 
                        END ASC, 
                        quantity ASC, 
                        product_name ASC
                ");

                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $qty = (int)$row['quantity'];
                        $rp  = (int)($row['reorder_point'] ?? 10);
                        if ($qty <= 0) {
                            $outCount++;
                            $row['status_key']   = 'OUT_OF_STOCK';
                            $row['status_label'] = 'OUT OF STOCK';
                            $row['badge_bg']     = '#fee2e2';
                            $row['badge_color']  = '#b91c1c';
                            $row['badge_border'] = '#fca5a5';
                            $row['stock_color']  = '#dc2626';
                        } elseif ($qty <= $rp) {
                            $lowCount++;
                            $row['status_key']   = 'LOW_STOCK';
                            $row['status_label'] = 'LOW STOCK';
                            $row['badge_bg']     = '#fef3c7';
                            $row['badge_color']  = '#b45309';
                            $row['badge_border'] = '#fcd34d';
                            $row['stock_color']  = '#d97706';
                        } else {
                            $inCount++;
                            $row['status_key']   = 'IN_STOCK';
                            $row['status_label'] = 'IN STOCK';
                            $row['badge_bg']     = '#dcfce7';
                            $row['badge_color']  = '#15803d';
                            $row['badge_border'] = '#86efac';
                            $row['stock_color']  = '#16a34a';
                        }
                        $row['sku'] = 'P-' . str_pad((string)$row['product_id'], 4, '0', STR_PAD_LEFT);
                        $products[] = $row;
                    }
                }
            } catch (Throwable $e) {
                error_log('[EmailService] getAllInventoryProducts error: ' . $e->getMessage());
            }
        }

        // Fallback sample if no DB products
        if (empty($products)) {
            $products = [
                [
                    'product_id'   => 101,
                    'product_name' => 'The Originote Moisturizer',
                    'category'     => 'Skincare',
                    'sku'          => 'P-0101',
                    'location'     => 'Shelf A-01',
                    'quantity'     => 0,
                    'reorder_point'=> 15,
                    'status_key'   => 'OUT_OF_STOCK',
                    'status_label' => 'OUT OF STOCK',
                    'badge_bg'     => '#fee2e2',
                    'badge_color'  => '#b91c1c',
                    'badge_border' => '#fca5a5',
                    'stock_color'  => '#dc2626'
                ],
                [
                    'product_id'   => 102,
                    'product_name' => 'Aquaflask 32 Oz Thumbler',
                    'category'     => 'Household Items',
                    'sku'          => 'P-0470',
                    'location'     => 'Shelf H',
                    'quantity'     => 6,
                    'reorder_point'=> 10,
                    'status_key'   => 'LOW_STOCK',
                    'status_label' => 'LOW STOCK',
                    'badge_bg'     => '#fef3c7',
                    'badge_color'  => '#b45309',
                    'badge_border' => '#fcd34d',
                    'stock_color'  => '#d97706'
                ],
                [
                    'product_id'   => 103,
                    'product_name' => 'Coca-Cola 330ml Can',
                    'category'     => 'Beverages',
                    'sku'          => 'P-0103',
                    'location'     => 'Aisle 2, Bin 04',
                    'quantity'     => 120,
                    'reorder_point'=> 30,
                    'status_key'   => 'IN_STOCK',
                    'status_label' => 'IN STOCK',
                    'badge_bg'     => '#dcfce7',
                    'badge_color'  => '#15803d',
                    'badge_border' => '#86efac',
                    'stock_color'  => '#16a34a'
                ]
            ];
            $outCount = 1;
            $lowCount = 1;
            $inCount  = 1;
        }

        return [
            'items'     => $products,
            'total'     => count($products),
            'out_count' => $outCount,
            'low_count' => $lowCount,
            'in_count'  => $inCount
        ];
    }

    /**
     * Send a stock alert email containing the complete inventory status table.
     *
     * @param mysqli|null $conn
     * @param array $product Details: product_name, category, sku, location, quantity, reorder_point
     * @param string $alertType 'OUT_OF_STOCK' or 'LOW_STOCK'
     * @param string|null $customRecipient
     * @return array{success:bool, error:?string, sent_to:array}
     */
    public static function sendStockAlertEmail(
        ?mysqli $conn,
        array $product,
        string $alertType = 'OUT_OF_STOCK',
        ?string $customRecipient = null
    ): array {
        $settings = getAllNotificationSettings($conn);

        if (($settings['notif_email_enabled'] ?? '1') !== '1') {
            return [
                'success' => false,
                'error'   => 'Email notifications are disabled in settings.',
                'sent_to' => []
            ];
        }

        // Determine recipients
        $recipients = [];
        if ($customRecipient) {
            $parsed = array_filter(array_map('trim', explode(',', $customRecipient)));
            foreach ($parsed as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        } else {
            $resolved = self::getRecipients($conn);
            foreach ($resolved as $r) {
                $recipients[] = $r['email'];
            }
        }

        if (empty($recipients)) {
            return [
                'success' => false,
                'error'   => 'No recipient email addresses configured or opted in.',
                'sent_to' => []
            ];
        }

        // Product fields
        $productName  = $product['product_name'] ?? 'Unknown Product';
        $currentStock = (int)($product['quantity'] ?? 0);
        $dateTime     = date('F j, Y - g:i A');

        $isOutOfStock = ($alertType === 'OUT_OF_STOCK' || $currentStock === 0);

        // Fetch inventory list from database
        $invData    = self::getAllInventoryProducts($conn);
        $totalCount = $invData['total'];

        // Filter table items to only show products under the triggered category
        $targetStatus = $isOutOfStock ? 'OUT_OF_STOCK' : 'LOW_STOCK';
        $filteredItems = array_values(array_filter($invData['items'], function($it) use ($targetStatus) {
            return ($it['status_key'] ?? '') === $targetStatus;
        }));

        // Ensure the triggered product is in the items list
        $hasTriggered = false;
        foreach ($filteredItems as $it) {
            if (($it['product_id'] ?? 0) == ($product['product_id'] ?? -1)) {
                $hasTriggered = true;
                break;
            }
        }
        if (!$hasTriggered) {
            $p = $product;
            $p['status_key'] = $targetStatus;
            $p['sku'] = !empty($p['sku']) ? $p['sku'] : ('P-' . str_pad((string)($p['product_id'] ?? 0), 4, '0', STR_PAD_LEFT));
            array_unshift($filteredItems, $p);
        }

        $invData['items'] = $filteredItems;
        $categoryCount = count($filteredItems);

        // Subject line indicating product urgency and count of items under category
        $subject = $isOutOfStock
            ? "IntelliWare Alert: {$productName} is OUT OF STOCK ({$categoryCount} item" . ($categoryCount === 1 ? '' : 's') . " out of stock)"
            : "IntelliWare Alert: {$productName} is LOW ON STOCK ({$currentStock} remaining)";

        $inventoryUrl = self::getAppBaseUrl() . '/products.php';

        // Render inventory status table HTML & Plain Text
        $htmlBody = self::renderInventoryStatusReportHtml(
            $invData,
            $product,
            $alertType,
            $dateTime,
            $inventoryUrl
        );
        $plainText = self::renderInventoryStatusReportPlainText(
            $invData,
            $product,
            $alertType,
            $dateTime,
            $inventoryUrl
        );

        // Save preview for developer diagnostics
        self::savePreviewHtml($htmlBody);

        return self::dispatchMail($settings, $recipients, $subject, $htmlBody, $plainText, $productName);
    }

    /**
     * Backward compatibility wrapper for existing Out-of-Stock calls.
     */
    public static function sendOutOfStockAlert(?mysqli $conn, array $product, ?string $customRecipient = null): array
    {
        return self::sendStockAlertEmail($conn, $product, 'OUT_OF_STOCK', $customRecipient);
    }

    /**
     * Send a consolidated batch digest email when multiple products cross threshold.
     *
     * @param mysqli|null $conn
     * @param array<int, array{product:array, alert_type:string}> $items
     * @param string|null $customRecipient
     * @return array{success:bool, error:?string, sent_to:array}
     */
    public static function sendBatchStockAlertEmail(
        ?mysqli $conn,
        array $items,
        ?string $customRecipient = null
    ): array {
        if (empty($items)) {
            return ['success' => false, 'error' => 'No items in batch alert.', 'sent_to' => []];
        }

        $settings = getAllNotificationSettings($conn);
        if (($settings['notif_email_enabled'] ?? '1') !== '1') {
            return ['success' => false, 'error' => 'Email notifications are disabled in settings.', 'sent_to' => []];
        }

        $recipients = [];
        if ($customRecipient) {
            $parsed = array_filter(array_map('trim', explode(',', $customRecipient)));
            foreach ($parsed as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) $recipients[] = $email;
            }
        } else {
            $resolved = self::getRecipients($conn);
            foreach ($resolved as $r) $recipients[] = $r['email'];
        }

        if (empty($recipients)) {
            return ['success' => false, 'error' => 'No recipient email addresses configured or opted in.', 'sent_to' => []];
        }

        // Count totals in batch
        $outCount = 0;
        $lowCount = 0;
        foreach ($items as $it) {
            if (($it['alert_type'] ?? '') === 'OUT_OF_STOCK' || (int)($it['product']['quantity'] ?? 0) === 0) {
                $outCount++;
            } else {
                $lowCount++;
            }
        }
        $batchTotal = count($items);

        // Fetch inventory stats from database
        $invData    = self::getAllInventoryProducts($conn);

        // Filter table items to only include products part of this batch alert
        $batchProductIds = [];
        foreach ($items as $it) {
            $pid = $it['product']['product_id'] ?? null;
            if ($pid !== null) {
                $batchProductIds[$pid] = true;
            }
        }
        $filteredItems = [];
        foreach ($invData['items'] as $it) {
            $pid = $it['product_id'] ?? 0;
            if (isset($batchProductIds[$pid])) {
                $filteredItems[] = $it;
            }
        }
        if (count($filteredItems) < count($items)) {
            foreach ($items as $it) {
                $pid = $it['product']['product_id'] ?? 0;
                $found = false;
                foreach ($filteredItems as $f) {
                    if (($f['product_id'] ?? 0) == $pid) { $found = true; break; }
                }
                if (!$found) {
                    $p = $it['product'];
                    $p['status_key'] = $it['alert_type'] ?? 'LOW_STOCK';
                    $p['sku'] = !empty($p['sku']) ? $p['sku'] : ('P-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT));
                    $filteredItems[] = $p;
                }
            }
        }
        $invData['items'] = $filteredItems;

        // Subject line reflecting urgency and count of batch items
        if ($outCount > 0 && $lowCount > 0) {
            $subject = "IntelliWare Alert: {$batchTotal} products require attention ({$outCount} Out of Stock, {$lowCount} Low Stock)";
        } elseif ($outCount > 0) {
            $subject = "IntelliWare Alert: {$batchTotal} product" . ($batchTotal === 1 ? '' : 's') . " are OUT OF STOCK";
        } else {
            $subject = "IntelliWare Alert: {$batchTotal} product" . ($batchTotal === 1 ? '' : 's') . " low on stock";
        }

        $inventoryUrl = self::getAppBaseUrl() . '/products.php';
        $dateTime = date('F j, Y - g:i A');

        $htmlBody = self::renderInventoryStatusReportHtml($invData, null, 'BATCH', $dateTime, $inventoryUrl, $items);
        $plainText = self::renderInventoryStatusReportPlainText($invData, null, 'BATCH', $dateTime, $inventoryUrl, $items);

        self::savePreviewHtml($htmlBody);

        return self::dispatchMail($settings, $recipients, $subject, $htmlBody, $plainText, "Batch of {$batchTotal} items");
    }

    /**
     * Send a test email from the Settings page with the complete inventory status table.
     */
    public static function sendTestEmail(?mysqli $conn, string $testRecipient, string $testType = 'complete_table'): array
    {
        $settings = getAllNotificationSettings($conn);
        if (($settings['notif_email_enabled'] ?? '1') !== '1') {
            return ['success' => false, 'error' => 'Email notifications are disabled in settings.', 'sent_to' => []];
        }

        $parsed = array_filter(array_map('trim', explode(',', $testRecipient)));
        $recipients = [];
        foreach ($parsed as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $recipients[] = $email;
        }
        if (empty($recipients)) {
            return ['success' => false, 'error' => 'No valid recipient email address provided.', 'sent_to' => []];
        }

        $invData    = self::getAllInventoryProducts($conn);
        $totalCount = $invData['total'];
        $dateTime   = date('F j, Y - g:i A');
        $inventoryUrl = self::getAppBaseUrl() . '/products.php';

        $triggeredProduct = null;
        $alertType = 'REPORT';
        $subject = "IntelliWare: Complete Inventory Status Table ({$totalCount} Products)";

        if ($testType === 'out_of_stock') {
            $alertType = 'OUT_OF_STOCK';
            // Filter to only out-of-stock products
            $outItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') === 'OUT_OF_STOCK';
            }));
            if (empty($outItems)) {
                $demoOut = [
                    'product_id'   => 9999,
                    'product_name' => 'The Originote Moisturizer (Demo Out of Stock)',
                    'category'     => 'Skincare',
                    'sku'          => 'P-9999',
                    'location'     => 'Shelf A-01',
                    'quantity'     => 0,
                    'reorder_point'=> 15,
                    'status_key'   => 'OUT_OF_STOCK'
                ];
                $outItems = [$demoOut];
                $triggeredProduct = $demoOut;
            } else {
                $triggeredProduct = $outItems[0];
            }
            $invData['items'] = $outItems;
            $cnt = count($outItems);
            $subject = "IntelliWare Alert: Critical Out of Stock Report ({$cnt} Product" . ($cnt === 1 ? '' : 's') . ")";
        } elseif ($testType === 'low_stock') {
            $alertType = 'LOW_STOCK';
            // Filter to only low-stock products
            $lowItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') === 'LOW_STOCK';
            }));
            if (empty($lowItems)) {
                $demoLow = [
                    'product_id'   => 470,
                    'product_name' => 'Aquaflask 32 Oz Thumbler (Demo Low Stock)',
                    'category'     => 'Household Items',
                    'sku'          => 'P-0470',
                    'location'     => 'Shelf H',
                    'quantity'     => 6,
                    'reorder_point'=> 10,
                    'status_key'   => 'LOW_STOCK'
                ];
                $lowItems = [$demoLow];
                $triggeredProduct = $demoLow;
            } else {
                $triggeredProduct = $lowItems[0];
            }
            $invData['items'] = $lowItems;
            $cnt = count($lowItems);
            $subject = "IntelliWare Alert: Low Stock Warning ({$cnt} Product" . ($cnt === 1 ? '' : 's') . " Need Attention)";
        } elseif ($testType === 'batch') {
            $alertType = 'BATCH';
            $batchItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') !== 'IN_STOCK';
            }));
            if (empty($batchItems)) {
                $batchItems = [
                    [
                        'product_id'   => 9999,
                        'product_name' => 'The Originote Moisturizer (Demo Out of Stock)',
                        'category'     => 'Skincare',
                        'sku'          => 'P-9999',
                        'location'     => 'Shelf A-01',
                        'quantity'     => 0,
                        'reorder_point'=> 15,
                        'status_key'   => 'OUT_OF_STOCK'
                    ],
                    [
                        'product_id'   => 470,
                        'product_name' => 'Aquaflask 32 Oz Thumbler (Demo Low Stock)',
                        'category'     => 'Household Items',
                        'sku'          => 'P-0470',
                        'location'     => 'Shelf H',
                        'quantity'     => 6,
                        'reorder_point'=> 10,
                        'status_key'   => 'LOW_STOCK'
                    ]
                ];
            }
            $invData['items'] = $batchItems;
            $cnt = count($batchItems);
            $subject = "IntelliWare: Consolidated Batch Digest ({$cnt} Products)";
        }

        $htmlBody = self::renderInventoryStatusReportHtml($invData, $triggeredProduct, $alertType, $dateTime, $inventoryUrl);
        $plainText = self::renderInventoryStatusReportPlainText($invData, $triggeredProduct, $alertType, $dateTime, $inventoryUrl);

        self::savePreviewHtml($htmlBody);

        $contextLabel = $testType === 'complete_table' ? "Complete Inventory Status Table" : "Filtered Alert ({$testType})";
        return self::dispatchMail($settings, $recipients, $subject, $htmlBody, $plainText, $contextLabel);
    }

    /**
     * Dispatch email via PHPMailer using configured SMTP settings.
     */
    private static function dispatchMail(
        array $settings,
        array $recipients,
        string $subject,
        string $htmlBody,
        string $plainText,
        string $contextName
    ): array {
        $smtpHost  = trim($settings['smtp_host'] ?? '');
        $smtpUser  = trim($settings['smtp_username'] ?? '');
        $smtpPass  = trim($settings['smtp_password'] ?? '');
        $smtpPort  = (int)($settings['smtp_port'] ?? 587);
        $fromEmail = trim($settings['smtp_from_email'] ?? 'alerts@intelliware.ph');
        $fromName  = trim($settings['smtp_from_name'] ?? 'IntelliWare Inventory');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            $msg = 'SMTP credentials not configured. Please enter your SMTP Host, Username and App Password under Settings -> Alert Channels & Gateway Credentials.';
            self::logOutcome($contextName, $recipients, false, $msg);
            return [
                'success' => false,
                'error'   => $msg,
                'sent_to' => $recipients
            ];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = strtolower($settings['smtp_encryption'] ?? 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->Timeout    = 8;

            $mail->setFrom($fromEmail, $fromName);
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            // Embed Logo if available
            $logoPath = __DIR__ . '/../../img/iLogo.png';
            if (is_file($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'intelliware_logo', 'iLogo.png', 'base64', 'image/png');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainText;

            $mail->send();

            self::logOutcome($contextName, $recipients, true, null);

            return [
                'success' => true,
                'error'   => null,
                'sent_to' => $recipients
            ];
        } catch (Exception $e) {
            $errMsg = $mail->ErrorInfo ?: $e->getMessage();
            if (stripos($errMsg, 'Could not authenticate') !== false || stripos($errMsg, '535') !== false) {
                $errMsg = 'SMTP Authentication Error: Google/Microsoft requires an "App Password" rather than your standard account password. Generate one in your account security settings.';
            }

            error_log('[EmailService] SMTP error: ' . $errMsg);
            self::logOutcome($contextName, $recipients, false, $errMsg);

            return [
                'success' => false,
                'error'   => $errMsg,
                'sent_to' => $recipients
            ];
        }
    }

    /**
     * Render responsive inline-styled HTML for complete inventory status report.
     */
    public static function renderInventoryStatusReportHtml(
        array $invData,
        ?array $triggeredProduct = null,
        string $alertType = 'REPORT',
        string $dateTime = '',
        string $inventoryUrl = '',
        array $batchItems = []
    ): string {
        $totalCount = $invData['total'] ?? count($invData['items'] ?? []);
        $outCount   = $invData['out_count'] ?? 0;
        $lowCount   = $invData['low_count'] ?? 0;
        $inCount    = $invData['in_count'] ?? 0;
        $items      = $invData['items'] ?? [];

        if (empty($dateTime)) {
            $dateTime = date('F j, Y - g:i A');
        }
        if (empty($inventoryUrl)) {
            $inventoryUrl = self::getAppBaseUrl() . '/products.php';
        }

        $displayedCount = count($items);

        // Determine Table Title based on alertType
        if ($alertType === 'LOW_STOCK') {
            $tableTitle = 'Low Stock Products (At or Below Reorder Point)';
        } elseif ($alertType === 'OUT_OF_STOCK') {
            $tableTitle = 'Critical Out of Stock Products (0 Units Remaining)';
        } elseif ($alertType === 'BATCH') {
            $tableTitle = 'Threshold Alert Items (Requiring Attention)';
        } else {
            $tableTitle = 'All Inventory Catalog Products';
        }

        // Header badge and Alert banner setup
        $badgeBg    = '#1e3a8a';
        $badgeText  = 'STATUS REPORT';
        $bannerHtml = '';

        if ($alertType === 'OUT_OF_STOCK') {
            $badgeBg   = '#ef4444';
            $badgeText = 'CRITICAL ALERT';
            $pName     = htmlspecialchars($triggeredProduct['product_name'] ?? 'Out of Stock Alert');
            $bannerHtml = '
            <div style="background-color:#fee2e2; border-left:4px solid #ef4444; border-radius:6px; padding:14px 16px; margin-bottom:18px;">
                <h2 style="margin:0 0 4px 0; color:#991b1b; font-size:15px; font-weight:700;">Critical Alert: ' . $pName . '</h2>
                <p style="margin:0; color:#991b1b; font-size:13px; font-weight:500; line-height:1.4;">Stock has reached 0 units. Immediate replenishment is required. Showing ' . $displayedCount . ' out-of-stock product' . ($displayedCount === 1 ? '' : 's') . ' below.</p>
            </div>';
        } elseif ($alertType === 'LOW_STOCK') {
            $badgeBg   = '#f59e0b';
            $badgeText = 'WARNING ALERT';
            $pName     = htmlspecialchars($triggeredProduct['product_name'] ?? 'Low Stock Warning');
            $currQty   = (int)($triggeredProduct['quantity'] ?? 0);
            $reorderPt = (int)($triggeredProduct['reorder_point'] ?? 10);
            $subline   = !empty($triggeredProduct['product_name'])
                ? "Available stock is {$currQty} units (at or below reorder threshold of {$reorderPt} units). Showing {$displayedCount} low-stock product" . ($displayedCount === 1 ? '' : 's') . " below."
                : "Showing {$displayedCount} product" . ($displayedCount === 1 ? '' : 's') . " currently at or below minimum reorder threshold.";
            $bannerHtml = '
            <div style="background-color:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; padding:14px 16px; margin-bottom:18px;">
                <h2 style="margin:0 0 4px 0; color:#92400e; font-size:15px; font-weight:700;">Stock Warning: ' . $pName . '</h2>
                <p style="margin:0; color:#92400e; font-size:13px; font-weight:500; line-height:1.4;">' . $subline . '</p>
            </div>';
        } elseif ($alertType === 'BATCH') {
            $badgeBg   = '#1e3a8a';
            $badgeText = 'BULK DIGEST';
            $bannerHtml = '
            <div style="background-color:#eff6ff; border-left:4px solid #3b82f6; border-radius:6px; padding:14px 16px; margin-bottom:18px;">
                <h2 style="margin:0 0 4px 0; color:#1e40af; font-size:15px; font-weight:700;">Bulk Digest: ' . $displayedCount . ' Products Require Attention</h2>
                <p style="margin:0; color:#1e3a8a; font-size:13px; font-weight:500; line-height:1.4;">Recent transactions triggered threshold warnings. Showing all affected products below.</p>
            </div>';
        } else {
            $bannerHtml = '
            <div style="background-color:#f8fafc; border-left:4px solid #0284c7; border-radius:6px; padding:12px 16px; margin-bottom:18px;">
                <h2 style="margin:0 0 4px 0; color:#0f172a; font-size:15px; font-weight:700;">Complete Warehouse Inventory Status Report</h2>
                <p style="margin:0; color:#475569; font-size:13px; font-weight:500; line-height:1.4;">Up-to-date snapshot of all ' . $totalCount . ' active products and their current stock levels across all categories.</p>
            </div>';
        }

        $logoPath = __DIR__ . '/../../img/iLogo.png';
        $logoHtml = is_file($logoPath)
            ? '<img src="cid:intelliware_logo" alt="IntelliWare" style="height:36px; max-width:180px; display:block; border:0;" />'
            : '<span style="font-size:22px; font-weight:800; color:#111827; letter-spacing:-0.5px; font-family:sans-serif;">INTELLI<span style="color:#16a34a;">WARE</span></span>';

        // Render table rows
        $rowsHtml = '';
        $idx = 0;
        foreach ($items as $p) {
            $idx++;
            $name = htmlspecialchars($p['product_name'] ?? 'Item');
            $cat  = htmlspecialchars($p['category'] ?: 'General');
            $sku  = htmlspecialchars(!empty($p['sku']) ? $p['sku'] : ('P-' . str_pad((string)($p['product_id'] ?? 0), 4, '0', STR_PAD_LEFT)));
            $loc  = htmlspecialchars($p['location'] ?: 'Main Warehouse');
            $qty  = (int)($p['quantity'] ?? 0);
            $rp   = (int)($p['reorder_point'] ?? 10);
            $stat = $p['status_key'] ?? ($qty <= 0 ? 'OUT_OF_STOCK' : ($qty <= $rp ? 'LOW_STOCK' : 'IN_STOCK'));

            if ($stat === 'OUT_OF_STOCK') {
                $statusBadge = '<span style="display:inline-block;padding:2px 6px;font-size:10px;font-weight:700;border-radius:4px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;">OUT OF STOCK</span>';
                $qtyHtml     = '<b style="color:#dc2626;">' . $qty . '</b>';
            } elseif ($stat === 'LOW_STOCK') {
                $statusBadge = '<span style="display:inline-block;padding:2px 6px;font-size:10px;font-weight:700;border-radius:4px;background:#fef3c7;color:#b45309;border:1px solid #fcd34d;">LOW STOCK</span>';
                $qtyHtml     = '<b style="color:#d97706;">' . $qty . '</b>';
            } else {
                $statusBadge = '<span style="display:inline-block;padding:2px 6px;font-size:10px;font-weight:700;border-radius:4px;background:#dcfce7;color:#15803d;border:1px solid #86efac;">IN STOCK</span>';
                $qtyHtml     = '<span style="color:#16a34a;font-weight:600;">' . $qty . '</span>';
            }

            $rowsHtml .= "<tr style='border-bottom:1px solid #e5e7eb;'><td style='padding:6px 8px;font-size:11px;color:#9ca3af;text-align:center;'>{$idx}</td><td style='padding:6px 8px;'><div style='font-size:13px;font-weight:700;color:#111827;'>{$name}</div><div style='font-size:11px;color:#6b7280;'>{$cat} &bull; <span style='font-family:monospace;'>{$sku}</span></div></td><td style='padding:6px 8px;font-size:11px;color:#4b5563;'>{$loc}</td><td style='padding:6px 8px;text-align:right;'>{$qtyHtml} <span style='font-size:10px;color:#9ca3af;'>/ {$rp}</span></td><td style='padding:6px 8px;text-align:center;'>{$statusBadge}</td></tr>";
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntelliWare Inventory Status Report</title>
    <style>
        body { margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color:#1f2937; }
        table { border-collapse: collapse; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#1f2937; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; padding:24px 8px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:720px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.06);" cellspacing="0" cellpadding="0" border="0">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding:20px 24px; background-color:#ffffff; border-bottom:1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        ' . $logoHtml . '
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; background-color:' . $badgeBg . '; color:#ffffff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; padding:4px 10px; border-radius:12px;">
                                            ' . $badgeText . '
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Alert Banner (if applicable) & Overview KPI Cards -->
                    <tr>
                        <td style="padding:20px 24px 10px 24px;">
                            ' . $bannerHtml . '

                            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">INVENTORY SNAPSHOT &bull; ' . htmlspecialchars($dateTime) . '</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:16px;">
                                <tr>
                                    <td width="25%" style="padding:3px;">
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 6px; text-align:center;">
                                            <div style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase;">Total</div>
                                            <div style="font-size:19px; font-weight:800; color:#0f172a; margin-top:2px;">' . $totalCount . '</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:3px;">
                                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 6px; text-align:center;">
                                            <div style="font-size:10px; font-weight:700; color:#991b1b; text-transform:uppercase;">Out of Stock</div>
                                            <div style="font-size:19px; font-weight:800; color:#dc2626; margin-top:2px;">' . $outCount . '</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:3px;">
                                        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 6px; text-align:center;">
                                            <div style="font-size:10px; font-weight:700; color:#92400e; text-transform:uppercase;">Low Stock</div>
                                            <div style="font-size:19px; font-weight:800; color:#d97706; margin-top:2px;">' . $lowCount . '</div>
                                        </div>
                                    </td>
                                    <td width="25%" style="padding:3px;">
                                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 6px; text-align:center;">
                                            <div style="font-size:10px; font-weight:700; color:#166534; text-transform:uppercase;">In Stock</div>
                                            <div style="font-size:19px; font-weight:800; color:#16a34a; margin-top:2px;">' . $inCount . '</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Table Section Header -->
                    <tr>
                        <td style="padding:4px 24px 8px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left">
                                        <span style="font-size:12px; font-weight:700; color:#111827; text-transform:uppercase; letter-spacing:0.04em;">' . $tableTitle . '</span>
                                    </td>
                                    <td align="right">
                                        <span style="display:inline-block; background-color:#f1f5f9; color:#475569; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; border:1px solid #e2e8f0;">Showing ' . $displayedCount . ' item' . ($displayedCount === 1 ? '' : 's') . '</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Complete Inventory Table -->
                    <tr>
                        <td style="padding:0 24px 20px 24px;">
                            <table role="presentation" width="100%" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; border-collapse:collapse;" cellspacing="0" cellpadding="0">
                                <thead>
                                    <tr style="background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <th style="padding:8px 10px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:center; width:30px;">#</th>
                                        <th style="padding:8px 10px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:left;">Product &amp; Category</th>
                                        <th style="padding:8px 10px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:left;">Location</th>
                                        <th style="padding:8px 10px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:right;">Stock / Min</th>
                                        <th style="padding:8px 10px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; text-align:center; width:110px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $rowsHtml . '
                                </tbody>
                            </table>
                            ' . ($displayedCount < $totalCount ? '
                            <div style="margin-top:10px; text-align:center; font-size:11px; color:#6b7280;">
                                Filtered view: Showing only the <strong>' . $displayedCount . '</strong> product' . ($displayedCount === 1 ? '' : 's') . ' under this category out of <strong>' . $totalCount . '</strong> total catalog items.
                            </div>' : '') . '

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
                                <tr>
                                    <td align="center">
                                        <a href="' . htmlspecialchars($inventoryUrl) . '" target="_blank" style="display:inline-block; background-color:#16a34a; color:#ffffff; font-size:13px; font-weight:700; text-decoration:none; padding:11px 26px; border-radius:6px; box-shadow:0 2px 6px rgba(22, 163, 74, 0.25);">
                                            Open Full Inventory in IntelliWare &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 24px; background-color:#f9fafb; border-top:1px solid #e5e7eb; text-align:center;">
                            <p style="margin:0 0 4px 0; color:#6b7280; font-size:11px;">
                                Automated real-time report generated by <strong style="color:#374151;">IntelliWare Inventory Management System</strong> on ' . htmlspecialchars($dateTime) . '.
                            </p>
                            <p style="margin:0; color:#9ca3af; font-size:10px;">
                                You are receiving this because your account is configured to receive inventory alerts and status reports.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Plain text rendering for complete inventory status report.
     */
    public static function renderInventoryStatusReportPlainText(
        array $invData,
        ?array $triggeredProduct = null,
        string $alertType = 'REPORT',
        string $dateTime = '',
        string $inventoryUrl = '',
        array $batchItems = []
    ): string {
        $totalCount = $invData['total'] ?? count($invData['items'] ?? []);
        $outCount   = $invData['out_count'] ?? 0;
        $lowCount   = $invData['low_count'] ?? 0;
        $inCount    = $invData['in_count'] ?? 0;
        $items      = $invData['items'] ?? [];

        if (empty($dateTime)) {
            $dateTime = date('F j, Y - g:i A');
        }
        if (empty($inventoryUrl)) {
            $inventoryUrl = self::getAppBaseUrl() . '/products.php';
        }

        $displayedCount = count($items);

        if ($alertType === 'LOW_STOCK') {
            $tableTitle = "LOW STOCK PRODUCTS (AT OR BELOW REORDER POINT)";
        } elseif ($alertType === 'OUT_OF_STOCK') {
            $tableTitle = "CRITICAL OUT OF STOCK PRODUCTS (0 UNITS REMAINING)";
        } elseif ($alertType === 'BATCH') {
            $tableTitle = "THRESHOLD ALERT ITEMS (REQUIRING ATTENTION)";
        } else {
            $tableTitle = "ALL INVENTORY CATALOG PRODUCTS";
        }

        $text = "INTELLIWARE INVENTORY STATUS REPORT\n";
        $text .= "====================================================\n";
        $text .= "Timestamp: {$dateTime}\n\n";

        if ($alertType === 'OUT_OF_STOCK' && $triggeredProduct) {
            $text .= "*** CRITICAL ALERT: " . ($triggeredProduct['product_name'] ?? 'Product') . " is OUT OF STOCK (0 units) ***\n\n";
        } elseif ($alertType === 'LOW_STOCK' && $triggeredProduct) {
            $text .= "*** WARNING ALERT: " . ($triggeredProduct['product_name'] ?? 'Product') . " is LOW ON STOCK (" . ($triggeredProduct['quantity'] ?? 0) . " remaining) ***\n\n";
        } elseif ($alertType === 'BATCH') {
            $text .= "*** BULK DIGEST: {$displayedCount} products crossed stock thresholds ***\n\n";
        }

        $text .= "WAREHOUSE OVERVIEW:\n";
        $text .= "- Total Catalog Products: {$totalCount}\n";
        $text .= "- Out of Stock:           {$outCount}\n";
        $text .= "- Low Stock:               {$lowCount}\n";
        $text .= "- In Stock:                {$inCount}\n\n";

        $text .= "{$tableTitle} (Showing {$displayedCount} of {$totalCount} products):\n";
        $text .= "----------------------------------------------------\n";

        $idx = 0;
        foreach ($items as $p) {
            $idx++;
            $name = $p['product_name'] ?? 'Product';
            $cat  = $p['category'] ?: 'General';
            $sku  = !empty($p['sku']) ? $p['sku'] : ('P-' . str_pad((string)($p['product_id'] ?? 0), 4, '0', STR_PAD_LEFT));
            $loc  = $p['location'] ?: 'Main Warehouse';
            $qty  = (int)($p['quantity'] ?? 0);
            $rp   = (int)($p['reorder_point'] ?? 10);
            $stat = $p['status_key'] ?? ($qty <= 0 ? 'OUT_OF_STOCK' : ($qty <= $rp ? 'LOW_STOCK' : 'IN_STOCK'));
            $statLabel = $stat === 'OUT_OF_STOCK' ? '[OUT OF STOCK]' : ($stat === 'LOW_STOCK' ? '[LOW STOCK]' : '[IN STOCK]');

            $text .= sprintf("%3d. %-14s %s (SKU: %s, %s)\n", $idx, $statLabel, $name, $sku, $cat);
            $text .= sprintf("     Stock: %d | Min: %d | Location: %s\n\n", $qty, $rp, $loc);
        }

        $text .= "----------------------------------------------------\n";
        $text .= "Open Full Inventory: {$inventoryUrl}\n\n";
        $text .= "---\nIntelliWare Inventory Management System";

        return $text;
    }

    /**
     * Render clean inline-styled HTML for single product alert.
     */
    public static function renderSingleAlertHtml(
        string $productName,
        string $category,
        string $sku,
        string $location,
        int $currentStock,
        int $reorderPoint,
        string $dateTime,
        string $actionUrl,
        bool $isOutOfStock
    ): string {
        $badgeBg    = $isOutOfStock ? '#ef4444' : '#f59e0b';
        $badgeText  = $isOutOfStock ? 'OUT OF STOCK' : 'LOW STOCK';
        $bannerBg   = $isOutOfStock ? '#fee2e2' : '#fef3c7';
        $bannerBorder = $isOutOfStock ? '#ef4444' : '#f59e0b';
        $bannerColor = $isOutOfStock ? '#991b1b' : '#92400e';
        $headline   = $isOutOfStock
            ? 'Critical Alert: Product is Out of Stock'
            : 'Warning Alert: Stock Dropped to/below Reorder Point';
        $subtext    = $isOutOfStock
            ? 'Stock has reached 0 units. Immediate replenishment is required.'
            : "Available stock ({$currentStock}) is at or below the reorder point ({$reorderPoint}). Restock soon.";

        $logoPath = __DIR__ . '/../../img/iLogo.png';
        $logoHtml = is_file($logoPath)
            ? '<img src="cid:intelliware_logo" alt="IntelliWare" style="height:36px; max-width:180px; display:block; border:0;" />'
            : '<span style="font-size:22px; font-weight:800; color:#111827; letter-spacing:-0.5px; font-family:sans-serif;">INTELLI<span style="color:#16a34a;">WARE</span></span>';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($headline) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#1f2937; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; padding:30px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:580px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.06);" cellspacing="0" cellpadding="0" border="0">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding:22px 28px; background-color:#ffffff; border-bottom:1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        ' . $logoHtml . '
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; background-color:' . $badgeBg . '; color:#ffffff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; padding:4px 10px; border-radius:12px;">
                                            ' . $badgeText . '
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Alert Banner -->
                    <tr>
                        <td style="padding:24px 28px 12px 28px;">
                            <div style="background-color:' . $bannerBg . '; border-left:4px solid ' . $bannerBorder . '; border-radius:6px; padding:14px 16px; margin-bottom:20px;">
                                <h2 style="margin:0 0 4px 0; color:' . $bannerColor . '; font-size:16px; font-weight:700;">' . $headline . '</h2>
                                <p style="margin:0; color:' . $bannerColor . '; font-size:13px; font-weight:500; line-height:1.4;">' . $subtext . '</p>
                            </div>

                            <!-- Product Details Table -->
                            <table role="presentation" width="100%" style="border:1px solid #e5e7eb; border-radius:8px; margin-bottom:24px; border-collapse:collapse;" cellspacing="0" cellpadding="0">
                                <tr style="background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase; width:38%;">Product Name</td>
                                    <td style="padding:10px 14px; font-size:14px; font-weight:700; color:#111827; text-align:right;">' . htmlspecialchars($productName) . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">Category</td>
                                    <td style="padding:10px 14px; font-size:13px; font-weight:600; color:#374151; text-align:right;">' . htmlspecialchars($category) . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">SKU</td>
                                    <td style="padding:10px 14px; font-size:12px; font-family:monospace; color:#4b5563; text-align:right;">' . htmlspecialchars($sku) . '</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">Current Stock</td>
                                    <td style="padding:10px 14px; font-size:14px; font-weight:800; color:' . ($isOutOfStock ? '#dc2626' : '#d97706') . '; text-align:right;">' . $currentStock . ' units</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">Reorder Point</td>
                                    <td style="padding:10px 14px; font-size:13px; font-weight:600; color:#374151; text-align:right;">' . $reorderPoint . ' units</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">Location</td>
                                    <td style="padding:10px 14px; font-size:13px; color:#4b5563; text-align:right;">' . htmlspecialchars($location) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">Alert Timestamp</td>
                                    <td style="padding:10px 14px; font-size:12px; color:#6b7280; text-align:right;">' . htmlspecialchars($dateTime) . '</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:6px 0 24px 0;">
                                        <a href="' . htmlspecialchars($actionUrl) . '" target="_blank" style="display:inline-block; background-color:#16a34a; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:12px 28px; border-radius:6px; box-shadow:0 2px 6px rgba(22, 163, 74, 0.25);">
                                            View Product in IntelliWare &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:18px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb; text-align:center;">
                            <p style="margin:0 0 4px 0; color:#6b7280; font-size:12px;">
                                Automated real-time notification generated by <strong style="color:#374151;">IntelliWare Inventory Management System</strong>.
                            </p>
                            <p style="margin:0; color:#9ca3af; font-size:11px;">
                                You are receiving this because your account is configured to receive inventory threshold alerts.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Plain text rendering for single alert.
     */
    private static function renderSingleAlertPlainText(
        string $productName,
        string $category,
        string $sku,
        string $location,
        int $currentStock,
        int $reorderPoint,
        string $dateTime,
        string $actionUrl,
        bool $isOutOfStock
    ): string {
        $status = $isOutOfStock ? 'OUT OF STOCK' : 'LOW STOCK';
        return "INTELLIWARE INVENTORY ALERT: {$status}\n" .
               "===============================================\n\n" .
               "Product: {$productName}\n" .
               "Category: {$category}\n" .
               "SKU: {$sku}\n" .
               "Current Stock: {$currentStock} units\n" .
               "Reorder Point: {$reorderPoint} units\n" .
               "Location: {$location}\n" .
               "Date/Time: {$dateTime}\n\n" .
               ($isOutOfStock ? "URGENT: Stock has reached 0 units. Immediate replenishment needed.\n" : "WARNING: Stock is at or below reorder threshold. Please prepare restock.\n") .
               "View in IntelliWare: {$actionUrl}\n\n" .
               "---\nIntelliWare WIMS Automated Alerts";
    }

    /**
     * Render clean HTML for multi-product batch digest.
     */
    public static function renderBatchDigestHtml(
        array $items,
        int $outCount,
        int $lowCount,
        string $dateTime,
        string $inventoryUrl
    ): string {
        $total = count($items);

        $rowsHtml = '';
        foreach ($items as $it) {
            $prod = $it['product'] ?? [];
            $name = htmlspecialchars($prod['product_name'] ?? 'Product');
            $cat  = htmlspecialchars($prod['category'] ?? 'General');
            $sku  = htmlspecialchars(!empty($prod['sku']) ? $prod['sku'] : ('P-' . str_pad((string)($prod['product_id'] ?? 0), 4, '0', STR_PAD_LEFT)));
            $loc  = htmlspecialchars($prod['location'] ?? 'Main Warehouse');
            $qty  = (int)($prod['quantity'] ?? 0);
            $rp   = (int)($prod['reorder_point'] ?? 10);
            $isOut= (($it['alert_type'] ?? '') === 'OUT_OF_STOCK' || $qty === 0);

            $badge = $isOut
                ? '<span style="background-color:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; border:1px solid #fca5a5;">OUT OF STOCK</span>'
                : '<span style="background-color:#fef3c7; color:#b45309; font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; border:1px solid #fcd34d;">LOW STOCK</span>';

            $itemLink = self::getAppBaseUrl() . '/products.php?search=' . urlencode($prod['product_name'] ?? '');

            $rowsHtml .= '
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:12px 14px; vertical-align:top;">
                    <div style="font-weight:700; color:#111827; font-size:14px; margin-bottom:2px;">' . $name . '</div>
                    <div style="font-size:12px; color:#6b7280;">Category: <strong>' . $cat . '</strong> &bull; SKU: ' . $sku . ' &bull; Loc: ' . $loc . '</div>
                </td>
                <td style="padding:12px 14px; text-align:center; vertical-align:middle;">
                    ' . $badge . '
                </td>
                <td style="padding:12px 14px; text-align:right; vertical-align:middle;">
                    <div style="font-size:14px; font-weight:800; color:' . ($isOut ? '#dc2626' : '#d97706') . ';">' . $qty . ' units</div>
                    <div style="font-size:11px; color:#6b7280;">Reorder at ' . $rp . '</div>
                </td>
                <td style="padding:12px 14px; text-align:right; vertical-align:middle;">
                    <a href="' . htmlspecialchars($itemLink) . '" target="_blank" style="display:inline-block; font-size:12px; font-weight:600; color:#16a34a; text-decoration:none; border:1px solid #86efac; background-color:#f0fdf4; padding:6px 12px; border-radius:4px;">View &rarr;</a>
                </td>
            </tr>';
        }

        $logoPath = __DIR__ . '/../../img/iLogo.png';
        $logoHtml = is_file($logoPath)
            ? '<img src="cid:intelliware_logo" alt="IntelliWare" style="height:36px; max-width:180px; display:block; border:0;" />'
            : '<span style="font-size:22px; font-weight:800; color:#111827; letter-spacing:-0.5px; font-family:sans-serif;">INTELLI<span style="color:#16a34a;">WARE</span></span>';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Inventory Stock Alert Digest</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#1f2937; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6; padding:30px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:680px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.06);" cellspacing="0" cellpadding="0" border="0">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding:22px 28px; background-color:#ffffff; border-bottom:1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        ' . $logoHtml . '
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; background-color:#1e3a8a; color:#ffffff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; padding:4px 10px; border-radius:12px;">
                                            BULK DIGEST ALERT
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Summary Card -->
                    <tr>
                        <td style="padding:24px 28px 12px 28px;">
                            <div style="background-color:#eff6ff; border-left:4px solid #3b82f6; border-radius:6px; padding:14px 16px; margin-bottom:20px;">
                                <h2 style="margin:0 0 4px 0; color:#1e40af; font-size:16px; font-weight:700;">' . $total . ' Products Crossed Inventory Thresholds</h2>
                                <p style="margin:0; color:#1e3a8a; font-size:13px; font-weight:500;">
                                    A recent inventory transaction resulted in <strong>' . $outCount . ' item(s) reaching zero stock</strong> and <strong>' . $lowCount . ' item(s) dropping at or below reorder points</strong>.
                                </p>
                            </div>

                            <!-- Table of items -->
                            <table role="presentation" width="100%" style="border:1px solid #e5e7eb; border-radius:8px; margin-bottom:24px; border-collapse:collapse;" cellspacing="0" cellpadding="0">
                                <thead>
                                    <tr style="background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <th style="padding:10px 14px; text-align:left; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Product &amp; Category</th>
                                        <th style="padding:10px 14px; text-align:center; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Status</th>
                                        <th style="padding:10px 14px; text-align:right; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Stock Level</th>
                                        <th style="padding:10px 14px; text-align:right; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $rowsHtml . '
                                </tbody>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:6px 0 24px 0;">
                                        <a href="' . htmlspecialchars($inventoryUrl) . '" target="_blank" style="display:inline-block; background-color:#16a34a; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:12px 28px; border-radius:6px; box-shadow:0 2px 6px rgba(22, 163, 74, 0.25);">
                                            Open Full Inventory in IntelliWare &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:18px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb; text-align:center;">
                            <p style="margin:0 0 4px 0; color:#6b7280; font-size:12px;">
                                Generated by <strong style="color:#374151;">IntelliWare Inventory Management System</strong> on ' . htmlspecialchars($dateTime) . '.
                            </p>
                            <p style="margin:0; color:#9ca3af; font-size:11px;">
                                Multiple stock updates were batched to prevent inbox spam.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Plain text rendering for batch digest.
     */
    private static function renderBatchDigestPlainText(
        array $items,
        int $outCount,
        int $lowCount,
        string $dateTime,
        string $inventoryUrl
    ): string {
        $total = count($items);
        $text = "INTELLIWARE INVENTORY BATCH ALERT DIGEST\n" .
                "====================================================\n\n" .
                "Total Items: {$total} ({$outCount} Out of Stock, {$lowCount} Low Stock)\n" .
                "Timestamp: {$dateTime}\n\n" .
                "PRODUCTS REQUIRING ATTENTION:\n" .
                "----------------------------------------------------\n";

        foreach ($items as $it) {
            $prod = $it['product'] ?? [];
            $name = $prod['product_name'] ?? 'Unknown';
            $cat  = $prod['category'] ?? 'General';
            $qty  = (int)($prod['quantity'] ?? 0);
            $rp   = (int)($prod['reorder_point'] ?? 10);
            $loc  = $prod['location'] ?? 'Main Warehouse';
            $type = (($it['alert_type'] ?? '') === 'OUT_OF_STOCK' || $qty === 0) ? '[OUT OF STOCK]' : '[LOW STOCK]';

            $text .= "• {$type} {$name} (Category: {$cat})\n";
            $text .= "  Stock: {$qty} units | Reorder Point: {$rp} | Loc: {$loc}\n\n";
        }

        $text .= "Open Inventory: {$inventoryUrl}\n\n" .
                 "---\nIntelliWare WIMS Batch Digest";

        return $text;
    }

    /**
     * Safe application base URL resolver.
     */
    public static function getAppBaseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return "{$scheme}://{$host}/intelliware";
    }

    /**
     * Save HTML preview file in logs directory.
     */
    public static function savePreviewHtml(string $html): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        @file_put_contents($logDir . '/last_email_preview.html', $html);
    }

    /**
     * Render sample HTML for browser preview or testing.
     */
    public static function renderSampleHtml(string $type = 'complete_table', ?mysqli $conn = null): string
    {
        $invData = self::getAllInventoryProducts($conn);
        $dateTime = date('F j, Y - g:i A');
        $inventoryUrl = self::getAppBaseUrl() . '/products.php';

        if ($type === 'complete_table') {
            return self::renderInventoryStatusReportHtml($invData, null, 'REPORT', $dateTime, $inventoryUrl);
        }

        if ($type === 'out_of_stock') {
            $outItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') === 'OUT_OF_STOCK';
            }));
            $triggered = null;
            if (empty($outItems)) {
                $demo = [
                    'product_id'   => 9999,
                    'product_name' => 'The Originote Moisturizer (Demo Out of Stock)',
                    'category'     => 'Skincare',
                    'sku'          => 'P-9999',
                    'location'     => 'Shelf A-01',
                    'quantity'     => 0,
                    'reorder_point'=> 15,
                    'status_key'   => 'OUT_OF_STOCK'
                ];
                $outItems = [$demo];
                $triggered = $demo;
            } else {
                $triggered = $outItems[0];
            }
            $invData['items'] = $outItems;
            return self::renderInventoryStatusReportHtml($invData, $triggered, 'OUT_OF_STOCK', $dateTime, $inventoryUrl);
        }

        if ($type === 'low_stock') {
            $lowItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') === 'LOW_STOCK';
            }));
            $triggered = null;
            if (empty($lowItems)) {
                $demo = [
                    'product_id'   => 470,
                    'product_name' => 'Aquaflask 32 Oz Thumbler (Demo Low Stock)',
                    'category'     => 'Household Items',
                    'sku'          => 'P-0470',
                    'location'     => 'Shelf H',
                    'quantity'     => 6,
                    'reorder_point'=> 10,
                    'status_key'   => 'LOW_STOCK'
                ];
                $lowItems = [$demo];
                $triggered = $demo;
            } else {
                $triggered = $lowItems[0];
            }
            $invData['items'] = $lowItems;
            return self::renderInventoryStatusReportHtml($invData, $triggered, 'LOW_STOCK', $dateTime, $inventoryUrl);
        }

        if ($type === 'batch') {
            $batchItems = array_values(array_filter($invData['items'], function($it) {
                return ($it['status_key'] ?? '') !== 'IN_STOCK';
            }));
            if (empty($batchItems)) {
                $batchItems = [
                    [
                        'product_id'   => 9999,
                        'product_name' => 'The Originote Moisturizer (Demo Out of Stock)',
                        'category'     => 'Skincare',
                        'sku'          => 'P-9999',
                        'location'     => 'Shelf A-01',
                        'quantity'     => 0,
                        'reorder_point'=> 15,
                        'status_key'   => 'OUT_OF_STOCK'
                    ],
                    [
                        'product_id'   => 470,
                        'product_name' => 'Aquaflask 32 Oz Thumbler (Demo Low Stock)',
                        'category'     => 'Household Items',
                        'sku'          => 'P-0470',
                        'location'     => 'Shelf H',
                        'quantity'     => 6,
                        'reorder_point'=> 10,
                        'status_key'   => 'LOW_STOCK'
                    ]
                ];
            }
            $invData['items'] = $batchItems;
            return self::renderInventoryStatusReportHtml($invData, null, 'BATCH', $dateTime, $inventoryUrl, $batchItems);
        }

        return self::renderInventoryStatusReportHtml($invData, null, 'REPORT', $dateTime, $inventoryUrl);
    }

    /**
     * Audit logger for email dispatch attempts.
     */
    private static function logOutcome(string $subject, array $recipients, bool $success, ?string $error): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/email.log';
        $entry = sprintf(
            "[%s] %s | Target: %s | Status: %s%s\n",
            date('Y-m-d H:i:s'),
            $subject,
            implode(', ', $recipients),
            $success ? 'SENT' : 'FAILED',
            $error ? " (Error: {$error})" : ''
        );
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
