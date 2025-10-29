<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }
$users = $_SESSION['users'] ?? [ ['username'=>'admin','role'=>'admin'], ['username'=>'worker','role'=>'staff'] ];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  if ($action==='add') {
    $users[] = ['username'=>trim($_POST['username']??''),'role'=>$_POST['role']??'staff'];
  } elseif ($action==='delete') {
    $u = $_POST['username'] ?? '';
    $users = array_values(array_filter($users, fn($x)=>$x['username']!==$u));
  }
  $_SESSION['users']=$users; header('Location: users.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="styles.css" rel="stylesheet" />
  <title>User Management | IntelliWare</title>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
    .menu-item { border:1px solid #2A4484; color:#E3E3E3; border-radius:10px; padding:12px 16px; display:block; text-decoration:none; }
    .menu-item:hover { background:#13275B; }
    .btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
    .btn-outline { background:transparent; border:1px solid #2A4484; color:#E3E3E3; }
    .input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
    .label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
    .table { width:100%; border-collapse:collapse; }
    .table th, .table td { padding:10px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:12px; }
  </style>
</head>
<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">
  <div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">
    <div class="sidebar w-[290px] bg-[#1D387B] text-white p-3 pt-5 flex flex-col">
      <div class="ml-2 mb-4"><img src="img/logo.svg" alt="IntelliWare" class="w-[180px]" /><div class="text-[10px]">Warehouse Inventory System</div></div>
      <div class="p-2 flex flex-col gap-[8px]">
        <a class="menu-item" href="dash_admin.php">Dashboard</a>
        <a class="menu-item" href="users.php">User Management</a>
      </div>
      <div class="mt-auto p-2"><a class="btn" href="logout.php">Logout</a></div>
    </div>
    <div class="flex-1 flex flex-col">
      <div class="bg-white px-[50px] py-[20px] h-[67px] flex justify-between items-center" style="box-shadow: 0 3px 4px rgba(0,0,0,.3)">
        <div class="font-onest text-[20px] font-semibold">User Management</div>
      </div>
      <div class="p-6" style="background:#0b1438; flex:1; overflow:auto;">
        <div class="card" style="max-width:640px;">
          <form method="POST" style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:8px; align-items:end;">
            <div><label class="label">Username</label><input class="input" name="username" required /></div>
            <div><label class="label">Role</label>
              <select name="role" class="input">
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="manager">Manager</option>
              </select>
            </div>
            <div><button class="btn" name="action" value="add" type="submit">Add User</button></div>
          </form>
        </div>
        <div class="card" style="margin-top:16px;">
          <table class="table">
            <thead><tr><th>Username</th><th>Role</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($users as $u): ?>
                <tr>
                  <td><?php echo htmlspecialchars($u['username']); ?></td>
                  <td><?php echo htmlspecialchars(strtoupper($u['role'])); ?></td>
                  <td>
                    <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete this user?');">
                      <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>" />
                      <button class="btn btn-outline" name="action" value="delete" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>


















