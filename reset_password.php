<?php
/**
 * reset_password.php
 *
 * Direct email link gateway for IntelliWare WIMS password resets.
 * Validates the email token and routes users directly to the password set screen.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/services/PasswordResetService.php';

$token = trim($_GET['token'] ?? '');

if (!empty($token)) {
    $verify = PasswordResetService::verifyCodeOrToken($conn, '', $token);
    if ($verify['success']) {
        $account = !empty($verify['record']['email']) ? $verify['record']['email'] : ($verify['record']['username'] ?? '');
        header('Location: forgot_password.php?step=3&account=' . urlencode($account) . '&token=' . urlencode($token));
        exit();
    } else {
        header('Location: forgot_password.php?error=' . urlencode($verify['message']));
        exit();
    }
}

header('Location: forgot_password.php');
exit();
