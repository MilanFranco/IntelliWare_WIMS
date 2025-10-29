<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

// Include database connection
require_once 'config/db.php';

// Get products from database
$items = [];
$productQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, 'A1' as location FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    // If no products in database, use session data as fallback
    $items = $_SESSION['items'] ?? [
        [ 'id' => 1, 'sku' => 'W-0001', 'name' => 'Wood Pallet', 'category' => 'Packaging', 'qty' => 120, 'uom' => 'pcs', 'location' => 'A1' ],
        [ 'id' => 2, 'sku' => 'B-0042', 'name' => 'Bolt M8x40', 'category' => 'Hardware', 'qty' => 860, 'uom' => 'pcs', 'location' => 'B3' ],
        [ 'id' => 3, 'sku' => 'T-0100', 'name' => 'Thermal Label 4x6', 'category' => 'Labels', 'qty' => 540, 'uom' => 'roll', 'location' => 'C2' ],
    ];
}

// Get user info from database
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="styles.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet">
  <title>Admin Dashboard | IntelliWare</title>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
    .metric { color:#E3E3E3; font-family:'Onest', sans-serif; font-size:12px; }
    .metric .num { font-size:22px; font-weight:700; font-family:'Overpass', sans-serif; }
    .menu-item { border:1px solid #2A4484; color:#E3E3E3; border-radius:10px; padding:12px 16px; display:block; text-decoration:none; }
    .menu-item:hover { background:#13275B; }
    .btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; }
    
    /* Premium Admin Styles */
    .premium-admin-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.8);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
    }
    
    .admin-user-section {
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
      z-index: 100000;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: rgba(81, 213, 90, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(81, 213, 90, 0.2);
      cursor: pointer;
      position: relative;
    }
    
    .user-profile:hover {
      background: rgba(81, 213, 90, 0.15);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
    }
    
    .user-profile:active {
      transform: translateY(0);
      background: rgba(81, 213, 90, 0.2);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #51D55A;
      object-fit: cover;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3);
    }
    
    .user-avatar:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4);
    }
    
    .user-details {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    
    .user-name {
      font-family: 'Onest', sans-serif;
      font-weight: 600;
      font-size: 14px;
      color: #1f2937;
    }
    
    .user-role {
      font-family: 'Onest', sans-serif;
      font-weight: 400;
      font-size: 12px;
      color: #6b7280;
    }
    
    .premium-metric-card {
      background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
      border: 1px solid rgba(42, 68, 132, 0.6);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(81, 213, 90, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .premium-metric-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(81, 213, 90, 0.5), transparent);
    }
    
    .premium-metric-card:hover {
      transform: translateY(-2px);
      box-shadow: 
        0 12px 40px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(81, 213, 90, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
      border-color: rgba(81, 213, 90, 0.3);
    }
    
    .premium-metric {
      color: #E3E3E3;
      font-family: 'Onest', sans-serif;
      font-size: 14px;
      font-weight: 500;
    }
    
    .premium-metric .num {
      font-size: 28px;
      font-weight: 700;
      font-family: 'Overpass', sans-serif;
      background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: 0 0 20px rgba(81, 213, 90, 0.3);
    }
    
    /* User Dropdown Menu */
    .user-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 8px;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 12px;
      box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(81, 213, 90, 0.1);
      border: 1px solid rgba(81, 213, 90, 0.2);
      min-width: 200px;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 99999;
    }
    
    .user-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
      display: block !important;
    }
    
    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      color: #374151;
      text-decoration: none;
      font-family: 'Onest', sans-serif;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .dropdown-item:last-child {
      border-bottom: none;
    }
    
    .dropdown-item:hover {
      background: rgba(81, 213, 90, 0.1);
      color: #1f2937;
    }
    
    .dropdown-item svg {
      width: 16px;
      height: 16px;
      color: #6b7280;
    }
    
    .dropdown-item:hover svg {
      color: #51D55A;
    }
    
    /* Improved Color Scheme */
    .premium-admin-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.8);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
      position: relative;
      z-index: 100000;
    }
    
    .premium-sidebar {
      background: linear-gradient(180deg, #1D387B 0%, #13275B 100%);
      border-right: 1px solid rgba(42, 68, 132, 0.6);
      box-shadow: 
        4px 0 20px rgba(0, 0, 0, 0.3),
        inset -1px 0 0 rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
    }
    
    .premium-menu-item {
      border: 1px solid rgba(42, 68, 132, 0.6);
      color: #E3E3E3;
      border-radius: 12px;
      padding: 14px 18px;
      display: block;
      text-decoration: none;
      font-family: 'Onest', sans-serif;
      font-weight: 500;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .premium-menu-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(81, 213, 90, 0.1), transparent);
      transition: left 0.5s;
    }
    
    .premium-menu-item:hover {
      background: linear-gradient(135deg, rgba(19, 39, 91, 0.8) 0%, rgba(29, 56, 123, 0.6) 100%);
      border-color: rgba(81, 213, 90, 0.3);
      transform: translateX(4px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .premium-menu-item:hover::before {
      left: 100%;
    }
    
    .premium-button {
      background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-size: 14px;
      font-weight: 600;
      font-family: 'Onest', sans-serif;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 
        0 4px 12px rgba(81, 213, 90, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
      position: relative;
      overflow: hidden;
      text-decoration: none;
      display: inline-block;
    }
    
    .premium-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }
    
    .premium-button:hover {
      transform: translateY(-1px);
      box-shadow: 
        0 6px 20px rgba(81, 213, 90, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
    
    .premium-button:hover::before {
      left: 100%;
    }
    
    .premium-button:active {
      transform: translateY(0);
      box-shadow: 
        0 2px 8px rgba(81, 213, 90, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }
    
    /* Notification System */
    .notification-container {
      position: relative;
      display: flex;
      align-items: center;
      gap: 16px;
      z-index: 100000;
    }
    
    .notification-bell {
      position: relative;
      width: 40px;
      height: 40px;
      background: rgba(81, 213, 90, 0.1);
      border: 1px solid rgba(81, 213, 90, 0.2);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
    }
    
    .notification-bell:hover {
      background: rgba(81, 213, 90, 0.15);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
    }
    
    .notification-bell svg {
      width: 20px;
      height: 20px;
      color: #51D55A;
      transition: all 0.3s ease;
    }
    
    .notification-bell:hover svg {
      color: #4BC052;
      transform: scale(1.1);
    }
    
    .notification-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 700;
      font-family: 'Onest', sans-serif;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    .notification-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 8px;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 12px;
      box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(81, 213, 90, 0.1);
      border: 1px solid rgba(81, 213, 90, 0.2);
      min-width: 320px;
      max-width: 400px;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 99998;
    }
    
    .notification-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
      display: block !important;
    }
    
    .notification-header {
      padding: 16px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .notification-title {
      font-family: 'Onest', sans-serif;
      font-weight: 600;
      font-size: 16px;
      color: #1f2937;
    }
    
    .notification-clear {
      background: none;
      border: none;
      color: #6b7280;
      font-size: 12px;
      cursor: pointer;
      transition: color 0.2s ease;
    }
    
    .notification-clear:hover {
      color: #51D55A;
    }
    
    .notification-list {
      max-height: 300px;
      overflow-y: auto;
    }
    
    .notification-item {
      padding: 12px 16px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: flex-start;
      gap: 12px;
      transition: background-color 0.2s ease;
    }
    
    .notification-item:hover {
      background: rgba(81, 213, 90, 0.05);
    }
    
    .notification-item:last-child {
      border-bottom: none;
    }
    
    .notification-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    
    .notification-icon.low-stock {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .notification-icon.high-stock {
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .notification-icon.new-item {
      background: rgba(59, 130, 246, 0.1);
      color: #3b82f6;
    }
    
    .notification-icon.system {
      background: rgba(139, 92, 246, 0.1);
      color: #8b5cf6;
    }
    
    .notification-content {
      flex: 1;
    }
    
    .notification-message {
      font-family: 'Onest', sans-serif;
      font-size: 14px;
      color: #374151;
      margin-bottom: 4px;
      line-height: 1.4;
    }
    
    .notification-time {
      font-family: 'Onest', sans-serif;
      font-size: 12px;
      color: #6b7280;
    }
    
    .notification-empty {
      padding: 32px 16px;
      text-align: center;
      color: #6b7280;
      font-family: 'Onest', sans-serif;
      font-size: 14px;
    }
    
    /* Dropdown Backdrop */
    .dropdown-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(2px);
      z-index: 99990;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    
    .dropdown-backdrop.show {
      opacity: 1;
      visibility: visible;
    }
  </style>
</head>
<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">
  <div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">
    <div class="premium-sidebar w-[290px] text-white p-3 pt-5 flex flex-col">
      <div class="ml-2 mb-4">
        <img src="img/logo.svg" alt="IntelliWare" class="w-[220px]" />
      </div>
      <div class="p-2 flex flex-col gap-[8px]">
        <a class="premium-menu-item" href="dash_admin.php">Dashboard</a>
        <a class="premium-menu-item" href="products.php">Inventory</a>
        <a class="premium-menu-item" href="reorder.php">Set Reorder Point</a>
        <a class="premium-menu-item" href="transactions.php">Stock Transactions</a>
        <a class="premium-menu-item" href="transaction_logs.php">Transaction Logs</a>
        <a class="premium-menu-item" href="insights.php">Data Mining Insights</a>
        <a class="premium-menu-item" href="reports.php">Reports</a>
        <a class="premium-menu-item" href="users.php">User Management</a>
        <a class="premium-menu-item" href="settings.php">Settings</a>
      </div>
      <div class="mt-auto p-2"><a class="premium-button" href="logout.php">Logout</a></div>
    </div>
    <div class="flex-1 flex flex-col">
      <div class="premium-admin-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">
        <div class="font-onest text-[20px] font-semibold text-gray-800">Admin Dashboard</div>
        <div class="admin-user-section">
          <div class="notification-container">
            <!-- Notification Bell -->
            <div class="notification-bell" onclick="toggleNotificationDropdown()">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
              </svg>
              <div class="notification-badge" id="notificationBadge">3</div>
            </div>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
              <div class="notification-header">
                <div class="notification-title">Notifications</div>
                <button class="notification-clear" onclick="clearAllNotifications()">Clear All</button>
              </div>
              <div class="notification-list" id="notificationList">
                <!-- Low Stock Notification -->
                <div class="notification-item">
                  <div class="notification-icon low-stock">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                  </div>
                  <div class="notification-content">
                    <div class="notification-message">Low stock alert: 5 items are running low on inventory</div>
                    <div class="notification-time">2 minutes ago</div>
                  </div>
                </div>
                
                <!-- High Stock Notification -->
                <div class="notification-item">
                  <div class="notification-icon high-stock">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                  <div class="notification-content">
                    <div class="notification-message">High stock alert: 3 items have exceeded maximum capacity</div>
                    <div class="notification-time">15 minutes ago</div>
                  </div>
                </div>
                
                <!-- New Item Notification -->
                <div class="notification-item">
                  <div class="notification-icon new-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                  </div>
                  <div class="notification-content">
                    <div class="notification-message">New item added: "Premium Wireless Headphones" has been added to inventory</div>
                    <div class="notification-time">1 hour ago</div>
                  </div>
                </div>
                
                <!-- System Notification -->
                <div class="notification-item">
                  <div class="notification-icon system">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                  </div>
                  <div class="notification-content">
                    <div class="notification-message">System update: Database backup completed successfully</div>
                    <div class="notification-time">3 hours ago</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- User Profile -->
          <div class="user-profile" onclick="toggleUserDropdown()">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['Username']); ?>&background=51D55A&color=fff&size=40&bold=true" 
                 alt="User Avatar" class="user-avatar" />
            <div class="user-details">
              <div class="user-name"><?php echo htmlspecialchars($_SESSION['Username']); ?></div>
              <div class="user-role">Administrator</div>
            </div>
            <div class="user-dropdown" id="userDropdown">
              <a href="settings.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
              </a>
              <a href="users.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                User Management
              </a>
              <a href="reports.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Reports
              </a>
              <a href="logout.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6 premium-scrollbar" style="background:#0b1438; flex:1; overflow:auto; position:relative; z-index:1;">
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
          <div class="premium-metric-card premium-metric">
            <div>Total SKUs</div>
            <div class="num"><?php echo count($items); ?></div>
          </div>
          <div class="premium-metric-card premium-metric">
            <div>Total Qty</div>
            <div class="num"><?php echo array_sum(array_map(function($i){return (int)$i['qty'];}, $items)); ?></div>
          </div>
          <div class="premium-metric-card premium-metric">
            <div>Low Stock</div>
            <div class="num"><?php echo count(array_filter($items,function($i){return (int)$i['qty']<50;})); ?></div>
          </div>
          <div class="premium-metric-card premium-metric">
            <div>Categories</div>
            <div class="num"><?php echo count(array_unique(array_map(function($i){return $i['category'];}, $items))); ?></div>
          </div>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-top:16px;">
          <div class="premium-card">
            <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">Quick Links</div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <a class="premium-button" href="products.php">Manage Products</a>
              <a class="premium-button" href="transactions.php">Stock In/Out</a>
              <a class="premium-button" href="reports.php">View Reports</a>
            </div>
          </div>
          <div class="premium-card">
            <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">Inventory Overview</div>
            <canvas id="overviewChart" height="120"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Dropdown Backdrop -->
  <div class="dropdown-backdrop" id="dropdownBackdrop"></div>
  
<script>
  // User dropdown functionality
  function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const backdrop = document.getElementById('dropdownBackdrop');
    
    console.log('Toggling user dropdown');
    dropdown.classList.toggle('show');
    // Close notification dropdown when opening user dropdown
    notificationDropdown.classList.remove('show');
    
    // Show/hide backdrop
    if (dropdown.classList.contains('show')) {
      backdrop.classList.add('show');
      console.log('User dropdown should be visible');
    } else {
      backdrop.classList.remove('show');
      console.log('User dropdown should be hidden');
    }
  }

  // Notification dropdown functionality
  function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const userDropdown = document.getElementById('userDropdown');
    const backdrop = document.getElementById('dropdownBackdrop');
    
    dropdown.classList.toggle('show');
    // Close user dropdown when opening notification dropdown
    userDropdown.classList.remove('show');
    
    // Show/hide backdrop
    if (dropdown.classList.contains('show')) {
      backdrop.classList.add('show');
    } else {
      backdrop.classList.remove('show');
    }
  }

  // Clear all notifications
  function clearAllNotifications() {
    const notificationList = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    
    notificationList.innerHTML = '<div class="notification-empty">No notifications</div>';
    notificationBadge.style.display = 'none';
    
    // Close dropdown after clearing
    document.getElementById('notificationDropdown').classList.remove('show');
  }

  // Close dropdowns when clicking outside
  document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userProfile = document.querySelector('.user-profile');
    const notificationBell = document.querySelector('.notification-bell');
    const backdrop = document.getElementById('dropdownBackdrop');
    
    if (!userProfile.contains(event.target)) {
      userDropdown.classList.remove('show');
    }
    
    if (!notificationBell.contains(event.target)) {
      notificationDropdown.classList.remove('show');
    }
    
    // Hide backdrop if no dropdowns are open
    if (!userDropdown.classList.contains('show') && !notificationDropdown.classList.contains('show')) {
      backdrop.classList.remove('show');
    }
  });

  // Close dropdowns when clicking on backdrop
  document.getElementById('dropdownBackdrop').addEventListener('click', function() {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const backdrop = document.getElementById('dropdownBackdrop');
    
    userDropdown.classList.remove('show');
    notificationDropdown.classList.remove('show');
    backdrop.classList.remove('show');
  });

  // Simulate real-time notifications
  function addNotification(type, message, time) {
    const notificationList = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    
    // Remove empty state if it exists
    const emptyState = notificationList.querySelector('.notification-empty');
    if (emptyState) {
      emptyState.remove();
    }
    
    const notificationItem = document.createElement('div');
    notificationItem.className = 'notification-item';
    
    const iconClass = type === 'low-stock' ? 'low-stock' : 
                     type === 'high-stock' ? 'high-stock' : 
                     type === 'new-item' ? 'new-item' : 'system';
    
    const iconSvg = type === 'low-stock' ? 
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>' :
      type === 'high-stock' ?
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' :
      type === 'new-item' ?
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>' :
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
    
    notificationItem.innerHTML = `
      <div class="notification-icon ${iconClass}">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          ${iconSvg}
        </svg>
      </div>
      <div class="notification-content">
        <div class="notification-message">${message}</div>
        <div class="notification-time">${time}</div>
      </div>
    `;
    
    notificationList.insertBefore(notificationItem, notificationList.firstChild);
    
    // Update badge count
    const currentCount = parseInt(notificationBadge.textContent) || 0;
    notificationBadge.textContent = currentCount + 1;
    notificationBadge.style.display = 'flex';
  }

  // Simulate periodic notifications (for demo purposes)
  setInterval(function() {
    const notifications = [
      { type: 'low-stock', message: 'Low stock alert: Product XYZ is running low', time: 'Just now' },
      { type: 'high-stock', message: 'High stock alert: Product ABC has exceeded capacity', time: 'Just now' },
      { type: 'new-item', message: 'New item added to inventory', time: 'Just now' },
      { type: 'system', message: 'System maintenance completed', time: 'Just now' }
    ];
    
    const randomNotification = notifications[Math.floor(Math.random() * notifications.length)];
    addNotification(randomNotification.type, randomNotification.message, randomNotification.time);
  }, 30000); // Add notification every 30 seconds for demo

  // Chart initialization
  (function(){
    const items = <?php echo json_encode($items); ?>;
    const low = items.filter(i => parseInt(i.qty||0,10) < 50).length;
    const ok = items.filter(i => { const q = parseInt(i.qty||0,10); return q >= 50 && q < 200; }).length;
    const high = items.filter(i => parseInt(i.qty||0,10) >= 200).length;
    const ctx = document.getElementById('overviewChart');
    if(ctx){
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Low','Normal','High'],
          datasets: [{ 
            label:'SKU Count', 
            data:[low, ok, high], 
            backgroundColor:['#f87171','#60a5fa','#34d399'],
            borderColor: ['#ef4444', '#3b82f6', '#10b981'],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
          }]
        },
        options: { 
          plugins:{ 
            legend:{ 
              labels:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 12
                }
              } 
            } 
          }, 
          scales:{ 
            x:{ 
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 11
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.1)',
                drawBorder: false
              } 
            }, 
            y:{ 
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 11
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.1)',
                drawBorder: false
              } 
            } 
          },
          animation: {
            duration: 2000,
            easing: 'easeInOutQuart'
          }
        }
      });
    }
  })();
</script>
</body>
</html>


