<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IntelliWare - Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600&family=Overpass:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- 1. GLOBAL SETTINGS & BACKGROUND (Exact match to Signin) --- */
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

        /* The "Stars" / Glow Effect */
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

        /* Wrapper */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding-bottom: 80px; /* Exact match to Signin */
        }

        /* --- 2. HEADER (Exact Position Match) --- */
        .header { 
            margin-top: 24px; 
            margin-bottom: 12px; 
            text-align: center;
        }

        .header .signin-logo { 
            width: clamp(300px, 40vw, 550px); 
            height: auto; 
            display: block; 
            margin-left: auto; 
            margin-right: auto; 
            animation: fadeIn 1.5s ease;
        }

        /* --- 3. MAIN CARD (Slim Design) --- */
        .signinbox {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 10px;
            padding: 25px;
            
            /* SLIM WIDTH (Matches Login roughly, slightly wider for names) */
            width: 320px; 
            max-width: 90vw;
            
            box-shadow: 0 0 20px 2px #304374;
            margin-bottom: 15px;
            text-align: center;
            position: relative;
        }

        .signinbox h3 {
            font-family: "Overpass", sans-serif;
            font-size: 20px;
            color: #E3E3E3;
            margin-bottom: 5px;
            text-align: left;
        }

        .subtext {
            font-family: "Onest", sans-serif;
            font-size: 11px;
            color: #67e78c;
            margin-bottom: 10px;
            text-align: left;
            line-height: 1.3;
        }

        /* Separator inside box */
        .signinbox hr {
            border: none;
            border-top: 0.2px solid #2f4b91; 
            width: 100%;
            margin-bottom: 15px;
        }

        /* --- 4. BOTTOM LINK BOX --- */
        .link-box {
            background-color: #1D387B;
            border: 1px solid #3d74ff;
            border-radius: 10px;
            padding: 12px;
            
            /* Match main box width */
            width: 320px; 
            max-width: 90vw;
            
            box-shadow: 0 0 20px 2px #304374;
            text-align: center;
            font-size: 12px;
            color: #E3E3E3;
        }

        .link-box a { color: #51D55A; text-decoration: none; font-weight: 500; }
        .link-box a:hover { text-decoration: underline; }

        /* --- 5. INPUTS & BUTTONS --- */
        .tfieldname {
            text-align: left;
            font-size: 11px;
            font-weight: 500;
            color: #E3E3E3;
            margin-bottom: 4px;
            margin-top: 10px;
            display: block;
        }

        input, select {
            width: 100%;
            padding: 10px;
            background-color: #13275B !important;
            border: 1px solid #304374;
            border-radius: 5px;
            color: white !important;
            font-family: "Onest", sans-serif;
            font-size: 13px;
            box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2);
            outline: none;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            border-color: #51D55A;
            box-shadow: 0 0 0 1px #51D55A;
        }

        .row-split { display: flex; gap: 10px; }
        .row-split .input-wrapper { flex: 1; }

        .btnlogin {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background-color: #51D55A;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, background-color 0.2s;
        }
        .btnlogin:hover { background-color: #3d9743; transform: scale(1.02); }

        .btn-back {
            background: transparent;
            border: 1px solid #304374;
            color: #E3E3E3;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            flex: 1;
            transition: background-color 0.2s;
        }
        .btn-back:hover { background: #13275B; }

        .action-buttons { display: flex; gap: 8px; margin-top: 15px; }

        /* --- 6. FOOTER (Exact Match) --- */
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
            left: 0;
        }

        .footer hr {
            border: none;
            border-top: 0.2px solid #314f9b; 
            margin: 10px auto;
            
            /* WIDTH MATCH (320px) */
            width: 320px; 
            max-width: 90vw;
        }

        .footer a {
            color: rgb(168, 168, 168);
            margin: 0 5px; 
            text-decoration: none;
        }
        .footer a:hover { text-decoration: underline; }

        /* --- UTILITIES --- */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 1.5s ease; }
        
        .toast {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            padding: 12px 24px; border-radius: 8px; font-size: 14px; color: white;
            z-index: 1000; animation: fadeIn 0.5s;
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        
        <div class="header">
            <img src="img/logo.svg" alt="IntelliWare" class="fade-in signin-logo" />
            <br>   
        </div>

        <div class="signinbox fade-in">
            <h3>Create Account</h3>
            <p class="subtext">Join IntelliWare to manage your inventory.</p> 
            <hr>

            <form id="registerForm" method="POST" action="src/scripts/register_user.php">
                
                <div class="form-step" data-step="1">
                    <label class="tfieldname">Username</label>
                    <input type="text" name="username" placeholder="Choose a username" required>

                    <div class="row-split">
                        <div class="input-wrapper">
                            <label class="tfieldname">First Name</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="input-wrapper">
                            <label class="tfieldname">Last Name</label>
                            <input type="text" name="last_name" required>
                        </div>
                    </div>

                    <label class="tfieldname">Email Address</label>
                    <input type="email" name="email" placeholder="name@company.com" required>

                    <button type="button" class="btnlogin" id="step1Next">Next &nbsp; ➜</button>
                </div>

                <div class="form-step" data-step="2" style="display:none;">
                    <label class="tfieldname">Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="staff">Staff</option>
                    </select>

                    <label class="tfieldname">Employee ID</label>
                    <input type="text" name="employee_id" placeholder="e.g. STF-001" required>

                    <label class="tfieldname">Password</label>
                    <input type="password" name="password" required>

                    <label class="tfieldname">Confirm Password</label>
                    <input type="password" name="confirm_password" required>

                    <div class="action-buttons">
                        <button type="button" class="btn-back" id="step2Prev">Back</button>
                        <button type="submit" class="btnlogin" style="margin-top:0; flex: 2;">Register</button>
                    </div>
                </div>

            </form>
        </div>

        <div class="link-box fade-in">
            Already have an account? <a href="signin.php">Log In here.</a>
        </div>

        <div class="footer">
            <hr><br>
            © 2025 IntelliWare. All rights reserved.
            
            <a href="#">About IntelliWare</a>
            <a href="#">Contact our Support</a>
        </div>

    </div>

    <script>
        const step1Next = document.getElementById('step1Next');
        const step2Prev = document.getElementById('step2Prev');
        const steps = document.querySelectorAll('.form-step');

        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'toast';
            t.style.backgroundColor = '#dc3545';
            t.innerText = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        step1Next.addEventListener('click', function(){
            const inputs = steps[0].querySelectorAll('input[required]');
            let valid = true;
            inputs.forEach(i => {
                if(!i.value.trim()) { 
                    i.style.borderColor = '#dc3545'; 
                    valid = false; 
                } else { 
                    i.style.borderColor = '#304374'; 
                }
            });
            
            if(valid) {
                steps[0].style.display = 'none';
                steps[1].style.display = 'block';
            } else {
                showToast("Please fill in all fields.");
            }
        });

        step2Prev.addEventListener('click', function(){
            steps[1].style.display = 'none';
            steps[0].style.display = 'block';
        });

        document.querySelectorAll('input').forEach(i => {
            i.addEventListener('input', () => i.style.borderColor = '#304374');
        });
    </script>
</body>
</html>