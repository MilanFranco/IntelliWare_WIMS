<?php
session_start();

// 1. AUTHENTICATION & ACCESS CONTROL
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

// 2. HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACTION: ADD USER ---
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "Username and password are required.";
        } elseif ($password !== $confirm) {
            $_SESSION['error'] = "Passwords do not match.";
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
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $hashed, $role, $fullName, $email);
                
                if ($stmt->execute()) {
                    // Log the action
                    $adminId = 1; 
                    $logAction = "Created user account: $username ($role)";
                    $conn->query("INSERT INTO logs (user_id, action) VALUES ($adminId, '$logAction')");
                    
                    $_SESSION['success'] = "User account created successfully.";
                } else {
                    $_SESSION['error'] = "Database error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
        }
    }

    // --- ACTION: DELETE USER (FIXED) ---
    elseif ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        
        // Check if deleting self
        $checkSelf = $conn->query("SELECT username FROM users WHERE user_id = $userId")->fetch_assoc();
        
        if ($checkSelf && $checkSelf['username'] === $_SESSION['Username']) {
            $_SESSION['error'] = "You cannot delete your own account.";
        } else {
            // START TRANSACTION (Safety First)
            $conn->begin_transaction();
            try {
                // 1. Unlink from LOGS table (Set user_id to NULL instead of deleting history)
                $conn->query("UPDATE logs SET user_id = NULL WHERE user_id = $userId");

                // 2. Unlink from TRANSACTIONS table (Set user_id to NULL)
                $conn->query("UPDATE transactions SET user_id = NULL WHERE user_id = $userId");

                // 3. Now it is safe to delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $conn->commit(); // Save changes
                    $_SESSION['success'] = "User deleted successfully.";
                } else {
                    throw new Exception("Error deleting user record.");
                }
                $stmt->close();

            } catch (Exception $e) {
                $conn->rollback(); // Undo changes if something failed
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        }
    }

    // --- ACTION: RESET PASSWORD ---
    elseif ($action === 'reset_pass') {
        $userId = (int)$_POST['user_id'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_new_password'];

        if ($newPass !== $confirmPass) {
            $_SESSION['error'] = "New passwords do not match.";
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Password reset successfully.";
            } else {
                $_SESSION['error'] = "Error resetting password.";
            }
            $stmt->close();
        }
    }

    // Redirect to clear POST data
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
$conn->close();

// 4. RENDER LAYOUT
$pageTitle = 'User Management';
$username = $_SESSION['Username'];
$role = $_SESSION['Role'];

renderLayoutStart($pageTitle, $role, $username, 0);
?>

