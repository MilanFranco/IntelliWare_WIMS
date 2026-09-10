<?php
/**
 * src/services/SmsService.php
 *
 * Real SMS notification service for IntelliWare.
 * Supports configurable gateways:
 * 1. Semaphore (Philippine SMS Gateway — api.semaphore.co)
 * 2. Twilio (Global SMS API — api.twilio.com)
 * 3. Log/Sandbox (Local developer audit logging in logs/sms.log)
 *
 * Guarantees proper HTTP curl communication, number normalization, and error logging.
 */

require_once __DIR__ . '/../../config/notifications.php';

class SmsService
{
    /**
     * Send an Out-of-Stock alert SMS.
     *
     * @param mysqli|null $conn Database connection to read settings
     * @param array $product Product details containing product_name
     * @param string|null $customNumber Optional override recipient number
     * @return array{success:bool, error:?string, provider:string, sent_to:array}
     */
    public static function sendOutOfStockAlert(?mysqli $conn, array $product, ?string $customNumber = null): array
    {
        $settings = getAllNotificationSettings($conn);

        // Check if SMS channel is enabled
        if (($settings['notif_sms_enabled'] ?? '1') !== '1') {
            return [
                'success' => false,
                'error' => 'SMS notifications are disabled in settings.',
                'provider' => $settings['sms_provider'] ?? 'none',
                'sent_to' => []
            ];
        }

        $rawNumbers = $customNumber ?: ($settings['notif_sms_recipients'] ?? '');
        $numbers = array_filter(array_map('trim', explode(',', (string)$rawNumbers)));

        if (empty($numbers)) {
            return [
                'success' => false,
                'error' => 'No recipient mobile number configured.',
                'provider' => $settings['sms_provider'] ?? 'none',
                'sent_to' => []
            ];
        }

        $productName = $product['product_name'] ?? 'Item';
        // Required exact format
        $message = "IntelliWare Alert: {$productName} is OUT OF STOCK. Current stock: 0. Please restock.";

        $provider = strtolower(trim($settings['sms_provider'] ?? 'semaphore'));
        $apiKey   = trim($settings['sms_api_key'] ?? '');

        // If no API key is provided, safely log to local sandbox without throwing
        if (empty($apiKey) || $provider === 'log' || $provider === 'sandbox') {
            $logResult = self::logToSmsAudit($numbers, $message, 'sandbox_no_key');
            return [
                'success'  => true,
                'error'    => null,
                'provider' => 'sandbox_logger',
                'sent_to'  => $numbers
            ];
        }

        switch ($provider) {
            case 'semaphore':
                return self::sendViaSemaphore($apiKey, $numbers, $message, $settings);

            case 'twilio':
                return self::sendViaTwilio($apiKey, $numbers, $message, $settings);

            default:
                $err = "Unsupported SMS provider: {$provider}";
                self::logToSmsAudit($numbers, $message, 'failed: ' . $err);
                return [
                    'success'  => false,
                    'error'    => $err,
                    'provider' => $provider,
                    'sent_to'  => $numbers
                ];
        }
    }

