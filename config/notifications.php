<?php
/**
 * config/notifications.php
 *
 * Central configuration and helper loader for IntelliWare notifications.
 * Supports runtime database settings (system_settings), environment variables,
 * or default fallback configurations.
 */

if (!defined('INTELLIWARE_CONFIG_LOADED')) {
    define('INTELLIWARE_CONFIG_LOADED', true);
}

/**
 * Default notification configuration fallback.
 */
function getNotificationDefaults(): array
{
    return [
        // Master channel switches
        'notif_system_enabled'   => getenv('NOTIF_SYSTEM_ENABLED') !== false ? getenv('NOTIF_SYSTEM_ENABLED') : '1',
        'notif_email_enabled'    => getenv('NOTIF_EMAIL_ENABLED') !== false ? getenv('NOTIF_EMAIL_ENABLED') : '1',
        'notif_sms_enabled'      => getenv('NOTIF_SMS_ENABLED') !== false ? getenv('NOTIF_SMS_ENABLED') : '1',

        // Recipients
        'notif_email_recipients' => getenv('NOTIF_EMAIL_RECIPIENTS') ?: 'admin@intelliware.ph',
        'notif_sms_recipients'   => getenv('NOTIF_SMS_RECIPIENTS') ?: '',

        // SMTP Settings for PHPMailer
        'smtp_host'              => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port'              => getenv('SMTP_PORT') ?: '587',
        'smtp_username'          => getenv('SMTP_USERNAME') ?: 'support.intelliware@gmail.com',
        'smtp_password'          => getenv('SMTP_PASSWORD') ?: 'support.intelliware@2026',
        'smtp_encryption'        => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls' or 'ssl'
        'smtp_from_email'        => getenv('SMTP_FROM_EMAIL') ?: 'support.intelliware@gmail.com',
        'smtp_from_name'         => getenv('SMTP_FROM_NAME') ?: 'IntelliWare Inventory',

        // SMS Gateway Settings (semaphore / twilio / log)
        'sms_provider'           => getenv('SMS_PROVIDER') ?: 'semaphore',
        'sms_api_key'            => getenv('SMS_API_KEY') ?: '',
        'sms_account_sid'        => getenv('SMS_ACCOUNT_SID') ?: '',
        'sms_from_number'        => getenv('SMS_FROM_NUMBER') ?: 'IntelliWare',
    ];
}

/**
 * Fetch all notification settings from system_settings, merged with defaults.
 */
function getAllNotificationSettings(?mysqli $conn = null): array
{
    $settings = getNotificationDefaults();

    if ($conn) {
        try {
            $res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if ($row['setting_value'] !== null && $row['setting_value'] !== '') {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall back cleanly to defaults if table is unavailable
        }
    }

    return $settings;
}

/**
 * Fetch a single notification setting by key.
 */
function getNotificationSetting(?mysqli $conn, string $key, $default = null)
{
    $all = getAllNotificationSettings($conn);
    return $all[$key] ?? $default;
}

/**
 * Save or update a notification setting in system_settings table.
 */
function saveNotificationSetting(mysqli $conn, string $key, string $value): bool
{
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