<style>
    /* --- PREMIUM DESIGN SYSTEM CSS --- */
    
    /* Alerts */
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

    /* Cards */
    .premium-card {
        background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Inputs */
    .premium-input {
        background: rgba(19, 39, 91, 0.8);
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
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.1);
    }
    select.premium-input { cursor: pointer; }

    /* Buttons */
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

    /* Table */
    .premium-table { width: 100%; border-collapse: collapse; }
    .premium-table th {
        text-align: left;
        color: #9aa6d1;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Toast Styles */
    #toast-container {
        visibility: hidden;
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    #toast-container.show { visibility: visible; transform: translateX(-50%) translateY(0); }

    .toast-box {
        background: rgba(19, 39, 91, 0.95);
        border: 1px solid rgba(81, 213, 90, 0.3);
        backdrop-filter: blur(10px);
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 300px;
        color: #fff;
        font-family: 'Onest', sans-serif;
    }
    
    .toast-box.confirm {
        border-color: #f87171; 
    }

    .toast-actions {
        display: flex;
        gap: 8px;
        margin-left: auto;
    }

    .toast-btn {
        padding: 6px 12px;
        border-radius: 6px;
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
    
</style>

<div id="toast-container"></div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <div class="premium-card">
        <div class="p-5 border-b border-[#2A4484]/50 flex items-center gap-3 bg-[#13275B]/30">
            <div class="w-8 h-8 rounded-lg bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            </div>
            <h3 class="font-onest font-semibold text-lg text-white">Add New User</h3>
        </div>
        
        <div class="p-6">
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="hidden" name="action" value="add">
                
                <div class="md:col-span-2">
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Full Name</label>
                    <input type="text" name="full_name" class="premium-input" placeholder="e.g. Juan Dela Cruz" required>
                </div>

                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Username</label>
                    <input type="text" name="username" class="premium-input" placeholder="jdelacruz" required>
                </div>
                
                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Role</label>
                    <select name="role" class="premium-input inventory-select cursor-pointer">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Password</label>
                    <input type="password" name="password" class="premium-input" required>
                </div>
                
                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" class="premium-input" required>
                </div>
                
                <div class="md:col-span-2 mt-2">
                    <button class="premium-button w-full flex items-center justify-center gap-2" type="submit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="premium-card">
        <div class="p-5 border-b border-[#2A4484]/50 flex items-center gap-3 bg-[#13275B]/30">
            <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center text-orange-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
            </div>
            <h3 class="font-onest font-semibold text-lg text-white">Reset Password</h3>
        </div>
        
        <div class="p-6">
            <form method="POST" class="grid grid-cols-1 gap-5">
                <input type="hidden" name="action" value="reset_pass">
                
                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Select User</label>
                    <select name="user_id" class="premium-input inventory-select cursor-pointer" required>
                        <option value="">-- Choose Account --</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?php echo $u['user_id']; ?>">
                                <?php echo htmlspecialchars($u['username'] . ' (' . ucfirst($u['role']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">New Password</label>
                        <input type="password" name="new_password" class="premium-input" required>
                    </div>
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Confirm New</label>
                        <input type="password" name="confirm_new_password" class="premium-input" required>
                    </div>
                </div>
                
                <div class="mt-2">
                    <button class="premium-button w-full flex items-center justify-center gap-2" type="submit" 
                            style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border:none; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="premium-card">
    <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            Active Accounts
        </h3>
        <span class="text-xs text-[#9aa6d1] bg-[#1D387B] px-3 py-1 rounded-full border border-[#2A4484]">
            Total: <?php echo count($users); ?>
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left">
            <thead>
                <tr>
                    <th class="p-4 pl-6">User Profile</th>
                    <th class="p-4">Username</th>
                    <th class="p-4">Role</th>
                    <th class="p-4">Created At</th>
                    <th class="p-4 text-right pr-6">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr class="border-b border-[#2A4484]/30 hover:bg-[#1D387B]/50 transition-colors group">
                    <td class="p-4 pl-6">
                        <div class="flex items-center gap-3">
                            <?php renderUserAvatar($u['full_name'] ?: $u['username'], 32, 'iw-avatar-initials--md'); ?>
                            <span class="font-medium text-[#E3E3E3] font-onest"><?php echo htmlspecialchars($u['full_name'] ?: 'N/A'); ?></span>
                        </div>
                    </td>
                    <td class="p-4 text-[#9aa6d1] font-mono text-sm">
                        @<?php echo htmlspecialchars($u['username']); ?>
                    </td>
                    <td class="p-4">
                        <?php 
                            $roleColor = match($u['role']) {
                                'admin' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'manager' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                default => 'bg-blue-500/10 text-blue-400 border-blue-500/20'
                            };
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $roleColor; ?>">
                            <?php echo ucfirst($u['role']); ?>
                        </span>
                    </td>
                    <td class="p-4 text-[#6b7280] text-sm">
                        <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                    </td>
                    <td class="p-4 text-right pr-6">
                        <?php if ($u['username'] === $_SESSION['Username']): ?>
                            <span class="text-xs text-[#9aa6d1] italic">Current User</span>
                        <?php else: ?>
                            <button onclick="confirmDelete(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" class="text-red-400 hover:text-red-300 transition-colors p-2 rounded-lg hover:bg-red-500/10" title="Delete User">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="text-center p-8 text-[#9aa6d1]">
                        No users found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script>
    // --- TOAST NOTIFICATION LOGIC ---
    
    // Function to show a simple notification toast
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        
        let icon = type === 'success' 
            ? '<svg class="w-5 h-5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';

        toast.className = 'toast-box';
        toast.innerHTML = `
            ${icon}
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        container.classList.add('show');

        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.remove();
            if(container.children.length === 0) container.classList.remove('show');
        }, 3500);
    }

    // Function to show the DELETE CONFIRMATION toast
    function confirmDelete(userId, username) {
        const container = document.getElementById('toast-container');
        
        // Remove existing toasts to avoid clutter
        container.innerHTML = ''; 
        
        const toast = document.createElement('div');
        toast.className = 'toast-box confirm';
        toast.innerHTML = `
            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            <div style="flex:1">
                <div style="font-weight:600; font-size:13px; margin-bottom:2px">Delete ${username}?</div>
                <div style="font-size:11px; color:#9aa6d1">This action cannot be undone.</div>
            </div>
            <div class="toast-actions">
                <button class="toast-btn btn-no" onclick="this.closest('.toast-box').remove()">Cancel</button>
                <button class="toast-btn btn-yes" onclick="submitDelete(${userId})">Delete</button>
            </div>
        `;
        
        container.appendChild(toast);
        container.classList.add('show');
    }

    function submitDelete(userId) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteForm').submit();
    }
    
    <?php if (isset($_SESSION['success'])): ?>
        showToast("<?php echo $_SESSION['success']; ?>", "success");
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        showToast("<?php echo $_SESSION['error']; ?>", "error");
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

</script>

<?php
renderLayoutEnd();
?>