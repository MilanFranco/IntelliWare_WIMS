<?php
session_start();

// 1. AUTHENTICATION & ACCESS CONTROL
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper: Get Current Admin's User ID for Logging
function getCurrentAdminId($conn) {
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    $username = $_SESSION['Username'] ?? '';
    if ($username) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            $_SESSION['user_id'] = (int)$res['user_id'];
            return (int)$res['user_id'];
        }
    }
    return 0;
}

// 2. HANDLE FORM SUBMISSIONS
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, (string)$_POST['csrf_token'])) {
        $_SESSION['error'] = 'Security validation failed (CSRF token mismatch). Please refresh and try again.';
        header('Location: users.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    // --- ACTION: ADD USER ---
    if ($action === 'add') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "Username and password are required.";
        } elseif (strlen($password) < 4) {
            $_SESSION['error'] = "Password must be at least 4 characters long.";
        } elseif ($password !== $confirm) {
            $_SESSION['error'] = "Passwords do not match.";
        } elseif (!in_array($role, ['admin', 'manager', 'staff'], true)) {
            $_SESSION['error'] = "Invalid role selected.";
        } else {
            // Check for duplicate username
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Username already exists.";
            } else {
                // Insert new user
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $notifEmailEnabled = isset($_POST['notif_email_enabled']) ? 1 : ($role === 'admin' ? 1 : 0);
                $notificationEmail = trim($_POST['notification_email'] ?? '');

                $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, email, notif_email_enabled, notification_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $username, $hashed, $role, $fullName, $email, $notifEmailEnabled, $notificationEmail);
                
                if ($stmt->execute()) {
                    // Log the action using dynamic admin ID
                    $adminId = getCurrentAdminId($conn);
                    if ($adminId > 0) {
                        $logAction = "Created user account: $username ($role)";
                        $lStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                        $lStmt->bind_param("is", $adminId, $logAction);
                        $lStmt->execute();
                        $lStmt->close();
                    }
                    
                    $_SESSION['success'] = "User account @" . htmlspecialchars($username) . " created successfully.";
                } else {
                    $_SESSION['error'] = "Database error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
        }
    }

    // --- ACTION: EDIT USER ---
    elseif ($action === 'edit') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newRole = $_POST['role'] ?? 'staff';
        $notifEmailEnabled = isset($_POST['notif_email_enabled']) ? 1 : 0;
        $notificationEmail = trim($_POST['notification_email'] ?? '');

        if (!in_array($newRole, ['admin', 'manager', 'staff'], true)) {
            $_SESSION['error'] = "Invalid role selected.";
        } elseif ($userId <= 0) {
            $_SESSION['error'] = "Invalid user ID.";
        } else {
            // Fetch existing user to verify
            $uQuery = $conn->prepare("SELECT username, role FROM users WHERE user_id = ?");
            $uQuery->bind_param("i", $userId);
            $uQuery->execute();
            $targetUser = $uQuery->get_result()->fetch_assoc();
            $uQuery->close();

            if (!$targetUser) {
                $_SESSION['error'] = "User account not found.";
            } elseif ($targetUser['username'] === $_SESSION['Username'] && $newRole !== 'admin') {
                $_SESSION['error'] = "You cannot demote your own administrator account.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, notif_email_enabled = ?, notification_email = ? WHERE user_id = ?");
                $stmt->bind_param("sssisi", $fullName, $email, $newRole, $notifEmailEnabled, $notificationEmail, $userId);
                
                if ($stmt->execute()) {
                    $adminId = getCurrentAdminId($conn);
                    if ($adminId > 0) {
                        $logAction = "Updated user profile: " . $targetUser['username'] . " (Role: $newRole)";
                        $lStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                        $lStmt->bind_param("is", $adminId, $logAction);
                        $lStmt->execute();
                        $lStmt->close();
                    }
                    $_SESSION['success'] = "User details for @" . htmlspecialchars($targetUser['username']) . " updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating user: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // --- ACTION: DELETE USER ---
    elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        $uQuery = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
        $uQuery->bind_param("i", $userId);
        $uQuery->execute();
        $targetUser = $uQuery->get_result()->fetch_assoc();
        $uQuery->close();
        
        if (!$targetUser) {
            $_SESSION['error'] = "User account not found.";
        } elseif ($targetUser['username'] === $_SESSION['Username']) {
            $_SESSION['error'] = "You cannot delete your own account.";
        } else {
            $conn->begin_transaction();
            try {
                // 1. Unlink from logs table
                $conn->query("UPDATE logs SET user_id = NULL WHERE user_id = $userId");

                // 2. Unlink from transactions table
                $conn->query("UPDATE transactions SET user_id = NULL WHERE user_id = $userId");

                // 3. Delete user record
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    
                    // Log deletion
                    $adminId = getCurrentAdminId($conn);
                    if ($adminId > 0) {
                        $logAction = "Deleted user account: " . $targetUser['username'];
                        $lStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                        $lStmt->bind_param("is", $adminId, $logAction);
                        $lStmt->execute();
                        $lStmt->close();
                    }

                    $_SESSION['success'] = "User @" . htmlspecialchars($targetUser['username']) . " deleted successfully.";
                } else {
                    throw new Exception("Error deleting user record.");
                }
                $stmt->close();

            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        }
    }

    // --- ACTION: RESET PASSWORD ---
    elseif ($action === 'reset_pass') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_new_password'] ?? '';

        if ($userId <= 0) {
            $_SESSION['error'] = "Please select a user account.";
        } elseif (empty($newPass)) {
            $_SESSION['error'] = "New password cannot be empty.";
        } elseif (strlen($newPass) < 4) {
            $_SESSION['error'] = "Password must be at least 4 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $_SESSION['error'] = "New passwords do not match.";
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            if ($stmt->execute()) {
                $adminId = getCurrentAdminId($conn);
                if ($adminId > 0) {
                    $logAction = "Reset password for user ID: $userId";
                    $lStmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                    $lStmt->bind_param("is", $adminId, $logAction);
                    $lStmt->execute();
                    $lStmt->close();
                }
                $_SESSION['success'] = "Password reset successfully.";
            } else {
                $_SESSION['error'] = "Error resetting password.";
            }
            $stmt->close();
        }
    }

    header('Location: users.php');
    exit();
}