    /**
     * Send SMS via Semaphore (Philippine SMS API).
     */
    private static function sendViaSemaphore(string $apiKey, array $numbers, string $message, array $settings): array
    {
        $senderName = trim($settings['sms_from_number'] ?? '');
        $url = 'https://api.semaphore.co/api/v4/messages';
        $allSuccess = true;
        $errors = [];
        $sentNumbers = [];

        foreach ($numbers as $rawNumber) {
            $cleanNum = self::normalizePhilippineNumber($rawNumber);
            if (!$cleanNum) {
                $errors[] = "Invalid mobile number: {$rawNumber}";
                $allSuccess = false;
                continue;
            }

            $postData = [
                'apikey'  => $apiKey,
                'number'  => $cleanNum,
                'message' => $message,
            ];
            if (!empty($senderName) && strtolower($senderName) !== 'intelliware') {
                $postData['sendername'] = $senderName;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 8,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $allSuccess = false;
                $errors[] = "cURL error ({$cleanNum}): {$curlErr}";
                self::logToSmsAudit([$cleanNum], $message, "Semaphore cURL Error: {$curlErr}");
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                $sentNumbers[] = $cleanNum;
                self::logToSmsAudit([$cleanNum], $message, "Semaphore Sent (HTTP {$httpCode})");
            } else {
                $allSuccess = false;
                $errors[] = "Semaphore API error HTTP {$httpCode}: {$response}";
                self::logToSmsAudit([$cleanNum], $message, "Semaphore Error HTTP {$httpCode}: {$response}");
            }
        }

        return [
            'success'  => $allSuccess && count($sentNumbers) > 0,
            'error'    => !empty($errors) ? implode('; ', $errors) : null,
            'provider' => 'semaphore',
            'sent_to'  => $sentNumbers
        ];
    }

    /**
     * Send SMS via Twilio REST API.
     */
    private static function sendViaTwilio(string $authToken, array $numbers, string $message, array $settings): array
    {
        $accountSid = trim($settings['sms_account_sid'] ?? '');
        $fromNumber = trim($settings['sms_from_number'] ?? '');

        if (empty($accountSid)) {
            $err = 'Twilio Account SID is missing.';
            self::logToSmsAudit($numbers, $message, $err);
            return [
                'success'  => false,
                'error'    => $err,
                'provider' => 'twilio',
                'sent_to'  => []
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $allSuccess = true;
        $errors = [];
        $sentNumbers = [];

        foreach ($numbers as $rawNumber) {
            $cleanNum = self::normalizeE164Number($rawNumber);
            if (!$cleanNum) {
                $errors[] = "Invalid E.164 number: {$rawNumber}";
                $allSuccess = false;
                continue;
            }

            $postData = [
                'To'   => $cleanNum,
                'From' => $fromNumber ?: 'IntelliWare',
                'Body' => $message,
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 8,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $allSuccess = false;
                $errors[] = "Twilio cURL error ({$cleanNum}): {$curlErr}";
                self::logToSmsAudit([$cleanNum], $message, "Twilio cURL Error: {$curlErr}");
            } elseif ($httpCode === 200 || $httpCode === 201) {
                $sentNumbers[] = $cleanNum;
                self::logToSmsAudit([$cleanNum], $message, "Twilio Sent (HTTP {$httpCode})");
            } else {
                $allSuccess = false;
                $errors[] = "Twilio API error HTTP {$httpCode}: {$response}";
                self::logToSmsAudit([$cleanNum], $message, "Twilio Error HTTP {$httpCode}: {$response}");
            }
        }

        return [
            'success'  => $allSuccess && count($sentNumbers) > 0,
            'error'    => !empty($errors) ? implode('; ', $errors) : null,
            'provider' => 'twilio',
            'sent_to'  => $sentNumbers
        ];
    }

    /**
     * Normalize Philippine local phone numbers (e.g. 0917..., +63917..., 63917... -> 0917...).
     */
    public static function normalizePhilippineNumber(string $phone): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return '09' . substr($digits, 3);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }
        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return $digits;
        }
        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Normalize phone numbers to E.164 standard (e.g. +639171234567).
     */
    public static function normalizeE164Number(string $phone): ?string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            $digits = preg_replace('/[^\d]/', '', $phone);
            return '+' . $digits;
        }
        $digits = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '+63' . substr($digits, 1);
        }
        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return '+' . $digits;
        }
        return '+' . $digits;
    }

    /**
     * Persistent logging for SMS messages to logs/sms.log for auditing & sandbox development.
     */
    private static function logToSmsAudit(array $numbers, string $message, string $status): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/sms.log';
        $entry = sprintf(
            "[%s] Recipient(s): %s | Status: %s | Message: \"%s\"\n",
            date('Y-m-d H:i:s'),
            implode(', ', $numbers),
            $status,
            $message
        );
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
