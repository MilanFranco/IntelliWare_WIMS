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
            min-height: 100dvh;
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

        /* --- WRAPPER --- */
        .auth-wrapper {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 24px 16px 0;
            box-sizing: border-box;
        }

        .auth-center {
            margin-top: auto;
            margin-bottom: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        /* --- HEADER --- */
        .header { 
            margin-top: 0; 
            margin-bottom: 24px; /* Tagline to card: 24px */
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .header .signin-logo { 
            width: clamp(200px, 68vw, 300px); 
            height: auto; 
            display: block; 
            margin: 0 auto 8px auto; /* Logo to tagline: 8px */
            animation: fadeIn 1.5s ease-in-out;
        }

        .auth-tagline {
            font-family: "Onest", sans-serif;
            font-size: 13px;
            font-weight: 400;
            color: #c4cdd5;
            letter-spacing: 0.2px;
            margin: 0;
            text-align: center;
        }

        .auth-wrapper .container,
        .container {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .signinbox {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 10px;
            width: 340px; 
            max-width: 92vw;
            padding: 24px; /* Card padding: 24px all sides */
            box-shadow: 0 0 20px 2px #304374;
            margin-bottom: 0;
            text-align: left;
            height: auto;
            box-sizing: border-box;
        }

        .signinbox h3 {
            font-family: "Overpass", sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #E3E3E3;
            margin-bottom: 8px; /* Title to subtitle: 8px */
            text-align: left;
        }

        .signinbox .subtext {
            font-family: "Onest", sans-serif;
            font-size: 13px;
            color: #67e78c;
            margin-bottom: 16px; /* Subtitle to divider: 16px */
            text-align: left;
            text-wrap: balance;
            line-height: 1.4;
        }

        .signinbox hr {
            border: none;
            border-top: 1px solid #2f4b91; 
            width: 100%;
            margin: 0 0 16px 0; /* Divider to first label: 16px */
        }

        .link-box {
            background-color: #1D387B;
            border: 2px solid #3d74ff;
            border-radius: 10px;
            width: 340px; 
            max-width: 92vw;
            padding: 8px 12px; 
            box-shadow: 0 0 20px 2px #304374;
            text-align: center;
            font-size: 12px;
            color: #E3E3E3;
        }

        .tfieldname {
            text-align: left;
            font-family: "Onest", sans-serif;
            font-weight: 500;
            color: #E3E3E3;
            margin: 0 0 8px 0; /* Label to input: 8px */
            font-size: 13px;
            width: auto;
        }

        .tf {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 0 20px 0; /* Input to next label: 20px */
            flex-direction: column;
            width: 100%;
        }

        .tf.tf-password {
            margin: 0;
        }

        .tf input {
            box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2);
            color: white;
            background-color: #13275B;
            width: 100%;
            height: 48px; /* 48px input height */
            padding: 0 14px; /* 0 14px padding */
            border-radius: 8px;
            border: 1px solid #304374;
            font-family: "Onest", sans-serif;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .tf input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(81, 213, 90, 0.4);
            border-color: #51D55A;
        }
        
        .tf input.error-border {
            border: 1px solid #dc3545 !important;
            box-shadow: 0 0 1px #dc3545 !important;
        }

        /* Password Eye */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 44px; /* Prevent text clashing with toggle */
        }

        .password-toggle {
            position: absolute;
            right: 14px; /* 14px from right edge */
            top: 50%;
            transform: translateY(-50%); /* vertically centered */
            cursor: pointer;
            color: #E3E3E3;
            font-size: 16px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            user-select: none;
        }

        .password-toggle:hover { opacity: 1; }
        .eye-closed { display: none; }

        /* Forgot Password */
        .fieldrow {
            display: flex;
            justify-content: space-between; 
            align-items: baseline; 
            width: 100%; 
            margin: 0 0 8px 0; /* Password label row margin-bottom: 8px */
        }

        .fieldrow .tfieldname {
            margin: 0;
        }

        .forgotpass {
            text-align: right;
            font-size: 12px;
            font-family: "Onest", sans-serif;
            font-weight: 500;
            color: #E3E3E3;
            margin: 0;
        }

        .forgotpass a {
            font-weight: 400;
            color: #51D55A;
            text-decoration: none;
            font-size: 12px;
        }
        .forgotpass a:hover { text-decoration: underline; }

        /* Login Button */
        .btnlogin {
            border: 1px solid transparent;
            font-family: "Onest", sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: white;
            border-radius: 8px;
            background-color: #51D55A;
            width: 100%;
            height: 52px; /* 52px tall */
            padding: 0;
            margin-top: 24px; /* Last input to login button: 24px */
            margin-bottom: 0;
            box-shadow: 0 4px 12px rgba(81, 213, 90, 0.25);
            transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btnlogin:hover {
            background-color: #3d9743;
            border: 1px solid #51D55A;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(81, 213, 90, 0.35);
        }
        .btnlogin:active {
            background-color: #3ea147;
            transform: translateY(0);
        }

        .new-user-line {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            font-family: "Onest", sans-serif;
            font-size: 12px;
            color: #E3E3E3;
        }
        .new-user-line .reglink a { color: #51D55A; text-decoration: none; font-weight: 500; }
        .new-user-line .reglink a:hover { text-decoration: underline; }

        /* --- FOOTER --- */
        .auth-wrapper .footer,
        .footer {
            position: static !important;
            bottom: auto !important;
            margin-top: auto;
            text-align: center;
            font-family: "Overpass", sans-serif;
            font-weight: 300;
            font-size: 12px;
            color: rgb(168, 168, 168);
            padding: 16px 0 calc(16px + env(safe-area-inset-bottom));
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .footer-copyright {
            color: rgb(168, 168, 168);
            font-size: 12px;
            line-height: 1.4;
        }

        .footer-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: rgb(168, 168, 168);
            margin: 0;
            text-decoration: none;
            white-space: nowrap;
            font-size: 12px;
            transition: color 0.2s ease;
        }
        .footer-links a:hover {
            color: #51D55A;
            text-decoration: underline;
        }

        /* --- UTILITIES --- */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 1.5s ease-in-out; }
        
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

        /* ---- Mobile responsive ---- */
        @media (max-width: 480px) {
            .toast {
                left: 12px;
                right: 12px;
                transform: none;
                font-size: 13px;
                padding: 12px 16px;
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
        <div class="auth-center">
            <div class="header">
                <svg class="fade-in signin-logo" viewBox="86 0 274 37" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="IntelliWare">
                    <path d="M86.7223 36V0.999998H93.9223V36H86.7223ZM98.1148 36V0.999998H104.715L117.94 20.175C118.107 20.4417 118.348 20.8417 118.665 21.375C118.982 21.9083 119.223 22.375 119.39 22.775C119.34 22.425 119.298 21.975 119.265 21.425C119.248 20.8583 119.24 20.4333 119.24 20.15V0.999998H126.39V36H120.065L106.565 16.675C106.382 16.4083 106.132 16 105.815 15.45C105.515 14.9 105.273 14.425 105.09 14.025C105.157 14.4583 105.19 14.925 105.19 15.425C105.207 15.9083 105.215 16.325 105.215 16.675V36H98.1148ZM138.301 36V7.9H128.301V0.999998H155.526V7.9H145.501V36H138.301ZM157.423 36V0.999998H182.023V7.825H164.623V14.525H174.548V21.35H164.623V29.125H183.048V36H157.423ZM185.418 36V0.999998H192.618V29.05H209.443V36H185.418ZM210.336 36V0.999998H217.536V29.05H234.361V36H210.336ZM235.254 36V0.999998H242.454V36H235.254Z" fill="#E3E3E3"/>
                    <path d="M251.494 36L251.244 0.999998H255.494V24.7C255.494 25.0167 255.494 25.4083 255.494 25.875C255.494 26.3417 255.494 26.7667 255.494 27.15C255.644 26.7667 255.802 26.375 255.969 25.975C256.152 25.5583 256.327 25.15 256.494 24.75L266.944 0.999998H270.394L272.544 25C272.577 25.2667 272.61 25.6417 272.644 26.125C272.677 26.5917 272.694 26.9667 272.694 27.25C272.794 26.95 272.91 26.5917 273.044 26.175C273.194 25.7583 273.327 25.4 273.444 25.1L282.144 0.999998H286.744L273.744 36H269.844L267.469 12.05C267.452 11.8167 267.427 11.5917 267.394 11.375C267.377 11.1417 267.36 10.9 267.344 10.65C267.327 10.3833 267.31 10.1 267.294 9.8C267.227 10.0167 267.119 10.3167 266.969 10.7C266.819 11.0667 266.66 11.4167 266.494 11.75L255.344 36H251.494ZM279.534 36L297.634 0.999998H301.384L307.284 36H302.984L301.584 27.4H288.484L284.084 36H279.534ZM290.434 23.55H300.984L298.884 10.6C298.834 10.3333 298.776 10 298.709 9.6C298.643 9.2 298.584 8.80833 298.534 8.425C298.484 8.04167 298.451 7.75 298.434 7.55C298.351 7.75 298.226 8.04167 298.059 8.425C297.893 8.79166 297.718 9.175 297.534 9.575C297.351 9.95833 297.184 10.2833 297.034 10.55L290.434 23.55ZM309.73 36L315.88 0.999998H327.98C329.913 0.999998 331.521 1.275 332.805 1.825C334.105 2.375 335.138 3.10833 335.905 4.025C336.688 4.925 337.246 5.91667 337.58 7C337.913 8.06666 338.08 9.11667 338.08 10.15C338.08 11.3 337.896 12.45 337.53 13.6C337.18 14.7333 336.613 15.7917 335.83 16.775C335.063 17.7583 334.071 18.6 332.855 19.3C331.638 19.9833 330.163 20.45 328.43 20.7L333.48 36H328.93L324.03 20.9H316.73L314.08 36H309.73ZM317.43 16.95H326.13C328.146 16.95 329.713 16.6 330.83 15.9C331.946 15.2 332.721 14.3417 333.155 13.325C333.605 12.2917 333.83 11.2833 333.83 10.3C333.83 9.43333 333.638 8.59167 333.255 7.775C332.871 6.95833 332.23 6.28333 331.33 5.75C330.446 5.21666 329.238 4.95 327.705 4.95H319.53L317.43 16.95ZM338.017 36L344.167 0.999998H364.317L363.667 4.95H347.817L345.917 15.9H356.217L355.467 19.85H345.217L343.067 32.05H359.967L359.267 36H338.017Z" fill="#51D55A"/>
                </svg>
                <p class="auth-tagline">Intelligent Inventory Management System</p>
            </div>

            <div class="container">
                <form action="src/scripts/authenticate.php" method="POST" id="loginForm">
                    <div class="signinbox">
                        <h3>Log In to IntelliWare</h3>
                        <p class="subtext">Enter your credentials to continue.</p> 

                        <hr>

                        <div class="tfieldname">Username or email address</div>
                        <div class="tf">
                            <input type="text" id="usernamelogin" name="username" autocomplete="username" required>
                        </div>

                        <div class="fieldrow">
                            <div class="tfieldname">Password</div>
                            <div class="forgotpass"><a href="forgot_password.php" id="forgotPasswordLink">Forgot password?</a></div>
                        </div>

                        <div class="tf tf-password">
                            <div class="password-container">
                                <input type="password" name="password" id="passwordlogin" autocomplete="current-password" required>
                                <span class="password-toggle" id="togglePassword" role="button" aria-label="Toggle password visibility">
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

                        <button class="btnlogin" type="submit">Login</button>
                    </div>
                </form>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-copyright">© 2025 IntelliWare. All rights reserved.</div>
            <div class="footer-links">
                <a href="javascript:void(0)" onclick="openAboutModal(event)">About IntelliWare</a>
                <a href="javascript:void(0)" onclick="openContactModal(event)">Contact our Support</a>
            </div>
        </footer>
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