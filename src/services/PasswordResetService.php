<?php
/**
 * src/services/PasswordResetService.php
 *
 * Secure Password Reset & Email Verification Service for IntelliWare WIMS.
 * Handles token generation, OTP generation, HTML email dispatch,
 * verification, and password updates.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notifications.php';
require_once __DIR__ . '/EmailService.php';

class PasswordResetService
{
    /**
     * Ensure the password_resets table exists.
     */
    public static function ensureTable(?mysqli $conn): void
    {
        if (!$conn) return;

        $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `otp` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token` (`token`),
            INDEX `idx_otp` (`otp`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        try {
            $conn->query($sql);
        } catch (Throwable $e) {
            error_log('[PasswordResetService] ensureTable error: ' . $e->getMessage());
        }
    }

    /**
     * Mask an email address for privacy display (e.g., m***s@gmail.com).
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Request a password reset code / link for a username or email.
     */
    public static function requestReset(mysqli $conn, string $identifier): array
    {
        self::ensureTable($conn);
        $identifier = trim($identifier);

        if (empty($identifier)) {
            return [
                'success' => false,
                'message' => 'Please enter your registered email address.'
            ];
        }

        // Find user by email, notification_email, or username fallback
        $stmt = $conn->prepare("SELECT user_id, username, full_name, email, notification_email, role FROM users WHERE email = ? OR notification_email = ? OR username = ? LIMIT 1");
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Database error. Please try again later.'
            ];
        }

        $stmt->bind_param("sss", $identifier, $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'No account found registered with "' . htmlspecialchars($identifier) . '". Please check your email address.'
            ];
        }

        // Determine destination email address
        $targetEmail = '';
        if (!empty($user['email']) && filter_var(trim($user['email']), FILTER_VALIDATE_EMAIL)) {
            $targetEmail = trim($user['email']);
        } elseif (!empty($user['notification_email']) && filter_var(trim($user['notification_email']), FILTER_VALIDATE_EMAIL)) {
            $targetEmail = trim($user['notification_email']);
        }

        if (empty($targetEmail)) {
            return [
                'success' => false,
                'message' => 'This account does not have a valid email address configured. Please contact an administrator to reset your password.'
            ];
        }

        $userId = (int)$user['user_id'];
        $fullName = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

        // Invalidate previous active reset codes for this user
        $invStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
        if ($invStmt) {
            $invStmt->bind_param("i", $userId);
            $invStmt->execute();
            $invStmt->close();
        }

        // Generate 6-digit OTP code and secure 64-character token
        try {
            $otp = sprintf('%06d', random_int(100000, 999999));
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $otp = (string)mt_rand(100000, 999999);
            $token = md5(uniqid((string)mt_rand(), true)) . md5(microtime());
        }

        // Set 15-minute expiration
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

        // Insert new reset record
        $insStmt = $conn->prepare("INSERT INTO password_resets (user_id, email, token, otp, expires_at, used) VALUES (?, ?, ?, ?, ?, 0)");
        if (!$insStmt) {
            return [
                'success' => false,
                'message' => 'Failed to initialize password reset. Please try again.'
            ];
        }

        $insStmt->bind_param("issss", $userId, $targetEmail, $token, $otp, $expiresAt);
        $insStmt->execute();
        $insStmt->close();

        // Build Reset URLs
        $baseUrl = EmailService::getAppBaseUrl();
        $directResetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);
        $maskedEmail = self::maskEmail($targetEmail);

        // Build Email HTML Content
        $subject = "IntelliWare - Password Reset Code: {$otp}";
        $htmlBody = self::renderResetEmailHtml($fullName, $user['username'], $otp, $directResetUrl);
        $plainText = "Hello {$fullName},\n\n"
            . "A request has been made to reset the password for your IntelliWare WIMS account (@{$user['username']}).\n\n"
            . "Your 6-digit verification code is: {$otp}\n\n"
            . "Or click the direct reset link below to choose a new password:\n"
            . "{$directResetUrl}\n\n"
            . "This code and link will expire in 15 minutes.\n"
            . "If you did not request this password reset, please ignore this email.\n\n"
            . "— IntelliWare WIMS Support Team";

        // Dispatch Email via PHPMailer / EmailService
        $sendResult = EmailService::sendCustomEmail($conn, $targetEmail, $subject, $htmlBody, $plainText, 'Password Reset');

        $isSent = !empty($sendResult['success']);
        $smtpError = $sendResult['error'] ?? null;

        // Log the request
        $logStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($logStmt) {
            $actionLog = "Password reset requested for {$user['username']} ({$targetEmail}). Email dispatch: " . ($isSent ? 'Success' : 'Failed (' . substr((string)$smtpError, 0, 50) . ')');
            $logStmt->bind_param("is", $userId, $actionLog);
            $logStmt->execute();
            $logStmt->close();
        }

        return [
            'success'      => true,
            'user_id'      => $userId,
            'username'     => $user['username'],
            'email'        => $targetEmail,
            'masked_email' => $maskedEmail,
            'otp'          => $otp,
            'token'        => $token,
            'email_sent'   => $isSent,
            'smtp_error'   => $smtpError,
            'message'      => $isSent
                ? "A 6-digit verification code and reset link have been sent to {$maskedEmail}. Please check your inbox."
                : "Reset code generated for {$maskedEmail}. (Testing mode: use the code shown on screen)."
        ];
    }

    /**
     * Verify a reset token or 6-digit OTP code.
     */
    public static function verifyCodeOrToken(mysqli $conn, string $identifier, string $codeOrToken): array
    {
        self::ensureTable($conn);
        $identifier = trim($identifier);
        $codeOrToken = trim($codeOrToken);

        if (empty($codeOrToken)) {
            return [
                'success' => false,
                'message' => 'Please enter the 6-digit verification code or reset token.'
            ];
        }

        // If an identifier is given, resolve user first
        $userId = null;
        if (!empty($identifier)) {
            $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE username = ? OR email = ? OR notification_email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("sss", $identifier, $identifier, $identifier);
                $stmt->execute();
                $res = $stmt->get_result();
                $u = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($u) {
                    $userId = (int)$u['user_id'];
                }
            }
        }

        // Search for active reset record matching OTP or Token
        if ($userId) {
            $query = "SELECT r.*, u.username, u.full_name, u.email as user_email
                      FROM password_resets r
                      JOIN users u ON u.user_id = r.user_id
                      WHERE r.user_id = ? AND (r.otp = ? OR r.token = ?) AND r.used = 0
                      ORDER BY r.id DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $userId, $codeOrToken, $codeOrToken);
        } else {
            $query = "SELECT r.*, u.username, u.full_name, u.email as user_email
                      FROM password_resets r
                      JOIN users u ON u.user_id = r.user_id
                      WHERE (r.otp = ? OR r.token = ?) AND r.used = 0
                      ORDER BY r.id DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $codeOrToken, $codeOrToken);
        }

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Database error during verification.'
            ];
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $record = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Invalid or already used verification code. Please request a new code.'
            ];
        }

        // Check expiration
        if (strtotime($record['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'This verification code has expired (15-minute limit). Please request a new code.'
            ];
        }

        return [
            'success' => true,
            'record'  => $record
        ];
    }

    /**
     * Reset the user password using a verified code or token.
     */
    public static function resetPassword(
        mysqli $conn,
        string $identifier,
        string $codeOrToken,
        string $newPassword,
        string $confirmPassword
    ): array {
        self::ensureTable($conn);
        $newPassword = trim($newPassword);
        $confirmPassword = trim($confirmPassword);

        if (empty($newPassword)) {
            return [
                'success' => false,
                'message' => 'Please enter a new password.'
            ];
        }

        if (strlen($newPassword) < 4) {
            return [
                'success' => false,
                'message' => 'Password must be at least 4 characters long.'
            ];
        }

        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'Passwords do not match. Please ensure both fields are identical.'
            ];
        }

        $verify = self::verifyCodeOrToken($conn, $identifier, $codeOrToken);
        if (!$verify['success']) {
            return $verify;
        }

        $record = $verify['record'];
        $userId = (int)$record['user_id'];
        $resetId = (int)$record['id'];

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password in users table
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if (!$updateStmt) {
            return [
                'success' => false,
                'message' => 'Failed to prepare password update.'
            ];
        }

        $updateStmt->bind_param("si", $hashedPassword, $userId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            return [
                'success' => false,
                'message' => 'Failed to update password in database. Please try again.'
            ];
        }

        // Mark reset record as used
        $markStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        if ($markStmt) {
            $markStmt->bind_param("i", $resetId);
            $markStmt->execute();
            $markStmt->close();
        }

        // Write to system logs
        $logStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($logStmt) {
            $logAction = "Password reset successfully via email verification (" . $record['email'] . ")";
            $logStmt->bind_param("is", $userId, $logAction);
            $logStmt->execute();
            $logStmt->close();
        }

        return [
            'success' => true,
            'message' => 'Password has been successfully updated! You can now log in with your new password.'
        ];
    }

    /**
     * Render responsive inline-styled HTML template for password reset email.
     */
    private static function renderResetEmailHtml(
        string $fullName,
        string $username,
        string $otp,
        string $directResetUrl
    ): string {
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($directResetUrl, ENT_QUOTES, 'UTF-8');

        // Formatted OTP with spaced characters
        $spacedOtp = implode(' ', str_split($otp));

        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IntelliWare Password Reset</title>
</head>
<body style="margin:0; padding:0; background-color:#0b1120; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#e2e8f0;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0b1120; padding:40px 15px;">
    <tr>
      <td align="center">
        <!-- Main Container -->
        <table role="presentation" width="100%" style="max-width:540px; background-color:#131d38; border:1px solid #233560; border-radius:12px; box-shadow:0 12px 30px rgba(0,0,0,0.4); overflow:hidden;" cellspacing="0" cellpadding="0" border="0">
          
          <!-- Header Banner -->
          <tr>
            <td style="padding:28px 30px; background:linear-gradient(135deg, #1d387b 0%, #0f1c3f 100%); border-bottom:1px solid #2a4178; text-align:center;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center">
                    <img src="cid:intelliware_logo" alt="IntelliWare" style="height:38px; max-width:180px; margin-bottom:8px; display:block;" onerror="this.style.display=\'none\'">
                    <h1 style="margin:0; font-size:20px; font-weight:700; color:#51D55A; letter-spacing:0.5px;">IntelliWare WIMS</h1>
                    <p style="margin:4px 0 0 0; font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Warehouse Inventory Management System</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body Content -->
          <tr>
            <td style="padding:32px 30px;">
              <h2 style="margin:0 0 12px 0; font-size:18px; font-weight:600; color:#ffffff;">Password Reset Request</h2>
              <p style="margin:0 0 18px 0; font-size:14px; line-height:1.6; color:#cbd5e1;">
                Hello <strong style="color:#ffffff;">' . $safeName . '</strong> (<span style="color:#93c5fd;">@' . $safeUser . '</span>),
              </p>
              <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#94a3b8;">
                We received a request to reset your password for your IntelliWare account. Use the 6-digit verification code below to proceed:
              </p>

              <!-- OTP Box -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">
                <tr>
                  <td align="center" style="background:#0a1226; border:2px dashed #3b82f6; border-radius:10px; padding:20px 10px;">
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:1.5px; color:#60a5fa; margin-bottom:8px; font-weight:600;">Verification Code</div>
                    <div style="font-family:\'Courier New\', Courier, monospace; font-size:36px; font-weight:700; letter-spacing:8px; color:#51D55A; line-height:1;">
                      ' . $spacedOtp . '
                    </div>
                    <div style="font-size:12px; color:#64748b; margin-top:8px;">Valid for 15 minutes</div>
                  </td>
                </tr>
              </table>

              <!-- Action Button -->
              <p style="margin:20px 0 10px 0; font-size:13px; color:#94a3b8; text-align:center;">
                Or reset directly using the button below:
              </p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="padding:10px 0 24px 0;">
                    <a href="' . $safeUrl . '" target="_blank" style="background-color:#51D55A; color:#0b1120; font-weight:700; font-size:14px; text-decoration:none; padding:12px 28px; border-radius:6px; display:inline-block; box-shadow:0 4px 12px rgba(81,213,90,0.3);">
                      Reset Password Now
                    </a>
                  </td>
                </tr>
              </table>

              <div style="border-top:1px solid #1e293b; padding-top:18px; margin-top:10px;">
                <p style="margin:0 0 6px 0; font-size:12px; color:#64748b; line-height:1.5;">
                  <strong>Note:</strong> If you did not make this request, you can safely ignore this email. Your current password remains unchanged.
                </p>
                <p style="margin:0; font-size:11px; color:#475569; word-break:break-all;">
                  Direct link: <a href="' . $safeUrl . '" style="color:#38bdf8; text-decoration:underline;">' . $safeUrl . '</a>
                </p>
              </div>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#0d1527; padding:16px 30px; text-align:center; border-top:1px solid #1a2542; font-size:11px; color:#64748b;">
              © ' . date('Y') . ' IntelliWare WIMS. Automated security notification.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}
