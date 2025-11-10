<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Register | IntelliWare</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="styles.css?v=1.0" rel="stylesheet">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Onest&display=swap" rel="stylesheet">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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
	    
	    .eye-closed { display: none; }
	
	    .signinbox hr { border: none; border-top: 0.2px solid #2f4b91; width: 100%; }

	    .signinbox { text-align: center; text-shadow: 0 0 3px 3px #3d9743; }

	    .footer hr { border: none; border-top: 0.2px solid #314f9b; margin: 10px auto; width: 80%; }

	    .footer { position: fixed; bottom: 40px; text-align: center; font-family: "Overpass", sans-serif; font-weight: 200; font-size: 12px; color: rgb(168, 168, 168); padding: 10px 0; }

	    .footer a { color: rgb(168, 168, 168); margin: 0 5px; text-decoration: none; }

	    .container img { width: clamp(380px, 20%, 20%); }

	    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

	    .signupbox2, .signinbox {
	        box-shadow: 0 0 20px 2px #304374;
	        padding-left: clamp(20px, 2vw, 10px);
	        padding-right: clamp(20px, 2vw, 10px);
	        border-radius: 10px;
	        background-color: #1D387B;
	        width: clamp(300px, 20vw, 300px);
	        height: auto;
	        border: 0.5px solid #3d74ff;
	    }

	    .signintitle { color: #E3E3E3; font-size: clamp(25px, 0.5vw, 0.5rem); font-family: "Overpass", sans-serif; position: fixed; transform: translate(0, -300px); font-weight: 600; width: 100%; }

	    .tfieldname { text-align: left; font-family: "Onest", sans-serif; font-weight: 500; color: #E3E3E3; margin-bottom: 5px; margin-top: 15px; font-size: clamp(12px, 1.5vw, 12px); width: auto; }

	    .new-user-line { display: flex; justify-content: center; align-items: center; gap: 5px; margin-top: 8px; font-family: "Onest", sans-serif; font-size: clamp(12px, 1.5vw, 12px); color: #E3E3E3; padding-bottom: 8px; }

	    .tf { display: flex; justify-content: center; align-items: center; margin-top: 5px; flex-direction: column; }

	    .tf input { box-shadow: inset 0 2px 2px 2px rgba(0, 0, 0, 0.2); color: white; background-color: #13275B; width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #304374; font-family: "Onest", sans-serif; box-sizing: border-box; }

	    .tf input:focus { outline: none; box-shadow: 0 0 0 1px #51D55A; }

	    .btnlogin { border: 0.2px solid transparent; font-family: "Onest", sans-serif; font-size: 12px; font-weight: 500; color: white; border-radius: 5px; background-color: #51D55A; justify-content: center; align-items: center; width: 100%; padding: 10px; margin-bottom: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); transition: background-color 0.3s ease, transform 0.2s ease; }

	    .btnlogin:hover { cursor: pointer; background-color: #3d9743; border: 0.2px solid #51D55A; transform: scale(1.02); }

	    .onest-regular { font-family: "Onest", sans-serif; font-optical-sizing: auto; font-weight: 400; font-style: normal; }

	    .onest-medium { font-family: "Onest", sans-serif; font-optical-sizing: auto; font-weight: 500; font-style: normal; }

	    .onest-light { font-family: "Onest", sans-serif; font-optical-sizing: auto; font-weight: 300; font-style: normal; }

	    .overpass-semibold { font-family: "Overpass", sans-serif; font-optical-sizing: auto; font-weight: 600; font-style: normal; }

	    .signupbox2 h3, .signinbox h3 { font-family: "Overpass", sans-serif; color: #E3E3E3; font-size: 20px; margin-bottom: 5px; }

	    .signupbox2 .subtext,  .signinbox .subtext { font-family: "Onest", sans-serif; font-size: clamp(12px, 1.2vw, 12px); color: #67e78c; margin-bottom: 10px; }

	    .header { margin-top: 50px; position: relative; width: 100%; text-align: center; margin-bottom: 20px; }

	    .header img { width: clamp(250px, 20%, 300px); animation: fadeIn 2s ease-in-out; }

	    .container { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 40px; padding-bottom: 80px; }

	    .btnjoin { border: 0.2px solid transparent; font-family: "Onest", sans-serif; font-weight: 500; color: white; border-radius: 5px; background-color: #51D55A; padding: 10px 18px; cursor: pointer; font-size: clamp(12px, 1.5vw, 14px); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); transition: background-color 0.3s ease, transform 0.2s ease; }

	    .btnjoin:hover { background-color: #4bc052; transform: scale(1.02); color: #1a3373; }

	    .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 15px 25px; color: #fff; border-radius: 10px; font-family: 'Onest', sans-serif; font-size: 14px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 1000; animation: fadein 0.5s, fadeout 0.5s 3s; }

	    .toast.success { background-color: #28a745; }

	    .toast.error { background-color: #dc3545; }

	    .tf input.error-border { border: 1px solid #dc3545 !important; box-shadow: 0 0 1px #dc3545 !important; transition: border-color 0.3s ease, box-shadow 0.3s ease; }

	</style>
</head>
<body>
	<div class="auth-wrapper">
		<div class="header">
			<img src="img/logo.svg" alt="IntelliWare" class="fade-in signin-logo" />
		</div>
	<div class="container">
			<div class="signinbox">
				<h3>Registration</h3>
				<p class="subtext">Create your account with personal information.</p>
				<hr>
				
                <form id="registerForm" method="POST" action="src/scripts/register_user.php">
					<div class="form-step" data-step="1">
						<div class="tfieldname" style="margin-top:8px;">Username</div>
						<div class="tf"><input class="input" type="text" name="username" placeholder="choose a username (letters, numbers, ._- )" required></div>

						<div style="display:flex; gap:12px; margin-top:8px;">
							<div style="flex:1;">
								<div class="tfieldname">First Name</div>
								<div class="tf"><input class="input" type="text" name="first_name" required></div>
							</div>
							<div style="flex:1;">
								<div class="tfieldname">Surname</div>
								<div class="tf"><input class="input" type="text" name="last_name" required></div>
							</div>
						</div>

						<div class="tfieldname" style="margin-top:12px;">Email Address</div>
						<div class="tf"><input class="input" type="email" name="email" required></div>

						<div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end;">
							<button type="button" class="btn-secondary" id="step1Next" style="padding:8px 12px;">Next ➜</button>
						</div>
					</div>

					<div class="form-step" data-step="2" style="display:none;">
						<div class="tfieldname" style="margin-top:8px;">Role</div>
						<div class="tf">
							<select class="input role-select" name="role" required>
								<option value="">Select Role</option>
								<option value="admin">Admin</option>
								<option value="manager">Manager</option>
								<option value="staff">Staff</option>
							</select>
						</div>

						<div class="tfieldname" style="margin-top:12px;">Employee ID</div>
						<div class="tf"><input class="input" type="text" name="employee_id" placeholder="e.g. STF-001, MGR-002, ADM-003" required></div>

						<div class="tfieldname" style="margin-top:12px;">Password</div>
						<div class="tf"><div class="password-container"><input class="input" type="password" name="password" required id="regPassword"><span class="password-toggle" id="toggleRegPassword">👁️</span></div></div>

						<div class="tfieldname" style="margin-top:12px;">Confirm Password</div>
						<div class="tf"><input class="input" type="password" name="confirm_password" required></div>

						<div style="display:flex; gap:8px; margin-top:14px;">
							<button type="button" class="btn-secondary" id="step2Prev" style="padding:8px 12px;">← Back</button>
							<button type="submit" class="btnlogin" id="finalSubmit">Create Account</button>
						</div>
					</div>
				</form>

				<script>
				// Multi-step form logic
				(function(){
				    const step1Next = document.getElementById('step1Next');
				    const step2Prev = document.getElementById('step2Prev');
				    const steps = document.querySelectorAll('.form-step');
				    function showStep(n){
				        steps.forEach((s,i)=> s.style.display = (i===n? 'block':'none'));
				        // keep container height consistent by scrolling top
				        window.scrollTo(0,0);
				    }

				    function showToast(message, type='error'){
				        const toast = document.createElement('div');
				        toast.className = 'toast ' + (type || 'error');
				        toast.innerText = message;
				        document.body.appendChild(toast);
				        setTimeout(()=> toast.remove(), 3000);
				    }

				    step1Next.addEventListener('click', function(){
				        // simple required check for visible inputs
				        const step = steps[0];
				        const inputs = step.querySelectorAll('input[required], select[required]');
				        for(const inp of inputs){
				            if(!inp.value.trim()){
				                // show toast like signin page
				                showToast('Please fill in all fields.');
				                inp.classList.add('error-border');
				                return;
				            }
				        }
				        showStep(1);
				    });
				    step2Prev.addEventListener('click', function(){ showStep(0); });
				    // password toggle
				    const toggle = document.getElementById('toggleRegPassword');
				    const pwd = document.getElementById('regPassword');
				    if(toggle && pwd){ toggle.addEventListener('click', function(){ pwd.type = pwd.type === 'password' ? 'text' : 'password'; }); }

				    // show server-side error as toast if present in URL
				    const urlParams = new URLSearchParams(window.location.search);
				    if (urlParams.get('error')) {
				        try {
				            const msg = decodeURIComponent(urlParams.get('error'));
				            showToast(msg, 'error');
				        } catch(e){ showToast('An error occurred', 'error'); }
				    }
				})();
				</script>
			</div>
			<br>
			<div class="signinbox">
				<div class="new-user-line">
					<span class="newt">Already have an account?</span>
					<span class="reglink"><a href="signin.php">Log In here.</a></span>
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
</body>
</html>


