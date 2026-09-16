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
     * Send a single stock alert email (Out of Stock or Low Stock).
     *
     * @param mysqli|null $conn
     * @param array $product Details: product_name, category, sku, barcode, location, quantity, reorder_point
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
        $category     = $product['category'] ?? 'General';
        $productId    = (int)($product['product_id'] ?? 0);
        $sku          = !empty($product['sku']) ? $product['sku'] : ('P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT));
        $barcode      = !empty($product['barcode']) ? $product['barcode'] : 'N/A';
        $location     = !empty($product['location']) ? $product['location'] : 'Main Warehouse';
        $currentStock = (int)($product['quantity'] ?? 0);
        $reorderPoint = (int)($product['reorder_point'] ?? 10);
        $dateTime     = date('F j, Y - g:i A');

        $isOutOfStock = ($alertType === 'OUT_OF_STOCK' || $currentStock === 0);

        // Subject line indicating urgency
        $subject = $isOutOfStock
            ? "IntelliWare Alert: {$productName} is OUT OF STOCK"
            : "IntelliWare Alert: {$productName} is LOW ON STOCK ({$currentStock} remaining)";

        $inventoryUrl = self::getAppBaseUrl() . '/products.php?search=' . urlencode($productName);

        // Render HTML & Plain Text
        $htmlBody = self::renderSingleAlertHtml(
            $productName,
            $category,
            $sku,
            $barcode,
            $location,
            $currentStock,
            $reorderPoint,
            $dateTime,
            $inventoryUrl,
            $isOutOfStock
        );
        $plainText = self::renderSingleAlertPlainText(
            $productName,
            $category,
            $sku,
            $barcode,
            $location,
            $currentStock,
            $reorderPoint,
            $dateTime,
            $inventoryUrl,
            $isOutOfStock
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

        // If batch only contains 1 item, dispatch standard single email
        if (count($items) === 1) {
            $single = reset($items);
            return self::sendStockAlertEmail($conn, $single['product'], $single['alert_type'], $customRecipient);
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

        // Count totals
        $outCount = 0;
        $lowCount = 0;
        foreach ($items as $it) {
            if (($it['alert_type'] ?? '') === 'OUT_OF_STOCK' || (int)($it['product']['quantity'] ?? 0) === 0) {
                $outCount++;
            } else {
                $lowCount++;
            }
        }
        $totalCount = count($items);

        // Subject line reflecting urgency
        if ($outCount > 0 && $lowCount > 0) {
            $subject = "IntelliWare Alert: {$totalCount} products require attention ({$outCount} Out of Stock, {$lowCount} Low Stock)";
        } elseif ($outCount > 0) {
            $subject = "IntelliWare Alert: {$totalCount} products are OUT OF STOCK";
        } else {
            $subject = "IntelliWare Alert: {$totalCount} products low on stock";
        }

        $inventoryUrl = self::getAppBaseUrl() . '/products.php';
        $dateTime = date('F j, Y - g:i A');

        $htmlBody = self::renderBatchDigestHtml($items, $outCount, $lowCount, $dateTime, $inventoryUrl);
        $plainText = self::renderBatchDigestPlainText($items, $outCount, $lowCount, $dateTime, $inventoryUrl);

        self::savePreviewHtml($htmlBody);

        return self::dispatchMail($settings, $recipients, $subject, $htmlBody, $plainText, "Batch of {$totalCount} items");
    }

    /**
     * Send a test email from the Settings page.
     */
    public static function sendTestEmail(?mysqli $conn, string $testRecipient, string $testType = 'out_of_stock'): array
    {
        if ($testType === 'batch') {
            $sampleBatch = [
                [
                    'alert_type' => 'OUT_OF_STOCK',
                    'product' => [
                        'product_id'   => 101,
                        'product_name' => 'The Originote Moisturizer (Demo)',
                        'category'     => 'Skincare',
                        'sku'          => 'P-0101',
                        'barcode'      => '8997238240012',
                        'location'     => 'Shelf A-01',
                        'quantity'     => 0,
                        'reorder_point'=> 15
                    ]
                ],
                [
                    'alert_type' => 'LOW_STOCK',
                    'product' => [
                        'product_id'   => 102,
                        'product_name' => 'Cerave Hydrating Cleanser (Demo)',
                        'category'     => 'Skincare',
                        'sku'          => 'P-0102',
                        'barcode'      => '3606000537446',
                        'location'     => 'Shelf B-03',
                        'quantity'     => 4,
                        'reorder_point'=> 10
                    ]
                ]
            ];
            return self::sendBatchStockAlertEmail($conn, $sampleBatch, $testRecipient);
        }

        $isOut = ($testType === 'out_of_stock');
        $dummyProduct = [
            'product_id'   => 9999,
            'product_name' => $isOut ? 'The Originote Moisturizer (Test Out of Stock)' : 'Notebook A4 Lined (Test Low Stock)',
            'category'     => $isOut ? 'Skincare' : 'Office Supplies',
            'sku'          => 'P-9999',
            'barcode'      => '8997238240012',
            'location'     => 'Shelf A-01',
            'quantity'     => $isOut ? 0 : 3,
            'reorder_point'=> 10
        ];

        return self::sendStockAlertEmail($conn, $dummyProduct, $isOut ? 'OUT_OF_STOCK' : 'LOW_STOCK', $testRecipient);
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
     * Render clean inline-styled HTML for single product alert.
     */
    public static function renderSingleAlertHtml(
        string $productName,
        string $category,
        string $sku,
        string $barcode,
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
                                    <td style="padding:10px 14px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase;">SKU / Barcode</td>
                                    <td style="padding:10px 14px; font-size:12px; font-family:monospace; color:#4b5563; text-align:right;">' . htmlspecialchars($sku) . ' &bull; ' . htmlspecialchars($barcode) . '</td>
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
        string $barcode,
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
               "Barcode: {$barcode}\n" .
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
    public static function renderSampleHtml(string $type = 'out_of_stock'): string
    {
        if ($type === 'batch') {
            $sampleBatch = [
                [
                    'alert_type' => 'OUT_OF_STOCK',
                    'product' => [
                        'product_id'   => 101,
                        'product_name' => 'The Originote Moisturizer',
                        'category'     => 'Skincare',
                        'sku'          => 'P-0101',
                        'barcode'      => '8997238240012',
                        'location'     => 'Shelf A-01',
                        'quantity'     => 0,
                        'reorder_point'=> 15
                    ]
                ],
                [
                    'alert_type' => 'LOW_STOCK',
                    'product' => [
                        'product_id'   => 102,
                        'product_name' => 'Cerave Hydrating Cleanser',
                        'category'     => 'Skincare',
                        'sku'          => 'P-0102',
                        'barcode'      => '3606000537446',
                        'location'     => 'Shelf B-03',
                        'quantity'     => 4,
                        'reorder_point'=> 10
                    ]
                ]
            ];
            return self::renderBatchDigestHtml($sampleBatch, 1, 1, date('F j, Y - g:i A'), self::getAppBaseUrl() . '/products.php');
        }

        $isOut = ($type === 'out_of_stock');
        return self::renderSingleAlertHtml(
            $isOut ? 'The Originote Moisturizer' : 'Notebook A4 Lined',
            $isOut ? 'Skincare' : 'Office Supplies',
            $isOut ? 'P-0108' : 'P-0205',
            $isOut ? '8997238240012' : '4806512398412',
            $isOut ? 'Shelf A-01' : 'Aisle 3, Bin 12',
            $isOut ? 0 : 4,
            $isOut ? 15 : 10,
            date('F j, Y - g:i A'),
            self::getAppBaseUrl() . '/products.php',
            $isOut
        );
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
