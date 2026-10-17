<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['Username'])) {
    $role = $_SESSION['Role'] ?? 'staff';
    $redirect = ($role === 'admin') ? 'dash_admin.php' : (($role === 'manager') ? 'dash_manager.php' : 'dash_staff.php');
    header("Location: $redirect");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="img/systemlogo.svg?v=2">
    <title>IntelliWare - Login</title>
    <script src="assets/vendor/tailwindcss.js"></script>
    <link href="assets/css/styles.css?v=1.0" rel="stylesheet">
    <link href="assets/fonts.css" rel="stylesheet">
    <style>

        * { box-sizing: border-box; margin: 0; padding: 0; }

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

        :root {
            --gap: 40px;
        }

        /* --- WRAPPER (VERTICAL CENTERING) --- */
        .auth-wrapper {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px 16px;
            box-sizing: border-box;
            width: 100%;
        }

        /* --- CONTAINER (SHARED 420px COLUMN) --- */
        .auth-wrapper .container,
        .container,
        .auth-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            padding: 0;
            flex: 0 0 auto;
        }

        /* --- LOGO BLOCK --- */
        .brand-block,
        .header { 
            width: 100%;
            margin: 0 0 var(--gap) 0;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .signin-logo {
            width: 100%;
            max-width: 440px;
            height: auto;
            display: block;
            margin: 0 auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 14px rgba(0, 0, 0, 0.35));
            user-select: none;
            -webkit-user-drag: none;
        }

        /* --- CARD --- */
        .signinbox {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            padding: 28px 24px;
            box-shadow: 0 0 25px 2px #304374;
            margin: 0;
            text-align: left;
            height: auto;
            box-sizing: border-box;
        }

        /* Card Header */
        .signinbox h3,
        .signin-title {
            font-family: "Overpass", sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: #FFFFFF;
            margin: 0 0 6px 0;
            text-align: left;
            line-height: 1.25;
        }

        .signinbox .subtext,
        .signin-subtext {
            font-family: "Onest", sans-serif;
            font-size: 13px;
            line-height: 1.4;
            color: #51D55A;
            margin: 0;
            text-align: left;
            max-width: none;
        }

        .signinbox hr,
        .signin-divider {
            border: none;
            border-top: 1px solid #2f4b91; 
            width: 100%;
            margin: 20px 0;
        }

        /* --- FORM SPACING & INPUTS --- */
        .field-group {
            width: 100%;
            margin: 0 0 20px 0;
        }

        .tfieldname {
            display: block;
            text-align: left;
            font-family: "Onest", sans-serif;
            font-weight: 500;
            color: #E3E3E3;
            margin: 0 0 8px 0;
            font-size: 13px;
            line-height: 1.3;
            width: auto;
        }

        .tf {
            display: flex;
            width: 100%;
            margin: 0;
            flex-direction: column;
        }

        .tf input {
            box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2);
            color: white;
            background-color: #13275B;
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border-radius: 6px;
            border: 1px solid #304374;
            font-family: "Onest", sans-serif;
            box-sizing: border-box;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .tf input:focus {
            outline: none;
            box-shadow: 0 0 0 1px #51D55A;
            border-color: #51D55A;
        }
        
        .tf input.error-border {
            border: 1px solid #dc3545 !important;
            box-shadow: 0 0 1px #dc3545 !important;
        }

        /* Password Field & Row */
        .fieldrow {
            display: flex;
            justify-content: space-between; 
            align-items: center; 
            width: 100%; 
            margin: 0 0 8px 0;
        }

        .fieldrow .tfieldname {
            margin: 0;
        }

        .forgotpass {
            margin: 0;
            text-align: right;
            font-size: 12px;
            font-family: "Onest", sans-serif;
            font-weight: 400;
        }

        .forgotpass a {
            font-weight: 400;
            color: #51D55A;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }
        .forgotpass a:hover {
            color: #67e78c;
            text-decoration: underline;
        }

        .password-container {
            position: relative;
            width: 100%;
            height: 44px;
        }

        .password-container input {
            height: 44px;
            padding-right: 42px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #E3E3E3;
            font-size: 16px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover { opacity: 1; }
        .eye-closed { display: none; }

        /* Login Button */
        .btnlogin {
            border: 0.2px solid transparent;
            font-family: "Onest", sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: white;
            border-radius: 6px;
            background-color: #51D55A;
            width: 100%;
            height: 48px;
            padding: 0;
            margin-top: 24px;
            margin-bottom: 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        .btnlogin:hover {
            background-color: #3d9743;
            border: 0.2px solid #51D55A;
            transform: scale(1.01);
        }
        .btnlogin:active {
            background-color: #3ea147;
            transform: scale(0.99);
        }

        /* --- FOOTER --- */
        .footer {
            width: 100%;
            max-width: 420px;
            margin: var(--gap) auto 0;
            text-align: center;
            font-family: "Onest", sans-serif;
            font-weight: 300;
            font-size: 12px;
            color: rgb(168, 168, 168);
            padding: 0;
            position: static;
            box-sizing: border-box;
        }

        .footer hr,
        .footer-divider {
            border: none;
            border-top: 1px solid #314f9b; 
            margin: 0 0 14px 0;
            width: 100%;
            box-sizing: border-box;
        }

        .footer .footer-copy {
            margin-bottom: 6px;
            font-size: 12px;
            color: rgb(168, 168, 168);
        }

        .footer .footer-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .footer .footer-dot {
            opacity: 0.4;
            font-size: 10px;
        }

        .footer a {
            color: rgb(168, 168, 168);
            margin: 0;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer a:hover {
            color: #FFFFFF;
            text-decoration: underline;
        }

        /* --- UTILITIES --- */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 2s ease-in-out; }
        
        .toast {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            padding: 15px 25px; border-radius: 10px; font-family: 'Onest', sans-serif; 
            font-size: 14px; color: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 100005; animation: fadein 0.5s, fadeout 0.5s 3s;
            pointer-events: auto;
        }
        .toast.error { background-color: #dc3545; }
        .toast.success { background-color: #51D55A; }
        
        @keyframes fadein { from { opacity: 0; bottom: 20px; } to { opacity: 1; bottom: 30px; } }
        @keyframes fadeout { from { opacity: 1; bottom: 30px; } to { opacity: 0; bottom: 20px; } }

        /* ---- Mobile responsive adjustments ---- */
        @media (max-width: 480px) {
            .signin-logo {
                max-width: 350px;
            }

            .signinbox {
                padding: 24px 20px;
            }

            .toast {
                left: 12px;
                right: 12px;
                transform: none;
                font-size: 13px;
                padding: 12px 16px;
            }
        }

        @media (max-height: 560px) {
            :root {
                --gap: 24px;
            }
            .auth-wrapper {
                justify-content: flex-start;
                padding-top: 16px;
                padding-bottom: 24px;
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
<body>

    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="brand-block">
                <img src="img/main%20logo.png" alt="IntelliWare" class="fade-in signin-logo" />
            </div>
            <form action="src/scripts/authenticate.php" method="POST" style="width: 100%; margin: 0;">
                <div class="signinbox">
                    <h3 class="signin-title">Log In to IntelliWare</h3>
                    <p class="signin-subtext">Enter your username or email and password to log in.</p> 

                    <hr class="signin-divider">

                    <!-- Username Field -->
                    <div class="field-group">
                        <label class="tfieldname" for="usernamelogin">Username or email address</label>
                        <div class="tf">
                            <input type="text" id="usernamelogin" name="username">
                        </div>
                    </div>

                    <!-- Password Field & Row -->
                    <div class="field-group">
                        <div class="fieldrow">
                            <label class="tfieldname" for="passwordlogin">Password</label>
                            <div class="forgotpass"><a href="forgot_password.php" id="forgotPasswordLink">Forgot password?</a></div>
                        </div>

                        <div class="tf">
                            <div class="password-container">
                                <input type="password" name="password" id="passwordlogin">
                                <span class="password-toggle" id="togglePassword">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                        <line x1="2" x2="22" y1="2" y2="22"></line>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <button class="btnlogin" type="submit">Login</button>
                </div>
            </form>
            
            <div class="footer">
                <hr class="footer-divider">
                <div class="footer-copy">© 2025 IntelliWare. All rights reserved.</div>
                <div class="footer-links">
                    <a href="javascript:void(0)" onclick="openAboutModal(event)">About IntelliWare</a>
                    <span class="footer-dot">•</span>
                    <a href="javascript:void(0)" onclick="openContactModal(event)">Contact our Support</a>
                </div>
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
        const form = document.querySelector('form');
        const usernameField = document.getElementById('usernamelogin');
        const passwordField = document.getElementById('passwordlogin');
        const togglePassword = document.getElementById('togglePassword');
        const eyeOpen = document.querySelector('.eye-open');
        const eyeClosed = document.querySelector('.eye-closed');

        togglePassword.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            eyeOpen.style.display = type === 'password' ? 'block' : 'none';
            eyeClosed.style.display = type === 'password' ? 'none' : 'block';
        });

        form.addEventListener('submit', function(e) {
            const username = usernameField.value.trim();
            const password = passwordField.value.trim();
            
            if (username === '' || password === '') {
                e.preventDefault();
                if (username === '') usernameField.classList.add('error-border');
                if (password === '') passwordField.classList.add('error-border');

                document.querySelectorAll('.toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className = 'toast error';
                toast.innerText = 'Please fill in all fields.';
                document.body.appendChild(toast);

                setTimeout(() => { toast.remove(); }, 3000);
                return;
            }
        });

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'invalid') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = 'toast error';
            toast.innerText = 'Invalid username or password.';
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        } else if (urlParams.get('error') === 'empty') {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = 'toast error';
            toast.innerText = 'Please fill in all fields.';
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        } else if (urlParams.get('success')) {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = 'toast success';
            toast.innerText = urlParams.get('success');
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }

        usernameField.addEventListener('input', () => {
            if (usernameField.value.trim() !== '') usernameField.classList.remove('error-border');
        });

        passwordField.addEventListener('input', () => {
            if (passwordField.value.trim() !== '') passwordField.classList.remove('error-border');
        });

        const forgotLink = document.getElementById('forgotPasswordLink');
        if (forgotLink && usernameField) {
            forgotLink.addEventListener('click', function(e) {
                const val = usernameField.value.trim();
                if (val && val.includes('@')) {
                    e.preventDefault();
                    window.location.href = 'forgot_password.php?account=' + encodeURIComponent(val);
                }
            });
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