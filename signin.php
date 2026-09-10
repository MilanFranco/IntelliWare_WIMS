<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="img/systemlogo.svg?v=2">
    <title>IntelliWare - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="styles.css?v=1.0" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Overpass:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

        /* --- WRAPPER --- */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding-bottom: 80px;
        }

        /* --- HEADER --- */
        .header { 
            margin-top: 24px; 
            margin-bottom: 12px; 
            text-align: center;
        }
        
        .header .signin-logo { 
            width: clamp(180px, 70vw, 480px); 
            height: auto; 
            display: block; 
            margin-left: auto; 
            margin-right: auto; 
            animation: fadeIn 2s ease-in-out;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start; 
        }

        .signinbox {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 10px;
            
            /* EXACT WIDTH MATCH TO REGISTER PAGE */
            width: 320px; 
            max-width: 90vw;
            
            padding: 25px;
            box-shadow: 0 0 20px 2px #304374;
            margin-bottom: 15px;
            text-align: center;
            height: auto;
        }

        .signinbox h3 {
            font-family: "Overpass", sans-serif;
            font-size: 20px;
            color: #E3E3E3;
            margin-bottom: 5px;
            text-align: left;
        }

        .signinbox .subtext {
            font-family: "Onest", sans-serif;
            font-size: 12px;
            color: #67e78c;
            margin-bottom: 10px;
            text-align: left;
        }

        .signinbox hr {
            border: none;
            border-top: 0.2px solid #2f4b91; 
            width: 100%;
        }

        .link-box {
            background-color: #1D387B;
            border: 2px solid #3d74ff;
            border-radius: 10px;
            width: 320px; 
            max-width: 90vw;
            
            
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
            margin-bottom: 5px;
            margin-top: 15px;
            font-size: 12px;
            width: auto;
        }

        .tf {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 5px;
            flex-direction: column;
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
        }

        .tf input:focus {
            outline: none;
            box-shadow: 0 0 0 1px #51D55A;
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
        }

        .password-toggle:hover { opacity: 1; }
        .eye-closed { display: none; }

        /* Forgot Password */
        .fieldrow {
            display: flex;
            justify-content: space-between; 
            align-items: center; 
            width: 100%; 
            margin: 0 auto;
        }

        .forgotpass {
            text-align: right;
            font-size: 10px;
            font-family: "Onest", sans-serif;
            font-weight: 500;
            color: #E3E3E3;
            margin-bottom: 5px;
            margin-top: 15px;
        }

        .forgotpass a {
            font-weight: 300;
            color: #51D55A;
            text-decoration: none;
            font-size: 12px;
        }
        .forgotpass a:hover { text-decoration: underline; }

        /* Login Button */
        .btnlogin {
            border: 0.2px solid transparent;
            font-family: "Onest", sans-serif;
            font-size: 12px;
            font-weight: 500;
            color: white;
            border-radius: 5px;
            background-color: #51D55A;
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
        }
        .btnlogin:hover {
            background-color: #3d9743;
            border: 0.2px solid #51D55A;
            transform: scale(1.02);
        }
        .btnlogin:active {
            background-color: #3ea147;
            transform: scale(0.98);
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
            
            /* WIDTH MATCH (320px) */
            width: 320px; 
            max-width: 90vw;
        }

        .footer a { color: rgb(168, 168, 168); margin: 0 5px; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }

        /* --- UTILITIES --- */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 2s ease-in-out; }
        
        .toast {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            padding: 15px 25px; border-radius: 10px; font-family: 'Onest', sans-serif; 
            font-size: 14px; color: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000; animation: fadein 0.5s, fadeout 0.5s 3s;
        }
        .toast.error { background-color: #dc3545; }
        
        @keyframes fadein { from { opacity: 0; bottom: 20px; } to { opacity: 1; bottom: 30px; } }
        @keyframes fadeout { from { opacity: 1; bottom: 30px; } to { opacity: 0; bottom: 20px; } }

        /* ---- Mobile responsive ---- */
        @media (max-width: 480px) {
            .auth-wrapper {
                padding-bottom: 100px;
                justify-content: flex-start;
                padding-top: 20px;
            }

            .header {
                margin-top: 16px;
                margin-bottom: 8px;
            }

            .signinbox {
                padding: 18px 16px;
            }

            .footer {
                bottom: 20px;
                font-size: 11px;
            }

            .footer hr {
                width: 90vw;
            }

            .toast {
                left: 12px;
                right: 12px;
                transform: none;
                font-size: 13px;
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <div class="header">
            <img src="img/main%20logo.png" alt="IntelliWare" class="fade-in signin-logo" />
            <br>   
        </div>

        <div class="container">
            <form action="src/scripts/authenticate.php" method="POST" >
                <div class="signinbox">
                    <h3>Log In to IntelliWare</h3>
                    <p class="subtext">Enter your username or email and password to log in.</p> 

                    <hr>

                    <div class="tfieldname">Username or email address</div>
                    <div class="tf">
                        <input type="text" id="usernamelogin" name="username">
                    </div>

                    <div class="fieldrow">
                        <div class="tfieldname">Password</div>
                        <div class="forgotpass"><a href="#">Forgot password?</a></div>
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
                    <br>
                    <button class="btnlogin" type="submit" style="margin-bottom: 20px;">Login</button>
                    <br>
                </div>
            </form>
            
            <div class="footer">
                <hr><br>
                © 2025 IntelliWare. All rights reserved.
                <a href="#">About IntelliWare</a>
                <a href="#">Contact our Support</a>
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
            const toast = document.createElement('div');
            toast.className = 'toast error';
            toast.innerText = 'Invalid username or password.';
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }

        usernameField.addEventListener('input', () => {
            if (usernameField.value.trim() !== '') usernameField.classList.remove('error-border');
        });

        passwordField.addEventListener('input', () => {
            if (passwordField.value.trim() !== '') passwordField.classList.remove('error-border');
        });
    </script>
</body>
</html>