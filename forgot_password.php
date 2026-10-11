<?php
/**
 * forgot_password.php
 *
 * Password Reset Page for IntelliWare WIMS.
 * 3-Step Seamless Password Recovery Flow:
 *   Step 1: Enter registered email -> Send Verification Code
 *   Step 2: Enter 6-digit code -> Confirm Code
 *   Step 3: Set New Password -> Update Password
 * 
 * Styled for 100% design consistency with signin.php:
 *   - Same background & animated mesh
 *   - Same fixed bottom-center toast placement & animations
 *   - Same card styling, shadows, fonts, and fixed footer
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/services/PasswordResetService.php';

PasswordResetService::ensureTable($conn);

$accountParam = trim($_GET['account'] ?? $_POST['account'] ?? '');
$tokenParam   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$stepParam    = (int)($_GET['step'] ?? $_POST['step'] ?? 1);

// If token is provided in URL, automatically enter step 3
if (!empty($tokenParam)) {
    $stepParam = 3;
}

$apiResponse = null;

// Handle AJAX or Form POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($action === 'request_code') {
        $identifier = trim($_POST['identifier'] ?? '');
        $result = PasswordResetService::requestReset($conn, $identifier);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }
        $apiResponse = $result;
        if ($result['success']) {
            $stepParam = 2;
            $accountParam = $result['email'] ?? $result['username'] ?? '';
        }
    } elseif ($action === 'verify_code') {
        $identifier = trim($_POST['identifier'] ?? '');
        $code = trim($_POST['verification_code'] ?? $_POST['token'] ?? '');
        $result = PasswordResetService::verifyCodeOrToken($conn, $identifier, $code);

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'token'   => $result['record']['token'] ?? '',
                    'message' => 'Code confirmed! Now set your new password.'
                ]);
            } else {
                echo json_encode($result);
            }
            exit();
        }

        $apiResponse = $result;
        if ($result['success']) {
            $stepParam = 3;
            $tokenParam = $result['record']['token'] ?? '';
            $accountParam = $result['record']['email'] ?? $identifier;
        }
    } elseif ($action === 'reset_password') {
        $identifier = trim($_POST['identifier'] ?? '');
        $code = trim($_POST['verification_code'] ?? $_POST['token'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $result = PasswordResetService::resetPassword($conn, $identifier, $code, $newPass, $confirmPass);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }

        $apiResponse = $result;
        if ($result['success']) {
            header('Location: signin.php?success=' . urlencode('Password reset successfully! Please sign in with your new password.'));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="img/systemlogo.svg?v=2">
    <title>IntelliWare - Reset Password</title>
    <script src="assets/vendor/tailwindcss.js"></script>
    <link href="assets/css/styles.css?v=1.0" rel="stylesheet">
    <link href="assets/fonts.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* --- EXACT BACKGROUND MATCH WITH SIGNIN.PHP --- */
        body {
            font-family: "Onest", sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 25%, #16213e 50%, #0f0f23 75%, #1a1a2e 100%);
            background-size: 400% 400%;
            background-attachment: fixed;
            animation: gradientShift 15s ease infinite;
            color: #E3E3E3;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(81, 213, 90, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(96, 165, 250, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* --- WRAPPER (MATCH SIGNIN.PHP) --- */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 40px 20px 80px;
            box-sizing: border-box;
        }

        /* --- HEADER (MATCH SIGNIN.PHP) --- */
        .header { 
            margin-top: auto; 
            margin-bottom: 60px; 
            text-align: center;
            width: 100%;
        }
        
        .header .signin-logo { 
            width: clamp(200px, 70vw, 460px); 
            height: auto; 
            display: block; 
            margin-left: auto; 
            margin-right: auto; 
            animation: fadeIn 2s ease-in-out;
        }

        .auth-wrapper .container,
        .container {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 0;
            padding-bottom: 0;
            margin-bottom: auto;
            width: 100%;
        }

        /* --- CARD (MATCH SIGNINBOX) --- */
        .reset-box {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 10px;
            width: 340px; 
            max-width: 90vw;
            padding: 24px 22px;
            box-shadow: 0 0 20px 2px #304374;
            margin-bottom: 15px;
            text-align: left;
            position: relative;
            transition: all 0.3s ease;
        }

        .reset-box h3 {
            font-family: "Overpass", sans-serif;
            font-size: 20px;
            color: #E3E3E3;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reset-box .subtext {
            font-family: "Onest", sans-serif;
            font-size: 12px;
            color: #67e78c;
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .reset-box hr {
            border: none;
            border-top: 0.2px solid #2f4b91; 
            width: 100%;
            margin-bottom: 14px;
        }

        .tfieldname {
            text-align: left;
            font-family: "Onest", sans-serif;
            font-weight: 500;
            color: #E3E3E3;
            margin-bottom: 5px;
            margin-top: 12px;
            font-size: 12px;
        }

        .tf {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 4px;
            flex-direction: column;
            width: 100%;
            position: relative;
        }

        .tf input {
            box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2);
            color: white;
            background-color: #13275B;
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #304374;
            font-family: "Onest", sans-serif;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .tf input:focus {
            outline: none;
            border-color: #51D55A;
            box-shadow: 0 0 0 1px #51D55A;
        }

        .tf input.otp-input {
            font-family: 'Courier New', Courier, monospace;
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 6px;
            text-align: center;
            color: #51D55A;
        }

        /* Password Eye (MATCH SIGNIN.PHP) */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #E3E3E3;
            font-size: 16px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover { opacity: 1; color: #51D55A; }
        .eye-closed { display: none; }

        /* Action Buttons (MATCH BTNLOGIN IN SIGNIN.PHP) */
        .btn-submit {
            border: 0.2px solid transparent;
            font-family: "Onest", sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: white;
            border-radius: 5px;
            background-color: #51D55A;
            width: 100%;
            padding: 10px;
            margin-top: 14px;
            margin-bottom: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background-color: #3d9743;
            border: 0.2px solid #51D55A;
            transform: scale(1.02);
        }

        .btn-submit:active {
            background-color: #3ea147;
            transform: scale(0.98);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Step Indicators */
        .step-progress {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 0 2px;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .step-item.active {
            color: #51D55A;
        }

        .step-item.completed {
            color: #51D55A;
        }

        .step-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #13275B;
            border: 1px solid #304374;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10.5px;
            color: #94a3b8;
            font-weight: 700;
        }

        .step-item.active .step-circle,
        .step-item.completed .step-circle {
            background: #51D55A;
            border-color: #51D55A;
            color: #0b1120;
        }

        .step-line {
            flex: 1;
            height: 2px;
            background: #23386b;
            margin: 0 6px;
            transition: background 0.3s;
        }

        .step-line.active {
            background: #51D55A;
        }

        .info-pill {
            background: rgba(30, 58, 138, 0.4);
            border: 1px solid #2b498d;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 8px;
            font-size: 11.5px;
            color: #cbd5e1;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .dev-badge {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid #d97706;
            border-radius: 6px;
            padding: 8px 10px;
            margin-top: 6px;
            margin-bottom: 8px;
            font-size: 11px;
            color: #fde68a;
            line-height: 1.35;
        }

        .dev-badge strong {
            color: #fbbf24;
            display: block;
            margin-bottom: 2px;
        }

        .code-chip {
            background: #0b1120;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 700;
            color: #51D55A;
            border: 1px solid #22c55e;
            cursor: pointer;
            display: inline-block;
            margin-top: 4px;
        }

        .back-link {
            text-align: center;
            margin-top: 10px;
            font-size: 11.5px;
        }

        .back-link a {
            color: #51D55A;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.2s;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        /* --- TOAST NOTIFICATIONS (EXACT MATCH TO SIGNIN.PHP LOCATION & ANIMATION) --- */
        .toast {
            position: fixed; 
            bottom: 30px; 
            left: 50%; 
            transform: translateX(-50%);
            padding: 15px 25px; 
            border-radius: 10px; 
            font-family: 'Onest', sans-serif; 
            font-size: 14px; 
            color: #fff; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 100005; 
            animation: fadein 0.5s, fadeout 0.5s 3.5s;
            pointer-events: auto;
            max-width: 90vw;
            text-align: center;
            cursor: pointer;
        }
        .toast.error { background-color: #dc3545; }
        .toast.success { background-color: #51D55A; color: #ffffff; }
        .toast.warning { background-color: #d97706; }

        @keyframes fadein { from { opacity: 0; bottom: 20px; } to { opacity: 1; bottom: 30px; } }
        @keyframes fadeout { from { opacity: 1; bottom: 30px; } to { opacity: 0; bottom: 20px; } }

        /* --- FOOTER (EXACT MATCH TO SIGNIN.PHP) --- */
        .footer {
            position: fixed;
            bottom: 40px;
            text-align: center;
            font-family: "Overpass", sans-serif;
            font-weight: 200;
            font-size: 12px;
            color: rgb(168, 168, 168);
            padding: 10px 0;
            width: 100%;
        }

        .footer hr {
            border: none;
            border-top: 0.2px solid #314f9b; 
            margin: 10px auto;
            width: 320px; 
            max-width: 90vw;
        }

        .footer a { color: rgb(168, 168, 168); margin: 0 5px; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }

        .spinner {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .auth-wrapper {
                padding-top: 24px;
                padding-bottom: 90px;
                justify-content: flex-start;
            }
            .header {
                margin-top: 12px;
                margin-bottom: 14px;
            }
            .auth-wrapper .container,
            .container {
                margin-bottom: 0;
            }
            .reset-box {
                padding: 18px 16px;
                width: 100%;
                max-width: 100%;
            }
            .step-item span {
                font-size: 9.5px;
            }
            .tf input.otp-input {
                font-size: 17px;
                letter-spacing: 4px;
            }
            .footer {
                bottom: 20px;
                font-size: 11px;
            }
        }

        /* --- AUTH MODALS (ABOUT & CONTACT) - EXPANSIVE & CLEAN --- */
        .auth-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: rgba(6, 10, 24, 0.82);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100010;
            padding: 20px;
            box-sizing: border-box;
        }

        .auth-modal-overlay.active {
            display: flex;
            animation: authModalFade 0.22s ease-out;
        }

        @keyframes authModalFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .auth-modal-card {
            background: #11224d;
            border: 1px solid #2f4b91;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.65), 0 0 35px rgba(61, 116, 255, 0.12);
            border-radius: 14px;
            width: 94vw;
            max-width: 980px;
            height: auto;
            max-height: 90vh;
            min-height: 520px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            color: #E3E3E3;
            font-family: "Onest", sans-serif;
            animation: authModalZoom 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes authModalZoom {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .auth-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 28px;
            border-bottom: 1px solid #24396b;
            background: #0e1c40;
            flex-shrink: 0;
        }

        .auth-modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #FFFFFF;
            margin: 0;
            font-family: "Onest", sans-serif;
        }

        .auth-modal-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 26px;
            line-height: 1;
            padding: 2px 6px;
            transition: color 0.2s ease;
        }

        .auth-modal-close:hover {
            color: #FFFFFF;
        }

        .auth-modal-body {
            padding: 32px 36px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.65;
            color: #cbd5e1;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .auth-desc-text {
            margin-bottom: 28px;
            color: #e2e8f0;
            font-size: 18px;
            line-height: 1.75;
            text-align: center;
            max-width: 860px;
            margin-left: auto;
            margin-right: auto;
        }

        .auth-section-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: #51D55A;
            margin-bottom: 18px;
            margin-top: 4px;
            text-align: center;
            width: 100%;
        }

        /* 3-Column Developer Grid (About Modal) - Centered */
        .auth-dev-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 260px));
            justify-content: center;
            gap: 24px;
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
        }

        .auth-dev-photo-card {
            background: #162b60;
            border: 1px solid #264385;
            border-radius: 12px;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .auth-dev-photo-card:hover {
            border-color: #3d74ff;
            transform: translateY(-2px);
        }

        .auth-dev-photo-placeholder,
        .auth-dev-photo-wrapper {
            width: 145px;
            height: 145px;
            border-radius: 12px;
            background: #0d1a3c;
            border: 2px solid #2f4b91;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7990c7;
            font-size: 12.5px;
            font-weight: 500;
            margin-bottom: 15px;
            text-align: center;
            box-sizing: border-box;
            user-select: none;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .auth-dev-photo-placeholder {
            border-style: dashed;
            padding: 10px;
        }

        .auth-dev-photo-card:hover .auth-dev-photo-wrapper {
            border-color: #3d74ff;
            box-shadow: 0 8px 20px rgba(61, 116, 255, 0.25);
        }

        .auth-dev-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .auth-dev-name {
            font-weight: 600;
            font-size: 15px;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .auth-dev-role {
            font-size: 12.5px;
            color: #8da2d4;
        }

        /* 3-Column Contact Grid (Contact Modal) - Centered */
        .auth-contact-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 270px));
            justify-content: center;
            gap: 24px;
            width: 100%;
            max-width: 880px;
            margin: 0 auto;
        }

        .auth-contact-card {
            background: #162b60;
            border: 1px solid #264385;
            border-radius: 12px;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s ease, transform 0.2s ease;
            text-align: center;
        }

        .auth-contact-card:hover {
            border-color: #3d74ff;
            transform: translateY(-2px);
        }

        .auth-contact-card .auth-dev-name {
            font-size: 15px;
            text-align: center;
        }

        .auth-contact-card .auth-email-text {
            font-size: 12.5px;
            color: #93c5fd;
            word-break: break-all;
            margin-top: 5px;
            text-align: center;
        }

        .auth-contact-card .auth-contact-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .auth-action-btn {
            display: inline-block;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: "Onest", sans-serif;
            border: 1px solid transparent;
            text-align: center;
        }

        .auth-action-btn.mail {
            background: #3d74ff;
            color: #FFFFFF;
            flex: 1;
        }
        .auth-action-btn.mail:hover {
            background: #2563eb;
            text-decoration: none;
            color: #FFFFFF;
        }

        .auth-action-btn.copy {
            background: rgba(255, 255, 255, 0.08);
            color: #E3E3E3;
            border: 1px solid rgba(255, 255, 255, 0.18);
            flex: 1;
        }
        .auth-action-btn.copy:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #FFFFFF;
        }
        .auth-action-btn.copied {
            background: rgba(81, 213, 90, 0.2) !important;
            border-color: #51D55A !important;
            color: #51D55A !important;
        }

        .auth-modal-footer {
            padding: 14px 28px;
            border-top: 1px solid #24396b;
            background: #0e1c40;
            font-size: 12px;
            color: #8da2d4;
            text-align: center;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .auth-modal-card {
                width: 95vw;
                height: auto;
                max-height: 92vh;
                min-height: auto;
            }
            .auth-modal-header {
                padding: 14px 18px;
            }
            .auth-modal-body {
                padding: 22px 16px;
                justify-content: flex-start;
            }
            .auth-desc-text {
                font-size: 15.5px;
                text-align: center;
                margin-bottom: 20px;
            }
            .auth-section-title {
                text-align: center;
            }
            .auth-dev-grid,
            .auth-contact-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                max-width: 100%;
            }
            .auth-dev-photo-placeholder,
            .auth-dev-photo-wrapper {
                width: 130px;
                height: 130px;
            }
        }
    </style>
