<?php
/**
 * src/services/EmailService.php
 *
 * Out-of-Stock email notification service for IntelliWare using PHPMailer.
 * Features:
 * - Direct SMTP sending (Gmail, Outlook, Amazon SES, SendGrid, Mailgun, or custom SMTP).
 * - Embedded IntelliWare header logo via CID (works in all email clients without broken image links).
 * - UI-connected dark-themed HTML design matching IntelliWare's palette (#0b1022, #132044, #51D55A, #ef4444).
 * - Clean plaintext AltBody.
 * - Non-blocking error handling (never crashes inventory transactions).
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
     * Send an Out-of-Stock alert email.
     *
     * @param mysqli|null $conn Database connection to fetch runtime settings
     * @param array $product Product details (product_name, sku, barcode, location, etc.)
     * @param string|null $customRecipient Optional override recipient(s)
     * @return array{success:bool, error:?string, sent_to:array}
     */
    public static function sendOutOfStockAlert(?mysqli $conn, array $product, ?string $customRecipient = null): array
    {
        $settings = getAllNotificationSettings($conn);

        // Check if email channel is enabled
        if (($settings['notif_email_enabled'] ?? '1') !== '1') {
            return [
                'success' => false,
                'error' => 'Email notifications are disabled in settings.',
                'sent_to' => []
            ];
        }

        // Determine recipients
        $rawRecipients = $customRecipient ?: ($settings['notif_email_recipients'] ?? '');
        $recipients = array_filter(array_map('trim', explode(',', (string)$rawRecipients)));

        // Fallback to user email if no recipient specified
        if (empty($recipients) && $conn) {
            $userQuery = $conn->query("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != '' LIMIT 1");
            if ($userQuery && $user = $userQuery->fetch_assoc()) {
                $recipients[] = $user['email'];
            }
        }

        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'No recipient email addresses configured.',
                'sent_to' => []
            ];
        }

        // Product fields
        $productName = $product['product_name'] ?? 'Unknown Product';
        $productId   = (int)($product['product_id'] ?? 0);
        $sku         = !empty($product['sku']) ? $product['sku'] : ('P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT));
        $barcode     = !empty($product['barcode']) ? $product['barcode'] : 'N/A';
        $location    = !empty($product['location']) ? $product['location'] : 'Main Warehouse';
        $dateTime    = date('F j, Y - g:i A');

        // Check if SMTP is configured
        $smtpHost = trim($settings['smtp_host'] ?? '');
        $smtpUser = trim($settings['smtp_username'] ?? '');
        $smtpPass = trim($settings['smtp_password'] ?? '');
        $smtpPort = (int)($settings['smtp_port'] ?? 587);
        $fromEmail = trim($settings['smtp_from_email'] ?? 'alerts@intelliware.ph');
        $fromName  = trim($settings['smtp_from_name'] ?? 'IntelliWare Inventory');

        // Always save a visual HTML preview to logs/last_email_preview.html for developer review
        $previewHtml = self::renderHtmlTemplate($productName, $sku, $barcode, $location, $dateTime, true, true);
        self::savePreviewHtml($previewHtml);

        // If SMTP credentials are missing, do not attempt mail() on Windows localhost (avoids "Could not instantiate mail function")
        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            $msg = 'SMTP credentials not configured. Please enter your SMTP Username (e.g. Gmail address) and Password (e.g. Gmail App Password) under "Configure SMTP & SMS Gateway", then click "Save Preferences".';
            self::logLocalEmailFallback($productName, $recipients, 'Missing SMTP credentials (simulated preview saved)');
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
            $mail->Timeout    = 8; // 8 second timeout to avoid stalling

            // Sender & Recipients
            $mail->setFrom($fromEmail, $fromName);
            foreach ($recipients as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($recipient);
                }
            }

            // Embed IntelliWare Logo as CID
            $logoPath = __DIR__ . '/../../img/iLogo.png';
            $hasEmbeddedLogo = false;
            if (is_file($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'intelliware_logo', 'iLogo.png', 'base64', 'image/png');
                $hasEmbeddedLogo = true;
            }

            // Subject
            $mail->Subject = '[IntelliWare] Product Out of Stock Alert';

            // HTML Body (Themed to IntelliWare\'s dark UI)
            $mail->isHTML(true);
            $mail->Body = self::renderHtmlTemplate($productName, $sku, $barcode, $location, $dateTime, $hasEmbeddedLogo);

            // Plain text AltBody
            $mail->AltBody = self::renderPlainTextBody($productName, $sku, $barcode, $location, $dateTime);

            // Attempt send
            $mail->send();

            return [
                'success' => true,
                'error'   => null,
                'sent_to' => $recipients
            ];
        } catch (Exception $e) {
            $errMsg = $mail->ErrorInfo ?: $e->getMessage();
            if (stripos($errMsg, 'Could not authenticate') !== false || stripos($errMsg, 'BadCredentials') !== false || stripos($errMsg, '535') !== false) {
                $errMsg = 'Gmail Authentication Error: Google requires a 16-character "App Password" for SMTP instead of your standard Gmail password. Please generate an App Password at https://myaccount.google.com/apppasswords and update the SMTP Password in Settings.';
            }
            error_log('[IntelliWare Notification] Email send error: ' . $errMsg);

            // In local offline/XAMPP environment without active internet/SMTP credentials,
            // log the full email payload for audit and testing
            self::logLocalEmailFallback($productName, $recipients, $errMsg);

            return [
                'success' => false,
                'error'   => $errMsg,
                'sent_to' => $recipients
            ];
        }
    }

    /**
     * Send a test email from the Settings page.
     */
    public static function sendTestEmail(?mysqli $conn, string $testRecipient): array
    {
        $dummyProduct = [
            'product_id'   => 9999,
            'product_name' => 'The Originote Moisturizer (Test Item)',
            'sku'          => 'P-9999',
            'barcode'      => '8997238240012',
            'location'     => 'Shelf A-01',
            'quantity'     => 0
        ];
        return self::sendOutOfStockAlert($conn, $dummyProduct, $testRecipient);
    }

    /**
     * Build Plain Text Body strictly following the required specification.
     */
    private static function renderPlainTextBody(string $productName, string $sku, string $barcode, string $location, string $dateTime): string
    {
        return "Product Out of Stock\n\n" .
               "Product: {$productName}\n" .
               "SKU: {$sku}\n" .
               "Barcode: {$barcode}\n" .
               "Current Stock: 0\n" .
               "Location: {$location}\n" .
               "Date/Time: {$dateTime}\n\n" .
               "The product is currently out of stock. Please restock this item.";
    }

    /**
     * Build visually stunning HTML Email connected to IntelliWare UI aesthetics.
     */
    public static function renderHtmlTemplate(
        string $productName,
        string $sku,
        string $barcode,
        string $location,
        string $dateTime,
        bool $hasEmbeddedLogo,
        bool $isLocalBrowser = false
    ): string {
        $logoSrc = 'cid:intelliware_logo';
        if ($isLocalBrowser) {
            $logoPath = __DIR__ . '/../../img/iLogo.png';
            if (is_file($logoPath)) {
                $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
        }

        $logoHtml = $hasEmbeddedLogo
            ? '<img src="' . $logoSrc . '" alt="IntelliWare" style="height:44px; max-width:200px; object-fit:contain; display:block;" />'
            : '<span style="font-size:24px; font-weight:800; color:#FFFFFF; letter-spacing:-0.5px; font-family:\'Onest\',sans-serif;">INTELLI<span style="color:#51D55A;">WARE</span></span>';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Out of Stock Alert</title>
</head>
<body style="margin:0; padding:0; background-color:#070b19; font-family:\'Onest\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#E3E3E3; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#070b19; padding:40px 15px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="100%" style="max-width:580px; background:linear-gradient(180deg, #11204a 0%, #0c1635 100%); border:1px solid #2A4484; border-radius:16px; overflow:hidden; box-shadow:0 15px 35px rgba(0,0,0,0.5);" cellspacing="0" cellpadding="0" border="0">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td style="padding:28px 32px; background:rgba(7, 11, 25, 0.6); border-bottom:1px solid rgba(42, 68, 132, 0.45);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        ' . $logoHtml . '
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; background:rgba(239, 68, 68, 0.15); border:1px solid rgba(239, 68, 68, 0.4); color:#fca5a5; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; padding:4px 10px; border-radius:20px;">
                                            &bull; Urgent Alert
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Alert Banner -->
                    <tr>
                        <td style="padding:32px 32px 16px 32px;">
                            <div style="background:rgba(239, 68, 68, 0.12); border-left:4px solid #ef4444; border-radius:8px; padding:16px 20px; margin-bottom:24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td width="36" style="vertical-align:top;">
                                            <div style="width:28px; height:28px; border-radius:50%; background:#ef4444; color:#ffffff; font-weight:bold; font-size:16px; text-align:center; line-height:28px;">!</div>
                                        </td>
                                        <td style="vertical-align:middle; padding-left:10px;">
                                            <h2 style="margin:0 0 4px 0; color:#ffffff; font-size:18px; font-weight:700; letter-spacing:-0.02em;">Product Out of Stock</h2>
                                            <p style="margin:0; color:#fca5a5; font-size:13px; font-weight:500;">
                                                The product is currently out of stock. Please restock this item.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Product Info Card -->
                            <table role="presentation" width="100%" style="background:#091129; border:1px solid #1D387B; border-radius:12px; padding:20px; margin-bottom:24px;" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding:8px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35);" width="35%">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Product</span>
                                    </td>
                                    <td style="padding:8px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35); text-align:right;">
                                        <strong style="color:#ffffff; font-size:15px; font-weight:700;">' . htmlspecialchars($productName) . '</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35);">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">SKU</span>
                                    </td>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35); text-align:right;">
                                        <span style="background:rgba(42, 68, 132, 0.35); color:#cbd5e1; font-family:monospace; padding:3px 8px; border-radius:4px; font-size:12px;">' . htmlspecialchars($sku) . '</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35);">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Barcode</span>
                                    </td>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35); text-align:right;">
                                        <span style="color:#E3E3E3; font-family:monospace; font-size:13px;">' . htmlspecialchars($barcode) . '</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35);">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Current Stock</span>
                                    </td>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35); text-align:right;">
                                        <span style="display:inline-block; background:rgba(239, 68, 68, 0.2); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.4); font-weight:800; font-size:13px; padding:2px 10px; border-radius:12px;">
                                            0 units
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35);">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Location</span>
                                    </td>
                                    <td style="padding:10px 0; border-bottom:1px solid rgba(42, 68, 132, 0.35); text-align:right;">
                                        <span style="color:#cbd5e1; font-size:13px;">' . htmlspecialchars($location) . '</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0 2px 0;">
                                        <span style="color:#9aa6d1; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Date / Time</span>
                                    </td>
                                    <td style="padding:10px 0 2px 0; text-align:right;">
                                        <span style="color:#cbd5e1; font-size:12px;">' . htmlspecialchars($dateTime) . '</span>
                                    </td>
                                </tr>
                            </table>

                            <!-- Call to action button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:8px 0 20px 0;">
                                        <a href="http://localhost/intelliware/products.php?search=' . urlencode($productName) . '" style="display:inline-block; background:#51D55A; color:#0B132B; font-size:14px; font-weight:700; text-decoration:none; padding:12px 28px; border-radius:8px; box-shadow:0 4px 14px rgba(81, 213, 90, 0.35);">
                                            Open in IntelliWare &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 32px; background:rgba(5, 8, 20, 0.7); border-top:1px solid rgba(42, 68, 132, 0.35); text-align:center;">
                            <p style="margin:0 0 6px 0; color:#64748b; font-size:12px;">
                                This is an automated real-time notification generated by <strong style="color:#94a3b8;">IntelliWare Inventory Management System</strong>.
                            </p>
                            <p style="margin:0; color:#475569; font-size:11px;">
                                &copy; ' . date('Y') . ' IntelliWare WIMS. All rights reserved.
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
     * Render sample HTML for browser preview.
     */
    public static function renderSampleHtml(): string
    {
        return self::renderHtmlTemplate(
            'The Originote Moisturizer',
            'P-0108',
            '8997238240012',
            'Shelf A-01',
            date('F j, Y - g:i A'),
            true,
            true
        );
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
     * Safe fallback logger for local environments where SMTP credentials aren't yet entered.
     */
    private static function logLocalEmailFallback(string $productName, array $recipients, string $reason): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/email.log';
        $entry = sprintf(
            "[%s] OUT_OF_STOCK Alert | Product: %s | Recipients: %s | Status: Local Logged (Reason: %s)\n",
            date('Y-m-d H:i:s'),
            $productName,
            implode(', ', $recipients),
            $reason
        );
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
