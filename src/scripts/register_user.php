<?php
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['Username']) || ($_SESSION['Role'] ?? '') !== 'admin') {
	header('Location: ../../signin.php');
	exit();
}

function redirect_with_error($msg) {
	header('Location: ../../users.php?error=' . urlencode($msg));
	exit();
}

$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm_password'] ?? '');
$first    = trim($_POST['first_name'] ?? '');
$last     = trim($_POST['last_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$fullName = $first . ' ' . $last;
$role = trim($_POST['role'] ?? '');
$employeeId = trim($_POST['employee_id'] ?? '');

if ($password === '' || $confirm === '' || $first === '' || $last === '' || $email === '' || $role === '' || $employeeId === '' || $username === '') {
	redirect_with_error('Please complete all fields.');
}

if ($password !== $confirm) {
	redirect_with_error('Passwords do not match.');
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$allowedRoles = ['admin', 'manager', 'staff'];
if (!in_array($role, $allowedRoles, true)) {
    redirect_with_error('Please select a valid role.');
}

$username = strtolower($username);
if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    redirect_with_error('Username invalid. Use 3-30 chars: letters, numbers, dot, underscore or hyphen.');
}

$checkUsername = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
if (!$checkUsername) {
    redirect_with_error('Database error checking username.');
}
$checkUsername->bind_param('s', $username);
$checkUsername->execute();
$result = $checkUsername->get_result();
if ($result->num_rows > 0) {
    $checkUsername->close();
    redirect_with_error('Username already taken. Please choose another.');
}
$checkUsername->close();

$finalUsername = $username;

$stmt = $conn->prepare('INSERT INTO users (username, password, role, full_name, employee_id, email) VALUES (?,?,?,?,?,?)');
if (!$stmt) {
	redirect_with_error('Failed to prepare statement.');
}
$stmt->bind_param('ssssss', $finalUsername, $passwordHash, $role, $fullName, $employeeId, $email);

if (!$stmt->execute()) {
	$err = $conn->error ?: 'Failed to create account.';
	$stmt->close();
	redirect_with_error($err);
}
$stmt->close();

$adminId = (int)($_SESSION['user_id'] ?? $_SESSION['AccountID'] ?? 0);
$logAction = "Created user account: $finalUsername ($role)";
$logStmt = $conn->prepare('INSERT INTO logs (user_id, action) VALUES (?, ?)');
if ($logStmt) {
	$logStmt->bind_param('is', $adminId, $logAction);
	$logStmt->execute();
	$logStmt->close();
}

$_SESSION['success'] = 'User account created successfully.';
header('Location: ../../users.php');
exit();
