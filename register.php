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
		.auth-wrapper { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; }
		.auth-wrapper .header { margin-top: 24px; margin-bottom: 12px; }
		.signin-logo { width: clamp(280px, 26vw, 360px); height: auto; display: block; margin-left: auto; margin-right: auto; }
		.auth-container { max-width: 560px; width: 92vw; margin-left: auto; margin-right: auto; }
		.auth-container .signinbox { width: 100%; }
		.signinbox { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:24px; color:#E3E3E3; box-shadow: 0 0 20px 2px #304374; }
		.tfieldname { color:#E3E3E3; font-size:12px; margin-bottom:6px; }
		.tf input, .tf .input, .tf select { background:#13275B; color:#fff; border:1px solid #304374; padding:10px 12px; border-radius:6px; width:100%; box-sizing:border-box; }
		.tf select { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23E3E3E3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 35px; }
		.tf select:focus { outline: none; border-color: #51D55A; box-shadow: 0 0 0 1px #51D55A; }
		.btnlogin, .btn { background:#51D55A; color:#fff; border-radius:6px; padding:10px 12px; font-size:14px; border:1px solid transparent; cursor:pointer; width:100%; }
		.btn-secondary { background:#13275B; color:#E3E3E3; border:1px solid #304374; text-align:center; text-decoration:none; display:inline-block; }
		.fieldrow { display:flex; align-items:center; justify-content:space-between; margin-top:10px; }
		.new-user-line { display:flex; align-items:center; justify-content:center; gap:6px; }
		.newt { color:#E3E3E3; font-family:'Onest', sans-serif; }
		.reglink a { color:#9CC4FF; text-decoration:none; }
		.error { background:#ff6b6b33; border:1px solid #ff6b6b; color:#ffdede; padding:8px; border-radius:6px; margin-bottom:10px; }
	</style>
</head>
<body>
	<div class="auth-wrapper">
		<div class="header">
			<img src="img/logo.svg" alt="IntelliWare" class="fade-in signin-logo" />
		</div>
		<div class="auth-container">
			<div class="signinbox">
				<h3>Registration</h3>
				<p class="subtext">Create your account with personal information.</p>
				<hr>
				<?php if (isset($_GET['error'])): ?>
					<div class="error"><?php echo htmlspecialchars($_GET['error']); ?></div>
				<?php endif; ?>
				<form method="POST" action="src/scripts/register_user.php">
					<div style="display:flex; gap:12px;">
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
					<div class="tfieldname" style="margin-top:12px;">Role</div>
					<div class="tf">
						<select class="input" name="role" required>
							<option value="">Select Role</option>
							<option value="admin">Admin</option>
							<option value="manager">Manager</option>
							<option value="staff">Staff</option>
						</select>
					</div>
					<div class="tfieldname" style="margin-top:12px;">Employee ID</div>
					<div class="tf"><input class="input" type="text" name="employee_id" placeholder="e.g. STF-001, MGR-002, ADM-003" required></div>
					<div class="tfieldname" style="margin-top:12px;">Password</div>
					<div class="tf"><input class="input" type="password" name="password" required></div>
					<div class="tfieldname" style="margin-top:12px;">Confirm Password</div>
					<div class="tf"><input class="input" type="password" name="confirm_password" required></div>
					<br>
					<button class="btnlogin" type="submit">Create Account</button>
				</form>
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


