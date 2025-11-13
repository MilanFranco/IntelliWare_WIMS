<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

// 1. --- Include the layout and get session data ---
require_once 'src/layout.php'; 

// Use a placeholder if no users exist yet
$users = $_SESSION['users'] ?? [
    ['username' => 'admin', 'role' => 'admin', 'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)]
];

// Get and clear any flash messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);


// 2. --- Handle all form submissions (POST requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACTION: ADD USER ---
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($username) || empty($role) || empty($password)) {
            $_SESSION['error'] = 'Please fill in all fields.';
        } elseif ($password !== $confirm_password) {
            $_SESSION['error'] = 'Passwords do not match.';
        } else {
            // Check for duplicate username
            $isDuplicate = false;
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                $_SESSION['error'] = 'Username already exists.';
            } else {
                // Add new user
                $users[] = [
                    'username' => $username,
                    'role' => $role,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ];
                $_SESSION['users'] = $users;
                $_SESSION['success'] = 'User added successfully.';
            }
        }
    }

    // --- ACTION: DELETE USER ---
    elseif ($action === 'delete') {
        $usernameToDelete = $_POST['username'] ?? '';

        // Validation: Prevent self-deletion
        if ($usernameToDelete === $_SESSION['Username']) {
            $_SESSION['error'] = 'You cannot delete your own account.';
        } else {
            $users = array_values(array_filter($users, fn($u) => $u['username'] !== $usernameToDelete));
            $_SESSION['users'] = $users;
            $_SESSION['success'] = 'User deleted successfully.';
        }
    }

    // --- ACTION: RESET PASSWORD ---
    elseif ($action === 'reset_pass') {
        $usernameToReset = $_POST['username'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        // Validation
        if (empty($usernameToReset) || empty($new_password)) {
            $_SESSION['error'] = 'Please select a user and enter a new password.';
        } elseif ($new_password !== $confirm_new_password) {
            $_SESSION['error'] = 'New passwords do not match.';
        } else {
            // Find and update the user
            foreach ($users as $i => $user) {
                if ($user['username'] === $usernameToReset) {
                    $users[$i]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                    break;
                }
            }
            $_SESSION['users'] = $users;
            $_SESSION['success'] = 'Password for ' . htmlspecialchars($usernameToReset) . ' has been reset.';
        }
    }

    // Redirect after POST to prevent re-submission
    header('Location: users.php');
    exit();
}

// 3. --- Set variables and render the page ---
$pageTitle = 'User Management';
$username = $_SESSION['Username'];
$role = $_SESSION['Role'];
$lowStockCount = 0; // Not applicable on this page

renderLayoutStart($pageTitle, $role, $username, $lowStockCount);
?>

<style>
  .card { 
    background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
    border: 1px solid rgba(42, 68, 132, 0.6);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  }
  .label {
    color: #E3E3E3;
    font-family: 'Onest';
    font-size: 13px;
    margin-bottom: 8px;
    display: block;
    font-weight: 500;
  }
  .input, .select {
    background: rgba(19, 39, 91, 0.8);
    color: #E3E3E3;
    border: 1px solid rgba(48, 67, 116, 0.6);
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 14px;
    font-family: 'Onest', sans-serif;
    transition: all 0.3s;
    width: 100%;
  }
  .input:focus, .select:focus {
    outline: none;
    border-color: #51D55A;
    box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.1);
  }
  .select option {
    background: #13275B;
    color: #E3E3E3;
  }
  .table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 16px;
  }
  .table th, .table td { 
    padding: 14px; 
    border-bottom: 1px solid rgba(42, 68, 132, 0.6);
    color: #E3E3E3; 
    font-family: 'Onest'; 
    font-size: 14px;
    text-align: left;
  }
  .table th {
    font-weight: 600;
    color: #f1f5f9;
  }
  .btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  }
  .btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
  }
  .btn-danger:disabled {
    background: #4b5563;
    color: #9ca3af;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
  }
  .alert {
    padding: 16px;
    border-radius: 12px;
    font-family: 'Onest';
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    border: 1px solid;
  }
  .alert-success {
    background: rgba(16, 185, 129, 0.18);
    color: #6ee7b7;
    border-color: rgba(16, 185, 129, 0.35);
  }
  .alert-error {
    background: rgba(239, 68, 68, 0.18);
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.35);
  }
</style>

<?php if ($success): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>


<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="card">
    <h2 class="text-xl font-semibold text-white font-onest mb-5">Add New User</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <input type="hidden" name="action" value="add">
      
      <div>
        <label class="label">Username</label>
        <input class="input" name="username" required>
      </div>
      
      <div>
        <label class="label">Role</label>
        <select name="role" class="select">
          <option value="staff">Staff</option>
          <option value="manager">Manager</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      
      <div>
        <label class="label">Password</label>
        <input class="input" type="password" name="password" required>
      </div>
      
      <div>
        <label class="label">Confirm Password</label>
        <input class="input" type="password" name="confirm_password" required>
      </div>
      
      <div class="md:col-span-2">
        <button class="premium-button w-full mt-2" type="submit">Add User</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 class="text-xl font-semibold text-white font-onest mb-5">Reset User Password</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <input type="hidden" name="action" value="reset_pass">
      
      <div class="md:col-span-2">
        <label class="label">User to Reset</label>
        <select name="username" class="select" required>
          <option value="">-- Select a user --</option>
          <?php foreach($users as $u): ?>
            <option value="<?php echo htmlspecialchars($u['username']); ?>">
              <?php echo htmlspecialchars($u['username']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="label">New Password</label>
        <input class="input" type="password" name="new_password" required>
      </div>
      
      <div>
        <label class="label">Confirm New Password</label>
        <input class="input" type="password" name="confirm_new_password" required>
      </div>
      
      <div class="md:col-span-2">
        <button class="premium-button w-full mt-2" style="background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); box-shadow: 0 4px 12px rgba(251, 146, 60, 0.3);" type="submit">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<div class="card mt-6">
  <h2 class="text-xl font-semibold text-white font-onest mb-4">Current Users</h2>
  <div class="overflow-x-auto">
    <table class="table min-w-full">
      <thead>
        <tr>
          <th>Username</th>
          <th>Role</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): ?>
          <tr>
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
            <td class="text-right">
              <?php if ($u['username'] === $_SESSION['Username']): ?>
                <button class="btn-danger" disabled>Delete</button>
              <?php else: ?>
                <form method="POST" style="display:inline-block" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                  <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>">
                  <button class="btn-danger" name="action" value="delete" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="3" class="text-center text-gray-400 py-6">No users found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// 4. --- Render the end of the layout ---
renderLayoutEnd();
?>