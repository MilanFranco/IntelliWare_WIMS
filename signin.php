<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntelliWare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="styles.css?v=1.0" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
    
        .password-container {
            position: relative;
            width: 100%;
        }
        /* Center the whole sign-in area on this page only */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .auth-wrapper .header { margin-top: 24px; margin-bottom: 12px; }
        .signin-logo { width: clamp(350px, 35vw, 450px); height: auto; display: block; margin-left: auto; margin-right: auto; }
        .auth-container { max-width: 560px; width: 92vw; margin-left: auto; margin-right: auto; }
        .auth-container .signinbox { width: 100%; }

        /* Make role select look identical to inputs */
        .role-select {
            box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2);
            color: #E3E3E3;
            background-color: #13275B;
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #304374;
            font-family: 'Onest', sans-serif;
            box-sizing: border-box;
            appearance: none;
        }
        .role-select:focus {
            outline: none;
            box-shadow: 0 0 0 1px #51D55A;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #E3E3E3;
            font-size: 18px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .password-toggle:hover {
            opacity: 1;
        }
        
        .eye-closed {
            display: none;
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
    <div class="header">
        <img src="img/logo.svg" alt="IntelliWare" class="fade-in signin-logo" />
        
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


                    <div class="g-recaptcha" data-sitekey="6LdTgDQrAAAAAHokkaDM8gCwd4ZjgYAJh-H4o4Zg" data-theme="dark" style="margin: 14px 0; border-radius: 7px; width: 100%; transform: scale(0.9); transform-origin: left top;"></div>
                 
                  

                    <button class="btnlogin" type="submit" style="margin-bottom: 20px;">Login</button>
                    <br>
                </div>
            </form>
            
            <br>
            <div class="signinbox">
                <div class="new-user-line">
                    <span class="newt">New to IntelliWare?</span>
                    <span class="reglink"><a href="register.php">Register here.</a></span>
                </div>
            </div>
            
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
    const captchaResponse = grecaptcha.getResponse();

    if (username === '' || password === '' || captchaResponse === '') {
        e.preventDefault();

        if (username === '') usernameField.classList.add('error-border');
        if (password === '') passwordField.classList.add('error-border');
        if (captchaResponse === '') {
            const toast = document.createElement('div');
            toast.className = 'toast error';
            toast.innerText = 'Please complete the CAPTCHA.';
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove(); 
            }, 3000);
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'toast error';
        toast.innerText = 'Please fill in all fields.';
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove(); 
        }, 3000);

        return;
    }
});

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('error') === 'invalid') {
    const toast = document.createElement('div');
    toast.className = 'toast error';
    toast.innerText = 'Invalid username or password.';
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

usernameField.addEventListener('input', () => {
    if (usernameField.value.trim() !== '') {
        usernameField.classList.remove('error-border');
    }
});

passwordField.addEventListener('input', () => {
    if (passwordField.value.trim() !== '') {
        passwordField.classList.remove('error-border');
    }
});
</script>
</body>
</html>