</head>
<body class="auth-page">

    <div class="auth-wrapper">
        <div class="header">
            <a href="signin.php">
                <img src="img/main%20logo.png" alt="IntelliWare" class="fade-in signin-logo" />
            </a>
        </div>

        <div class="container">
            <div class="reset-box">

                <!-- Step Progress (3 Steps) -->
                <div class="step-progress">
                    <div class="step-item <?php echo $stepParam === 1 ? 'active' : ($stepParam > 1 ? 'completed' : ''); ?>" id="indicatorStep1">
                        <div class="step-circle">1</div>
                        <span>Email</span>
                    </div>
                    <div class="step-line <?php echo $stepParam >= 2 ? 'active' : ''; ?>" id="indicatorLine1"></div>
                    <div class="step-item <?php echo $stepParam === 2 ? 'active' : ($stepParam > 2 ? 'completed' : ''); ?>" id="indicatorStep2">
                        <div class="step-circle">2</div>
                        <span>Verify Code</span>
                    </div>
                    <div class="step-line <?php echo $stepParam >= 3 ? 'active' : ''; ?>" id="indicatorLine2"></div>
                    <div class="step-item <?php echo $stepParam === 3 ? 'active' : ''; ?>" id="indicatorStep3">
                        <div class="step-circle">3</div>
                        <span>New Password</span>
                    </div>
                </div>

                <hr>

                <!-- STEP 1: REQUEST CODE FORM -->
                <div id="sectionStep1" style="<?php echo $stepParam === 1 ? 'display:block;' : 'display:none;'; ?>">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#51D55A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                        Forgot Password
                    </h3>
                    <p class="subtext">
                        Enter your registered email address to receive a 6-digit verification code.
                    </p>

                    <form id="formStep1" method="POST" action="forgot_password.php">
                        <input type="hidden" name="action" value="request_code">
                        
                        <div class="tfieldname">Registered Email Address</div>
                        <div class="tf">
                            <input type="email" id="identifierInput" name="identifier" 
                                   value="<?php echo htmlspecialchars($accountParam); ?>" 
                                   placeholder="e.g. juan@intelliware.com" required autofocus>
                        </div>

                        <button type="submit" class="btn-submit" id="btnRequestCode">
                            <span class="btn-text">Send Verification Code</span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </form>

                    <div class="back-link">
                        <a href="signin.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                            Back to Login
                        </a>
                    </div>
                </div>

                <!-- STEP 2: ENTER VERIFICATION CODE & CONFIRM -->
                <div id="sectionStep2" style="<?php echo $stepParam === 2 ? 'display:block;' : 'display:none;'; ?>">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#51D55A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Verify Code
                    </h3>
                    <p class="subtext">
                        Enter the 6-digit verification code sent to your email.
                    </p>

                    <div id="emailNoticeBox" class="info-pill" style="<?php echo !empty($apiResponse['masked_email']) ? 'display:flex;' : 'display:none;'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" x2="12" y1="8" y2="12"></line>
                            <line x1="12" x2="12.01" y1="16" y2="16"></line>
                        </svg>
                        <div>
                            Code sent to <strong id="noticeEmail" style="color:#93c5fd;"><?php echo htmlspecialchars($apiResponse['masked_email'] ?? ''); ?></strong>
                        </div>
                    </div>

                    <!-- Development / Local Test Hint (if SMTP failed) -->
                    <div id="devTestBox" class="dev-badge" style="<?php echo (!empty($apiResponse['otp']) && empty($apiResponse['email_sent'])) ? 'display:block;' : 'display:none;'; ?>">
                        <strong>Development / Local Testing Mode:</strong>
                        <span>Could not dispatch email via SMTP (Gmail requires an App Password). Your reset code is:</span>
                        <div class="code-chip" id="devChipCode" onclick="fillDevCode('<?php echo htmlspecialchars($apiResponse['otp'] ?? ''); ?>')">
                            <?php echo htmlspecialchars($apiResponse['otp'] ?? ''); ?> (Click to fill)
                        </div>
                    </div>

                    <form id="formStep2" method="POST" action="forgot_password.php">
                        <input type="hidden" name="action" value="verify_code">
                        <input type="hidden" id="step2AccountInput" name="identifier" value="<?php echo htmlspecialchars($accountParam); ?>">

                        <div class="tfieldname">6-Digit Verification Code</div>
                        <div class="tf">
                            <input type="text" id="verificationCodeInput" name="verification_code" 
                                   class="otp-input" placeholder="••••••" maxlength="10" required autocomplete="one-time-code">
                        </div>

                        <button type="submit" class="btn-submit" id="btnVerifyCode">
                            <span class="btn-text">Confirm Code</span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </form>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:11.5px;">
                        <a href="javascript:void(0)" onclick="goToStep(1)" style="color:#94a3b8; text-decoration:none;">
                            ← Use another email
                        </a>
                        <a href="signin.php" style="color:#51D55A; text-decoration:none;">
                            Back to Login
                        </a>
                    </div>
                </div>

                <!-- STEP 3: SET NEW PASSWORD -->
                <div id="sectionStep3" style="<?php echo $stepParam === 3 ? 'display:block;' : 'display:none;'; ?>">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#51D55A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Set New Password
                    </h3>
                    <p class="subtext">
                        Create a secure new password for your account.
                    </p>

                    <form id="formStep3" method="POST" action="forgot_password.php">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="token" id="hiddenToken" value="<?php echo htmlspecialchars($tokenParam); ?>">
                        <input type="hidden" id="step3AccountInput" name="identifier" value="<?php echo htmlspecialchars($accountParam); ?>">
                        <input type="hidden" id="step3CodeInput" name="verification_code" value="">

                        <div class="tfieldname">New Password</div>
                        <div class="tf">
                            <div class="password-container">
                                <input type="password" id="newPasswordInput" name="new_password" placeholder="At least 4 characters" required minlength="4">
                                <span class="password-toggle" id="toggleNewPassword">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-open" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-closed" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                        <line x1="2" x2="22" y1="2" y2="22"></line>
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="tfieldname">Confirm New Password</div>
                        <div class="tf">
                            <div class="password-container">
                                <input type="password" id="confirmPasswordInput" name="confirm_password" placeholder="Repeat new password" required minlength="4">
                                <span class="password-toggle" id="toggleConfirmPassword">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-open" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-closed" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                        <line x1="2" x2="22" y1="2" y2="22"></line>
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit" id="btnResetPassword">
                            <span class="btn-text">Update Password</span>
                            <span class="spinner" style="display:none;"></span>
                        </button>
                    </form>

                    <div class="back-link">
                        <a href="signin.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                            Back to Login
                        </a>
                    </div>
                </div>

            </div>

            <!-- FOOTER (MATCH SIGNIN.PHP) -->
            <div class="footer">
                <hr><br>
                © 2025 IntelliWare. All rights reserved.
                <a href="javascript:void(0)" onclick="openAboutModal(event)">About IntelliWare</a>
                <a href="javascript:void(0)" onclick="openContactModal(event)">Contact our Support</a>
            </div>
        </div>
    </div>

    <!-- ABOUT INTELLIWARE MODAL -->
    <div id="aboutModal" class="auth-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aboutModalTitle">
        <div class="auth-modal-card">
            <div class="auth-modal-header">
                <h3 id="aboutModalTitle">About IntelliWare</h3>
                <button type="button" class="auth-modal-close" onclick="closeAboutModal()" aria-label="Close modal">&times;</button>
            </div>
            <div class="auth-modal-body">
                <p class="auth-desc-text">
                    IntelliWare is a Warehouse and Inventory Management System designed to optimize retail and warehouse stock operations through real-time stock monitoring, automated replenishment alerts, fast- and slow-moving item classification, and decision-support analytics.
                </p>

                <div class="auth-section-title">Developers</div>
                <div class="auth-dev-grid">
                    <!-- Milan Franco L. Santos -->
                    <div class="auth-dev-photo-card">
                        <div class="auth-dev-photo-wrapper">
                            <img src="img/dev_santos.png" alt="Milan Franco L. Santos" class="auth-dev-photo" />
                        </div>
                        <div class="auth-dev-name">Milan Franco L. Santos</div>
                        <div class="auth-dev-role">Developer • BS Information Technology</div>
                    </div>

                    <!-- Stephen Kyle B. Batara -->
                    <div class="auth-dev-photo-card">
                        <div class="auth-dev-photo-wrapper">
                            <img src="img/dev_batara.png" alt="Stephen Kyle B. Batara" class="auth-dev-photo" />
                        </div>
                        <div class="auth-dev-name">Stephen Kyle B. Batara</div>
                        <div class="auth-dev-role">Developer • BS Information Technology</div>
                    </div>

                    <!-- Alyssa Mae G. Suarez -->
                    <div class="auth-dev-photo-card">
                        <div class="auth-dev-photo-wrapper">
                            <img src="img/dev_suarez.png" alt="Alyssa Mae G. Suarez" class="auth-dev-photo" />
                        </div>
                        <div class="auth-dev-name">Alyssa Mae G. Suarez</div>
                        <div class="auth-dev-role">Developer • BS Information Technology</div>
                    </div>
                </div>
            </div>
            <div class="auth-modal-footer">
                <span>College of Computer Studies &bull; Pamantasan ng Lungsod ng Pasig</span>
            </div>
        </div>
    </div>

    <!-- CONTACT OUR SUPPORT MODAL -->
    <div id="contactModal" class="auth-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">
        <div class="auth-modal-card">
            <div class="auth-modal-header">
                <h3 id="contactModalTitle">Contact Support</h3>
                <button type="button" class="auth-modal-close" onclick="closeContactModal()" aria-label="Close modal">&times;</button>
            </div>
            <div class="auth-modal-body">
                <p class="auth-desc-text">
                    For inquiries, feedback, or support, please reach out to any of the developers via their official student email addresses:
                </p>

                <div class="auth-section-title">Developer Contact Details</div>
                <div class="auth-contact-grid">
                    <!-- Milan Franco L. Santos -->
                    <div class="auth-contact-card">
                        <div>
                            <div class="auth-dev-name">Milan Franco L. Santos</div>
                            <div class="auth-email-text">santos_milanfranco@plpasig.edu.ph</div>
                        </div>
                        <div class="auth-contact-actions">
                            <a href="mailto:santos_milanfranco@plpasig.edu.ph" class="auth-action-btn mail">Email</a>
                            <button type="button" class="auth-action-btn copy" onclick="copyEmail('santos_milanfranco@plpasig.edu.ph', this)">Copy</button>
                        </div>
                    </div>

                    <!-- Stephen Kyle B. Batara -->
                    <div class="auth-contact-card">
                        <div>
                            <div class="auth-dev-name">Stephen Kyle B. Batara</div>
                            <div class="auth-email-text">batara_stephenkyle@plpasig.edu.ph</div>
                        </div>
                        <div class="auth-contact-actions">
                            <a href="mailto:batara_stephenkyle@plpasig.edu.ph" class="auth-action-btn mail">Email</a>
                            <button type="button" class="auth-action-btn copy" onclick="copyEmail('batara_stephenkyle@plpasig.edu.ph', this)">Copy</button>
                        </div>
                    </div>

                    <!-- Alyssa Mae G. Suarez -->
                    <div class="auth-contact-card">
                        <div>
                            <div class="auth-dev-name">Alyssa Mae G. Suarez</div>
                            <div class="auth-email-text">suarez_alyssamae@plpasig.edu.ph</div>
                        </div>
                        <div class="auth-contact-actions">
                            <a href="mailto:suarez_alyssamae@plpasig.edu.ph" class="auth-action-btn mail">Email</a>
                            <button type="button" class="auth-action-btn copy" onclick="copyEmail('suarez_alyssamae@plpasig.edu.ph', this)">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="auth-modal-footer">
                <span>Official Domain: @plpasig.edu.ph &bull; Pamantasan ng Lungsod ng Pasig</span>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'error') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerText = message;
            toast.onclick = () => toast.remove();
            document.body.appendChild(toast);
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 3500);
        }

        function setupEyeToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            if (!toggle || !input) return;

            const eyeOpen = toggle.querySelector('.eye-open');
            const eyeClosed = toggle.querySelector('.eye-closed');

            toggle.addEventListener('click', function() {
                const isPass = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPass ? 'text' : 'password');
                eyeOpen.style.display = isPass ? 'none' : 'block';
                eyeClosed.style.display = isPass ? 'block' : 'none';
            });
        }

        setupEyeToggle('toggleNewPassword', 'newPasswordInput');
        setupEyeToggle('toggleConfirmPassword', 'confirmPasswordInput');

        function goToStep(step) {
            const s1 = document.getElementById('sectionStep1');
            const s2 = document.getElementById('sectionStep2');
            const s3 = document.getElementById('sectionStep3');
            const ind1 = document.getElementById('indicatorStep1');
            const ind2 = document.getElementById('indicatorStep2');
            const ind3 = document.getElementById('indicatorStep3');
            const line1 = document.getElementById('indicatorLine1');
            const line2 = document.getElementById('indicatorLine2');

            // Reset
            s1.style.display = 'none';
            s2.style.display = 'none';
            s3.style.display = 'none';
            ind1.className = 'step-item';
            ind2.className = 'step-item';
            ind3.className = 'step-item';
            line1.className = 'step-line';
            line2.className = 'step-line';

            if (step === 1) {
                s1.style.display = 'block';
                ind1.classList.add('active');
                document.getElementById('identifierInput').focus();
            } else if (step === 2) {
                s2.style.display = 'block';
                ind1.classList.add('completed');
                line1.classList.add('active');
                ind2.classList.add('active');
                document.getElementById('verificationCodeInput').focus();
            } else if (step === 3) {
                s3.style.display = 'block';
                ind1.classList.add('completed');
                line1.classList.add('active');
                ind2.classList.add('completed');
                line2.classList.add('active');
                ind3.classList.add('active');
                document.getElementById('newPasswordInput').focus();
            }
        }

        function fillDevCode(code) {
            if (!code) return;
            const input = document.getElementById('verificationCodeInput');
            input.value = code;
            showToast('Code ' + code + ' applied!', 'success');
        }

        // --- Handle Step 1 (Request Code) via AJAX ---
        const form1 = document.getElementById('formStep1');
        const btn1  = document.getElementById('btnRequestCode');
        form1.addEventListener('submit', function(e) {
            e.preventDefault();
            const identifier = document.getElementById('identifierInput').value.trim();
            if (!identifier) {
                showToast('Please enter your registered email address.');
                return;
            }

            btn1.disabled = true;
            btn1.querySelector('.btn-text').textContent = 'Generating Code...';
            btn1.querySelector('.spinner').style.display = 'inline-block';

            const formData = new FormData();
            formData.append('action', 'request_code');
            formData.append('identifier', identifier);

            fetch('forgot_password.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn1.disabled = false;
                btn1.querySelector('.btn-text').textContent = 'Send Verification Code';
                btn1.querySelector('.spinner').style.display = 'none';

                if (data.success) {
                    const email = data.email || identifier;
                    document.getElementById('step2AccountInput').value = email;
                    document.getElementById('step3AccountInput').value = email;
                    if (data.token) {
                        document.getElementById('hiddenToken').value = data.token;
                    }
                    if (data.masked_email) {
                        document.getElementById('noticeEmail').textContent = data.masked_email;
                        document.getElementById('emailNoticeBox').style.display = 'flex';
                    }

                    // Development / local hint if SMTP failed
                    const devBox = document.getElementById('devTestBox');
                    const devChip = document.getElementById('devChipCode');
                    if (data.otp && !data.email_sent) {
                        devChip.textContent = data.otp + ' (Click to fill)';
                        devChip.onclick = () => fillDevCode(data.otp);
                        devBox.style.display = 'block';
                    } else {
                        devBox.style.display = 'none';
                    }

                    showToast(data.message, data.email_sent ? 'success' : 'warning');
                    goToStep(2);
                } else {
                    showToast(data.message || 'Could not find that account.');
                }
            })
            .catch(err => {
                btn1.disabled = false;
                btn1.querySelector('.btn-text').textContent = 'Send Verification Code';
                btn1.querySelector('.spinner').style.display = 'none';
                showToast('Connection error. Please try again.');
            });
        });

        // --- Handle Step 2 (Confirm Code) via AJAX ---
        const form2 = document.getElementById('formStep2');
        const btn2  = document.getElementById('btnVerifyCode');
        form2.addEventListener('submit', function(e) {
            e.preventDefault();
            const identifier = document.getElementById('step2AccountInput').value.trim();
            const code = document.getElementById('verificationCodeInput').value.trim();

            if (!code) {
                showToast('Please enter the 6-digit verification code.');
                return;
            }

            btn2.disabled = true;
            btn2.querySelector('.btn-text').textContent = 'Verifying Code...';
            btn2.querySelector('.spinner').style.display = 'inline-block';

            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('identifier', identifier);
            formData.append('verification_code', code);

            fetch('forgot_password.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn2.disabled = false;
                btn2.querySelector('.btn-text').textContent = 'Confirm Code';
                btn2.querySelector('.spinner').style.display = 'none';

                if (data.success) {
                    if (data.token) {
                        document.getElementById('hiddenToken').value = data.token;
                    }
                    document.getElementById('step3CodeInput').value = code;
                    showToast(data.message || 'Code confirmed! Now set your new password.', 'success');
                    goToStep(3);
                } else {
                    showToast(data.message || 'Invalid or expired verification code.');
                }
            })
            .catch(err => {
                btn2.disabled = false;
                btn2.querySelector('.btn-text').textContent = 'Confirm Code';
                btn2.querySelector('.spinner').style.display = 'none';
                showToast('Connection error. Please try again.');
            });
        });

        // --- Handle Step 3 (Reset Password) via AJAX ---
        const form3 = document.getElementById('formStep3');
        const btn3  = document.getElementById('btnResetPassword');
        form3.addEventListener('submit', function(e) {
            e.preventDefault();
            const identifier = document.getElementById('step3AccountInput').value.trim();
            const code = document.getElementById('step3CodeInput').value.trim();
            const token = document.getElementById('hiddenToken').value.trim();
            const newPass = document.getElementById('newPasswordInput').value;
            const confPass = document.getElementById('confirmPasswordInput').value;

            if (newPass.length < 4) {
                showToast('Password must be at least 4 characters long.');
                return;
            }
            if (newPass !== confPass) {
                showToast('Passwords do not match. Please retype identical passwords.');
                return;
            }

            btn3.disabled = true;
            btn3.querySelector('.btn-text').textContent = 'Updating Password...';
            btn3.querySelector('.spinner').style.display = 'inline-block';

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('identifier', identifier);
            formData.append('verification_code', code || token);
            formData.append('token', token);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confPass);

            fetch('forgot_password.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn3.disabled = false;
                btn3.querySelector('.btn-text').textContent = 'Update Password';
                btn3.querySelector('.spinner').style.display = 'none';

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'signin.php?success=' + encodeURIComponent('Password updated successfully! Please log in with your new password.');
                    }, 1200);
                } else {
                    showToast(data.message || 'Failed to update password.');
                }
            })
            .catch(err => {
                btn3.disabled = false;
                btn3.querySelector('.btn-text').textContent = 'Update Password';
                btn3.querySelector('.spinner').style.display = 'none';
                showToast('An error occurred while updating your password.');
            });
        });

        // Check for URL errors or prefilled alerts
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error')) {
            showToast(urlParams.get('error'), 'error');
        }

        /* --- AUTH MODAL HANDLERS --- */
        function openAboutModal(e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('aboutModal');
            if (modal) modal.classList.add('active');
        }

        function closeAboutModal() {
            const modal = document.getElementById('aboutModal');
            if (modal) modal.classList.remove('active');
        }

        function openContactModal(e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('contactModal');
            if (modal) modal.classList.add('active');
        }

        function closeContactModal() {
            const modal = document.getElementById('contactModal');
            if (modal) modal.classList.remove('active');
        }

        function copyEmail(email, btn) {
            const copySuccess = () => {
                const origText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = origText;
                    btn.classList.remove('copied');
                }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(email).then(copySuccess).catch(() => {
                    fallbackCopy(email, copySuccess);
                });
            } else {
                fallbackCopy(email, copySuccess);
            }
        }

        function fallbackCopy(text, cb) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                if (cb) cb();
            } catch (err) {}
            document.body.removeChild(ta);
        }

        // Close modal on overlay background click
        window.addEventListener('click', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('auth-modal-overlay')) {
                e.target.classList.remove('active');
            }
        });

        // Close modal on Escape key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.auth-modal-overlay').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