// 3. FETCH USERS LIST
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Calculate Summary Counts
$totalUsers = count($users);
$adminCount = 0;
$managerCount = 0;
$staffCount = 0;
foreach ($users as $u) {
    $r = strtolower($u['role'] ?? 'staff');
    if ($r === 'admin') $adminCount++;
    elseif ($r === 'manager') $managerCount++;
    else $staffCount++;
}

// Low Stock Count for Header Bell Badge
$lowStockCount = 0;
$lowStockRes = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE quantity <= reorder_point");
if ($lowStockRes) {
    $lowStockCount = (int)$lowStockRes->fetch_assoc()['cnt'];
}

$conn->close();

// 4. RENDER LAYOUT
$pageTitle = 'User Management';
$username = $_SESSION['Username'];
$role = $_SESSION['Role'];

renderLayoutStart($pageTitle, $role, $username, $lowStockCount);
?>

<style>
    /* --- USER MANAGEMENT STYLES --- */
    
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 24px;
        border: 1px solid;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease-out;
    }
    .alert-success {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
        border-color: rgba(16, 185, 129, 0.3);
    }
    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.3);
    }
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .user-hero-card {
        background: linear-gradient(135deg, rgba(29, 56, 123, 0.92) 0%, rgba(18, 39, 91, 0.96) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 18px;
        padding: 24px 28px;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
        position: relative;
        overflow: hidden;
    }
    .user-hero-card::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -40px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(81, 213, 90, 0.08) 0%, rgba(0, 0, 0, 0) 70%);
        pointer-events: none;
    }

    .premium-card {
        background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .premium-card:not(.metric-card) {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .premium-input {
        background: rgba(19, 39, 91, 0.85);
        color: #E3E3E3;
        border: 1px solid rgba(48, 67, 116, 0.6);
        border-radius: 12px;
        padding: 0 16px;
        font-size: 14px;
        font-family: 'Onest', sans-serif;
        height: 46px;
        width: 100%;
        transition: all 0.3s;
    }
    .premium-input:focus {
        border-color: #51D55A;
        outline: none;
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.15);
    }
    select.premium-input { cursor: pointer; }

    .role-filter-pill {
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
        font-family: 'Onest', sans-serif;
        background: rgba(19, 39, 91, 0.7);
        border: 1px solid rgba(42, 68, 132, 0.6);
        color: #9aa6d1;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .role-filter-pill:hover {
        color: #fff;
        border-color: rgba(81, 213, 90, 0.4);
    }
    .role-filter-pill.active {
        background: rgba(81, 213, 90, 0.15);
        border-color: #51D55A;
        color: #51D55A;
        box-shadow: 0 0 12px rgba(81, 213, 90, 0.2);
    }

    .premium-button {
        background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 0 24px;
        height: 46px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Onest', sans-serif;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3);
    }
    .premium-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4);
    }

    .premium-table { width: 100%; border-collapse: collapse; }
    .premium-table th {
        text-align: left;
        color: #9aa6d1;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    /* Modal Backdrop */
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(8, 15, 38, 0.82);
        backdrop-filter: blur(6px);
        z-index: 5000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .modal-backdrop.show {
        display: flex;
    }

    /* Toast Notification */
    #toast-container {
        visibility: hidden;
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        z-index: 100005;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: auto;
    }
    #toast-container.show { visibility: visible; transform: translateX(-50%) translateY(0); }

    .toast-box {
        background: rgba(19, 39, 91, 0.95);
        border: 1.5px solid rgba(81, 213, 90, 0.3);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        padding: 13px 22px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 320px;
        max-width: 90vw;
        color: #fff;
        font-family: 'Onest', sans-serif;
    }
    .toast-box.confirm { border-color: #f87171; }
    
    /* Error Toast (Red) */
    .toast-box.toast-error {
        background: rgba(45, 12, 18, 0.96);
        border-color: #ef4444;
        color: #fee2e2;
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.25);
    }
    /* Success Toast (Green) */
    .toast-box.toast-success {
        background: rgba(6, 44, 28, 0.96);
        border-color: #51D55A;
        color: #d1fae5;
        box-shadow: 0 10px 30px rgba(81, 213, 90, 0.25);
    }

    [data-theme="light"] .toast-box.toast-error {
        background: #fef2f2 !important;
        border-color: #ef4444 !important;
        color: #991b1b !important;
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.15) !important;
    }
    [data-theme="light"] .toast-box.toast-success {
        background: #f0fdf4 !important;
        border-color: #22c55e !important;
        color: #166534 !important;
        box-shadow: 0 10px 25px rgba(34, 197, 94, 0.15) !important;
    }
    .toast-actions { display: flex; gap: 8px; margin-left: auto; }
    .toast-btn {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    .btn-yes { background: #ef4444; color: white; }
    .btn-yes:hover { background: #dc2626; }
    .btn-no { background: rgba(255,255,255,0.1); color: #E3E3E3; }
    .btn-no:hover { background: rgba(255,255,255,0.2); }

    /* ── Light Mode Overrides ── */
    [data-theme="light"] .user-hero-card {
        background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
        border-color: #93C5FD;
        box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
    }
    [data-theme="light"] .premium-card {
        background: #FFFFFF;
        border: 1px solid #DBEAFE;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06), 0 4px 16px rgba(59, 130, 246, 0.08);
    }
    [data-theme="light"] .premium-input {
        background: #F8FAFC;
        color: #0F172A;
        border-color: #CBD5E1;
    }
    [data-theme="light"] .premium-input:focus {
        border-color: #1B8F3E;
        box-shadow: 0 0 0 3px rgba(27, 143, 62, 0.12);
    }
    [data-theme="light"] .premium-button {
        background: #1B8F3E;
        box-shadow: 0 4px 12px rgba(27, 143, 62, 0.3);
    }
    [data-theme="light"] .premium-button:hover {
        background: #157832;
        box-shadow: 0 6px 16px rgba(27, 143, 62, 0.4);
    }
    [data-theme="light"] .role-filter-pill {
        background: #EFF6FF;
        border-color: #BFDBFE;
        color: #475569;
    }
    [data-theme="light"] .role-filter-pill:hover {
        color: #0F172A;
        border-color: #93C5FD;
    }
    [data-theme="light"] .role-filter-pill.active {
        background: rgba(27, 143, 62, 0.1);
        border-color: #1B8F3E;
        color: #0F6E2C;
    }
    [data-theme="light"] .premium-table th {
        color: #475569;
    }
    [data-theme="light"] .btn-no {
        background: #E2E8F0;
        color: #334155;
    }
    [data-theme="light"] .btn-no:hover {
        background: #CBD5E1;
    }
</style>

<!-- 1. Hero Page Header Banner -->
<div class="user-hero-card page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="hero-tagline flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#9aa6d1] mb-1">
                <span>IntelliWare Enterprise WIMS</span>
                <span class="text-[#2A4484]">•</span>
                <span class="text-[#51D55A]">Access Governance</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                User Management & Access Control
            </h1>
            <p class="hero-desc text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Manage organizational credentials, enforce role-based access control (RBAC), and provision system operator profiles across warehouse modules.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="hero-pill-primary inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white">@<?php echo htmlspecialchars($username); ?></strong>
            </span>
            <span class="hero-pill-secondary inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Governance: <strong class="text-white">Strict RBAC</strong>
            </span>
        </div>
    </div>
</div>

<!-- 2. KPI Quick Stats Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-blue-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Accounts</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo number_format($totalUsers); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-red-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Administrators</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($adminCount); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-amber-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Managers</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($managerCount); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-[#51D55A]/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Staff Members</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($staffCount); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A] group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </div>
    </div>
</div>

<!-- 3. Forms Row: Add New User & Reset Password -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
    
    <!-- Add New User -->
    <div class="lg:col-span-7">
        <div class="premium-card">
            <div class="p-5 border-b border-[#2A4484]/50 flex items-center justify-between bg-[#13275B]/40">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    </div>
                    <div>
                        <h3 class="font-onest font-semibold text-lg text-white">Provision New Account</h3>
                        <p class="text-xs text-[#9aa6d1]">Create a new staff, manager, or admin credential.</p>
                    </div>
                </div>
                <span class="text-[11px] font-semibold text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/25 px-2.5 py-1 rounded-full">New Operator</span>
            </div>
            
            <div class="p-6">
                <form method="POST" id="createUserForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Full Name</label>
                        <input type="text" name="full_name" class="premium-input" placeholder="e.g. Juan Dela Cruz" required>
                    </div>

                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Email Address</label>
                        <input type="email" name="email" class="premium-input" placeholder="e.g. juan@intelliware.com">
                    </div>

                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">System Username</label>
                        <input type="text" name="username" class="premium-input" placeholder="e.g. jdelacruz" required>
                    </div>
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Security Role</label>
                        <select name="role" class="premium-input inventory-select cursor-pointer">
                            <option value="staff">Staff (Operational Inventory Access)</option>
                            <option value="manager">Manager (Reports, Rules & Thresholds)</option>
                            <option value="admin">Administrator (Full System Governance)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Initial Password</label>
                        <input type="password" name="password" class="premium-input" placeholder="••••••••" required>
                    </div>
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Confirm Password</label>
                        <input type="password" name="confirm_password" class="premium-input" placeholder="••••••••" required>
                    </div>
                    
                    <div class="md:col-span-2 p-3.5 rounded-xl bg-[#070b1a]/50 border border-[#2A4484]/40 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-white text-xs font-onest font-semibold flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Stock Alert Email Notifications
                                </div>
                                <div class="text-[#9aa6d1] text-[11px] mt-0.5">Receive low &amp; out-of-stock threshold alerts (Admins enabled by default)</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notif_email_enabled" id="addNotifEmailEnabled" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-[#9aa6d1] text-[11px] font-onest font-semibold mb-1 uppercase tracking-wider">Custom Alert Email (Optional)</label>
                            <input type="email" name="notification_email" class="premium-input text-xs" placeholder="Leave blank to use primary email address">
                        </div>
                    </div>

                    <div class="md:col-span-2 mt-2">
                        <button class="premium-button w-full flex items-center justify-center gap-2" type="submit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Create Account & Grant Access
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password -->
    <div class="lg:col-span-5">
        <div class="premium-card">
            <div class="p-5 border-b border-[#2A4484]/50 flex items-center justify-between bg-[#13275B]/40">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-orange-500/15 border border-orange-500/30 flex items-center justify-center text-orange-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                    </div>
                    <div>
                        <h3 class="font-onest font-semibold text-lg text-white">Security Override</h3>
                        <p class="text-xs text-[#9aa6d1]">Reset credentials for any user account.</p>
                    </div>
                </div>
                <span class="text-[11px] font-semibold text-orange-400 bg-orange-500/10 border border-orange-500/25 px-2.5 py-1 rounded-full">Credentials</span>
            </div>
            
            <div class="p-6">
                <form method="POST" id="resetPassForm" class="grid grid-cols-1 gap-4">
                    <input type="hidden" name="action" value="reset_pass">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Target Account</label>
                        <select name="user_id" id="resetUserSelect" class="premium-input inventory-select cursor-pointer" required>
                            <option value="">-- Select Registered Account --</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>">
                                    @<?php echo htmlspecialchars($u['username']); ?> (<?php echo ucfirst($u['role']); ?>) — <?php echo htmlspecialchars($u['full_name'] ?: 'No full name'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">New Password</label>
                            <input type="password" name="new_password" class="premium-input" placeholder="••••••••" required>
                        </div>
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Confirm New</label>
                            <input type="password" name="confirm_new_password" class="premium-input" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <button class="premium-button w-full flex items-center justify-center gap-2" type="submit" 
                                style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border:none; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Override Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 4. Active Accounts Table Card -->
<div class="premium-card mb-8">
    <div class="p-5 border-b border-[#2A4484]/50 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 bg-[#13275B]/40">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-500/15 border border-blue-500/30 flex items-center justify-center text-blue-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <h3 class="font-onest font-semibold text-lg text-white">Registered Accounts Directory</h3>
                <p class="text-xs text-[#9aa6d1] mt-0.5">Filter, audit, edit roles, and manage credentials across all organizational accounts.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            <!-- Role Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                <button type="button" class="role-filter-pill active" onclick="setRoleFilter('all', this)">
                    All (<?php echo $totalUsers; ?>)
                </button>
                <button type="button" class="role-filter-pill" onclick="setRoleFilter('admin', this)">
                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                    Admins (<?php echo $adminCount; ?>)
                </button>
                <button type="button" class="role-filter-pill" onclick="setRoleFilter('manager', this)">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    Managers (<?php echo $managerCount; ?>)
                </button>
                <button type="button" class="role-filter-pill" onclick="setRoleFilter('staff', this)">
                    <span class="w-2 h-2 rounded-full bg-[#51D55A]"></span>
                    Staff (<?php echo $staffCount; ?>)
                </button>
            </div>

            <!-- Live Search -->
            <div class="relative flex-1 sm:w-60">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#9aa6d1]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" id="userSearchInput" onkeyup="filterUserTable()" placeholder="Search directory..." class="premium-input pl-9 h-[38px] text-xs" />
            </div>

            <span class="text-xs text-[#9aa6d1] bg-[#1D387B] px-3 py-2 rounded-xl border border-[#2A4484] whitespace-nowrap">
                Showing: <strong class="text-white" id="userCountDisplay"><?php echo count($users); ?></strong> of <?php echo count($users); ?>
            </span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left" id="userTable">
            <thead>
                <tr class="border-b border-[#2A4484]/40 bg-[#13275B]/25">
                    <th class="p-4 pl-6">User Profile</th>
                    <th class="p-4">Username</th>
                    <th class="p-4">Role Tier</th>
                    <th class="p-4">Stock Alerts</th>
                    <th class="p-4">Registered Date</th>
                    <th class="p-4 text-right pr-6">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): 
                    $uDataJson = htmlspecialchars(json_encode([
                        'user_id' => $u['user_id'],
                        'username' => $u['username'],
                        'full_name' => $u['full_name'] ?? '',
                        'email' => $u['email'] ?? '',
                        'role' => $u['role'],
                        'notif_email_enabled' => $u['notif_email_enabled'] ?? null,
                        'notification_email' => $u['notification_email'] ?? ''
                    ]), ENT_QUOTES, 'UTF-8');

                    $rLower = strtolower($u['role']);
                    $isEnrolled = ($u['notif_email_enabled'] === null || $u['notif_email_enabled'] === '')
                        ? ($rLower === 'admin')
                        : ((int)$u['notif_email_enabled'] === 1);
                    $targetAlertEmail = trim(!empty($u['notification_email']) ? $u['notification_email'] : ($u['email'] ?? ''));
                ?>
                <tr class="user-row border-b border-[#2A4484]/30 hover:bg-[#1D387B]/40 transition-colors group"
                    data-role="<?php echo htmlspecialchars(strtolower($u['role'])); ?>"
                    data-search="<?php echo htmlspecialchars(strtolower(($u['full_name'] ?? '') . ' ' . $u['username'] . ' ' . ($u['email'] ?? '') . ' ' . $u['role'] . ' ' . ($u['notification_email'] ?? ''))); ?>">
                    <td class="p-4 pl-6">
                        <div class="flex items-center gap-3">
                            <?php renderUserAvatar($u['full_name'] ?: $u['username'], 38, 'iw-avatar-initials--md'); ?>
                            <div>
                                <div class="font-semibold text-[#E3E3E3] font-onest text-sm"><?php echo htmlspecialchars($u['full_name'] ?: 'N/A'); ?></div>
                                <div class="text-xs text-[#9aa6d1] mt-0.5"><?php echo htmlspecialchars($u['email'] ?: 'No email registered'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 font-mono text-sm text-[#9aa6d1]">
                        <span class="px-2 py-0.5 rounded bg-[#13275B] border border-[#2A4484] text-[#E3E3E3]">@<?php echo htmlspecialchars($u['username']); ?></span>
                    </td>
                    <td class="p-4">
                        <?php 
                            $roleLower = strtolower($u['role']);
                            $roleStyle = match($roleLower) {
                                'admin' => 'bg-red-500/15 text-red-400 border-red-500/30',
                                'manager' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                default => 'bg-[#51D55A]/15 text-[#51D55A] border-[#51D55A]/30'
                            };
                        ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo $roleStyle; ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo $roleLower === 'admin' ? 'bg-red-400' : ($roleLower === 'manager' ? 'bg-amber-400' : 'bg-[#51D55A]'); ?>"></span>
                            <?php echo ucfirst($u['role']); ?>
                        </span>
                    </td>
                    <td class="p-4">
                        <?php if ($isEnrolled): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30" title="Subscribed to alerts: <?php echo htmlspecialchars($targetAlertEmail ?: 'No address specified'); ?>">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Subscribed <?php echo !empty($u['notification_email']) ? '<span class="text-[10px] text-emerald-300/80">(Custom)</span>' : ''; ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-500/15 text-slate-400 border border-slate-500/30">
                                Opted Out
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-[#9aa6d1] text-xs">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-[#2A4484]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <?php echo !empty($u['created_at']) ? date('M j, Y · g:i A', strtotime($u['created_at'])) : '—'; ?>
                        </div>
                    </td>
                    <td class="p-4 text-right pr-6">
                        <div class="flex items-center justify-end gap-1.5">
                            <!-- Edit User -->
                            <button onclick='openEditModal(<?php echo $uDataJson; ?>)' class="text-[#9aa6d1] hover:text-white hover:bg-blue-500/20 p-2 rounded-xl transition-all" title="Edit Profile & Role">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>

                            <!-- Quick Reset Password -->
                            <button onclick="quickSelectReset(<?php echo $u['user_id']; ?>)" class="text-orange-400 hover:text-orange-300 hover:bg-orange-500/20 p-2 rounded-xl transition-all" title="Quick Reset Password">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            </button>

                            <!-- Delete User -->
                            <?php if ($u['username'] === $_SESSION['Username']): ?>
                                <span class="text-[10px] text-[#9aa6d1] px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 font-medium">Self</span>
                            <?php else: ?>
                                <button onclick="confirmDelete(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" class="text-red-400 hover:text-red-300 hover:bg-red-500/20 p-2 rounded-xl transition-all" title="Revoke Account">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr id="noUsersRow" style="<?php echo empty($users) ? '' : 'display: none;'; ?>">
                    <td colspan="5" class="text-center p-12">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-2xl bg-[#13275B] border border-[#2A4484] flex items-center justify-center text-[#9aa6d1] mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            </div>
                            <div class="text-white font-semibold font-onest">No Accounts Found</div>
                            <div class="text-xs text-[#9aa6d1] mt-1">No user records matched your search query or selected role filter.</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-backdrop" onclick="if(event.target === this) closeEditModal()">
    <div class="premium-card w-full max-w-md p-6 relative shadow-2xl border border-[#2A4484]" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center pb-4 mb-4 border-b border-[#2A4484]/50">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </div>
                Edit User Details
            </h3>
            <button type="button" onclick="closeEditModal()" class="text-[#9aa6d1] hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form method="POST" id="editUserForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="editUserId">

            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Username</label>
                <input type="text" id="editUsername" class="premium-input opacity-70 cursor-not-allowed font-mono text-sm" readonly>
            </div>

            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Full Name</label>
                <input type="text" name="full_name" id="editFullName" class="premium-input" required>
            </div>

            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Email Address</label>
                <input type="email" name="email" id="editEmail" class="premium-input" placeholder="e.g. user@intelliware.com">
            </div>

            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2 uppercase tracking-wider">Assigned Role</label>
                <select name="role" id="editRole" class="premium-input inventory-select cursor-pointer">
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="p-3.5 rounded-xl bg-[#070b1a]/50 border border-[#2A4484]/40 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-white text-xs font-onest font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Stock Alert Emails
                        </div>
                        <div class="text-[#9aa6d1] text-[11px] mt-0.5">Opt in/out of receiving threshold alert emails</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="notif_email_enabled" id="editNotifEmailEnabled">
                        <span class="slider"></span>
                    </label>
                </div>
                <div>
                    <label class="block text-[#9aa6d1] text-[11px] font-onest font-semibold mb-1 uppercase tracking-wider">Custom Alert Email (Optional)</label>
                    <input type="email" name="notification_email" id="editNotificationEmail" class="premium-input text-xs" placeholder="Defaults to account email if left empty">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEditModal()" class="flex-1 py-2.5 rounded-xl border border-[#2A4484] bg-[#13275B] text-[#E3E3E3] hover:text-white text-sm font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="premium-button flex-1 py-2.5 h-auto text-sm font-semibold">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script>
    let activeRoleFilter = 'all';

    function setRoleFilter(role, btnElement) {
        activeRoleFilter = role;
        document.querySelectorAll('.role-filter-pill').forEach(btn => btn.classList.remove('active'));
        if (btnElement) btnElement.classList.add('active');
        filterUserTable();
    }

    // Live Search and Filter in Table
    function filterUserTable() {
        const query = (document.getElementById('userSearchInput').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#userTable tbody tr.user-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchText = (row.getAttribute('data-search') || '').toLowerCase();
            const role = (row.getAttribute('data-role') || '').toLowerCase();

            const matchesQuery = !query || searchText.includes(query);
            const matchesRole = activeRoleFilter === 'all' || role === activeRoleFilter;

            if (matchesQuery && matchesRole) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noRow = document.getElementById('noUsersRow');
        if (noRow) {
            noRow.style.display = (visibleCount === 0) ? '' : 'none';
        }
        const countDisplay = document.getElementById('userCountDisplay');
        if (countDisplay) {
            countDisplay.innerText = visibleCount;
        }
    }

    // Quick select in reset password form
    function quickSelectReset(userId) {
        const select = document.getElementById('resetUserSelect');
        if (select) {
            select.value = userId;
            const form = document.getElementById('resetPassForm');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const newPassInput = form.querySelector('input[name="new_password"]');
                if (newPassInput) newPassInput.focus();
            }
        }
    }

    // Edit Modal Logic
    function openEditModal(user) {
        document.getElementById('editUserId').value = user.user_id;
        document.getElementById('editUsername').value = '@' + user.username;
        document.getElementById('editFullName').value = user.full_name || '';
        document.getElementById('editEmail').value = user.email || '';
        document.getElementById('editRole').value = user.role;

        const isOptedIn = (user.notif_email_enabled !== null && user.notif_email_enabled !== undefined && user.notif_email_enabled !== '')
            ? (parseInt(user.notif_email_enabled) === 1)
            : (user.role.toLowerCase() === 'admin');
        document.getElementById('editNotifEmailEnabled').checked = isOptedIn;
        document.getElementById('editNotificationEmail').value = user.notification_email || '';

        document.getElementById('editUserModal').classList.add('show');
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.remove('show');
    }

    // Standardized Bottom-Center Toast Notification (Color-Coded)
    function showToast(message, type = 'error') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        container.innerHTML = ''; 
        
        const toast = document.createElement('div');
        toast.className = 'toast-box ' + (type === 'success' ? 'toast-success' : 'toast-error');
        const icon = type === 'success'
            ? '<svg class="w-5 h-5 text-[#51D55A] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';
        
        toast.innerHTML = icon + '<span style="font-weight:500; font-size:13.5px;">' + message + '</span>';
        container.appendChild(toast);
        container.classList.add('show');
        
        clearTimeout(container._timer);
        container._timer = setTimeout(() => {
            container.classList.remove('show');
        }, 3500);
    }

    // Delete Confirmation Toast
    function confirmDelete(userId, username) {
        const container = document.getElementById('toast-container');
        container.innerHTML = ''; 
        
        const toast = document.createElement('div');
        toast.className = 'toast-box confirm';
        toast.innerHTML = `
            <svg class="w-6 h-6 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            <div style="flex:1">
                <div style="font-weight:600; font-size:13px; margin-bottom:2px">Delete @${username}?</div>
                <div style="font-size:11px; color:#9aa6d1">This user will be permanently revoked from the system.</div>
            </div>
            <div class="toast-actions">
                <button type="button" class="toast-btn btn-no" onclick="this.closest('.toast-box').remove()">Cancel</button>
                <button type="button" class="toast-btn btn-yes" onclick="submitDelete(${userId})">Delete</button>
            </div>
        `;
        
        container.appendChild(toast);
        container.classList.add('show');
    }

    function submitDelete(userId) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteForm').submit();
    }

    // Client-side Validation: Provision New User
    const createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        const fullNameInput = createUserForm.querySelector('input[name="full_name"]');
        const usernameInput = createUserForm.querySelector('input[name="username"]');
        const passwordInput = createUserForm.querySelector('input[name="password"]');
        const confirmInput  = createUserForm.querySelector('input[name="confirm_password"]');

        [fullNameInput, usernameInput, passwordInput, confirmInput].forEach(el => {
            el?.addEventListener('input', () => el.classList.remove('error-border'));
        });

        createUserForm.addEventListener('submit', function(e) {
            if (!fullNameInput.value.trim()) {
                e.preventDefault();
                fullNameInput.classList.add('error-border');
                fullNameInput.focus();
                showToast('Full name is required.', 'error');
                return;
            }
            if (!usernameInput.value.trim()) {
                e.preventDefault();
                usernameInput.classList.add('error-border');
                usernameInput.focus();
                showToast('Username is required.', 'error');
                return;
            }
            if (!passwordInput.value) {
                e.preventDefault();
                passwordInput.classList.add('error-border');
                passwordInput.focus();
                showToast('Password is required.', 'error');
                return;
            }
            if (passwordInput.value.length < 4) {
                e.preventDefault();
                passwordInput.classList.add('error-border');
                passwordInput.focus();
                showToast('Password must be at least 4 characters long.', 'error');
                return;
            }
            if (passwordInput.value !== confirmInput.value) {
                e.preventDefault();
                confirmInput.classList.add('error-border');
                confirmInput.focus();
                showToast('Passwords do not match.', 'error');
                return;
            }
        });
    }

    // Client-side Validation: Reset Password
    const resetPassForm = document.getElementById('resetPassForm');
    if (resetPassForm) {
        const userSelect   = document.getElementById('resetUserSelect');
        const newPassInput = resetPassForm.querySelector('input[name="new_password"]');
        const confirmInput = resetPassForm.querySelector('input[name="confirm_new_password"]');

        [userSelect, newPassInput, confirmInput].forEach(el => {
            el?.addEventListener('input', () => el.classList.remove('error-border'));
            el?.addEventListener('change', () => el.classList.remove('error-border'));
        });

        resetPassForm.addEventListener('submit', function(e) {
            if (!userSelect.value) {
                e.preventDefault();
                userSelect.classList.add('error-border');
                userSelect.focus();
                showToast('Please select a target account.', 'error');
                return;
            }
            if (!newPassInput.value) {
                e.preventDefault();
                newPassInput.classList.add('error-border');
                newPassInput.focus();
                showToast('New password cannot be empty.', 'error');
                return;
            }
            if (newPassInput.value.length < 4) {
                e.preventDefault();
                newPassInput.classList.add('error-border');
                newPassInput.focus();
                showToast('Password must be at least 4 characters long.', 'error');
                return;
            }
            if (newPassInput.value !== confirmInput.value) {
                e.preventDefault();
                confirmInput.classList.add('error-border');
                confirmInput.focus();
                showToast('New passwords do not match.', 'error');
                return;
            }
        });
    }

    // Client-side Validation: Edit User Modal
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        const editFullName = document.getElementById('editFullName');
        editFullName?.addEventListener('input', () => editFullName.classList.remove('error-border'));

        editUserForm.addEventListener('submit', function(e) {
            if (!editFullName.value.trim()) {
                e.preventDefault();
                editFullName.classList.add('error-border');
                editFullName.focus();
                showToast('Full name cannot be empty.', 'error');
                return;
            }
        });
    }
</script>

<?php
renderLayoutEnd();
?>