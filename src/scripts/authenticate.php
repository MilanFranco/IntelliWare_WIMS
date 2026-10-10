<?php
session_start();

// Include database connection
require_once '../../config/db.php';

// Get form data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validate input
if (empty($username) || empty($password)) {
    header('Location: ../../signin.php?error=empty');
    exit();
}

// Check if user exists in database (by username or email)
$userQuery = "SELECT user_id, username, password, role, full_name FROM users WHERE username = ? OR email = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("ss", $username, $username);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    $userStmt->close();
    $conn->close();
    header('Location: ../../signin.php?error=invalid');
    exit();
}

$user = $userResult->fetch_assoc();
$userStmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    $conn->close();
    header('Location: ../../signin.php?error=invalid');
    exit();
}

// Role is automatically detected from database, no need to validate

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

// Set session variables
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['AccountID'] = $user['user_id']; // Keep consistency with registration script

$_SESSION['Username'] = $user['username'];
$_SESSION['Role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];

// Log the login
$logQuery = "INSERT INTO logs (user_id, action) VALUES (?, ?)";
$logStmt = $conn->prepare($logQuery);
$action = "User logged in";
$logStmt->bind_param("is", $user['user_id'], $action);
$logStmt->execute();
$logStmt->close();

$conn->close();

// Redirect based on role
switch ($user['role']) {
    case 'admin':
        header('Location: ../../dash_admin.php');
        break;
    case 'manager':
        header('Location: ../../dash_manager.php');
        break;
    case 'staff':
        header('Location: ../../dash_staff.php');
        break;
    default:
        header('Location: ../../dash_staff.php');
}

exit();
?>




