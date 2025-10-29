<?php
session_start();

// Very simple demo auth: accept any non-empty username/password
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($username === '' || $password === '') {
    header('Location: ../../signin.php?error=invalid');
    exit();
}

// Seed a demo account profile
$_SESSION['Username'] = $username;
$_SESSION['AccountID'] = 1;
$_SESSION['Role'] = isset($_POST['role']) ? $_POST['role'] : 'staff';

// Seed in-memory inventory if not present
if (!isset($_SESSION['items'])) {
    $_SESSION['items'] = [
        [ 'id' => 1, 'sku' => 'W-0001', 'name' => 'Wood Pallet', 'category' => 'Packaging', 'qty' => 120, 'uom' => 'pcs', 'location' => 'A1' ],
        [ 'id' => 2, 'sku' => 'B-0042', 'name' => 'Bolt M8x40', 'category' => 'Hardware', 'qty' => 860, 'uom' => 'pcs', 'location' => 'B3' ],
        [ 'id' => 3, 'sku' => 'T-0100', 'name' => 'Thermal Label 4x6', 'category' => 'Labels', 'qty' => 540, 'uom' => 'roll', 'location' => 'C2' ],
    ];
}

// Redirect by role
switch ($_SESSION['Role']) {
    case 'admin':
        header('Location: ../../dash_admin.php');
        break;
    case 'manager':
        header('Location: ../../dash_manager.php');
        break;
    default:
        header('Location: ../../dash_staff.php');
}
exit();
?>


