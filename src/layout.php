<?php
// Shared layout component for consistent design
function renderSidebar($currentPage, $userRole) {
    $menuItems = [];
    
    // Define menu items based on role
    if ($userRole === 'admin') {
        $menuItems = [
            ['href' => 'dash_admin.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'transaction_logs.php', 'label' => 'Transaction Logs', 'icon' => 'logs'],
            ['href' => 'insights.php', 'label' => 'Insights', 'icon' => 'insights'],
            ['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
            ['href' => 'users.php', 'label' => 'User Management', 'icon' => 'users'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    } elseif ($userRole === 'manager') {
        $menuItems = [
            ['href' => 'dash_manager.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'insights.php', 'label' => 'Insights', 'icon' => 'insights'],
            ['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    } else { // staff
        $menuItems = [
            ['href' => 'dash_staff.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    }
    
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
        'inventory' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
        'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
        'logs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 0 6.75 5.625v1.5c0 .621.504 1.125 1.125 1.125h.375m-1.5-3-3.75 3.75" />',
        'insights' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />',
        'reports' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372m-10.002-.372a9.338 9.338 0 0 0-2.625.372M12 21a9.338 9.338 0 0 0 9 0a9.338 9.338 0 0 0-18 0M12 2.25v12.75a4.5 4.5 0 0 0 0 8.25a4.5 4.5 0 0 0 0-8.25V2.25Z" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688 0-1.25-.561-1.25-1.25 0-.688.562-1.25 1.25-1.25s1.25.562 1.25 1.25c0 .688-.562 1.25-1.25 1.25Zm0 0c-.688 0-1.25-.561-1.25-1.25 0-.688.562-1.25 1.25-1.25s1.25.562 1.25 1.25c0 .688-.562 1.25-1.25 1.25Zm0 0c-.688 0-1.25-.561-1.25-1.25 0-.688.562-1.25 1.25-1.25s1.25.562 1.25 1.25c0 .688-.562 1.25-1.25 1.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5c0-1.23-.323-2.38-.87-3.39l.035-.02a.75.75 0 0 1 .64 1.14l-.036.021a3.73 3.73 0 0 1-.869 3.39.75.75 0 0 1-1.14-.64l.021-.035a3.73 3.73 0 0 0 0-4.78.75.75 0 0 1 .64-1.14l.035.02a3.73 3.73 0 0 0 .869-3.39.75.75 0 0 1 1.14.64l-.02.035c.548 1.01.87 2.16.87 3.39s-.323 2.38-.87 3.39l.02.035a.75.75 0 0 1-.64 1.14l-.035-.02c.548 1.01.87 2.16.87 3.39s-.323 2.38-.87 3.39l.035.02a.75.75 0 0 1-.64-1.14l-.035-.021a3.73 3.73 0 0 1 .869-3.39.75.75 0 0 1 1.14.64l-.021.035c.548-1.01.87-2.16.87-3.39Zm-15 0c0-1.23.323-2.38.87-3.39l-.035-.02a.75.75 0 0 0-.64 1.14l.036.021a3.73 3.73 0 0 0 .869 3.39.75.75 0 0 0 1.14-.64l-.021-.035a3.73 3.73 0 0 1 0-4.78.75.75 0 0 0-.64-1.14l-.035.02a3.73 3.73 0 0 1-.869-3.39.75.75 0 0 0-1.14.64l.02.035C4.823 6.62 4.5 7.77 4.5 9s.323 2.38.87 3.39l-.02.035a.75.75 0 0 0 .64 1.14l.035-.02c-.548 1.01-.87 2.16-.87 3.39s.323 2.38.87 3.39l-.035.02a.75.75 0 0 0 .64-1.14l.035-.021a3.73 3.73 0 0 0-.869-3.39.75.75 0 0 0-1.14.64l.021.035c-.548-1.01-.87-2.16-.87-3.39Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 13.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />'
    ];
    
    echo '<div class="premium-sidebar w-[290px] text-white p-3 pt-5 flex flex-col">';
    echo '<div class="ml-2 mb-4">';
    echo '<img src="img/logo.svg" alt="IntelliWare" class="w-[220px]" />';
    echo '</div>';
    echo '<div class="p-2 flex flex-col gap-[8px]">';
    
    foreach ($menuItems as $item) {
        $isActive = (basename($_SERVER['PHP_SELF']) === basename($item['href']));
        $activeClass = $isActive ? 'active' : '';
        echo '<a class="premium-menu-item ' . $activeClass . '" href="' . $item['href'] . '">';
        echo '<svg class="sidebar-icon w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">';
        echo $icons[$item['icon']];
        echo '</svg>';
        echo '<span class="sidebar-text">' . $item['label'] . '</span>';
        echo '</a>';
    }
    
    echo '</div>';
    
    // --- UPDATED LOGOUT BUTTON ---
    echo '<div class="mt-auto p-2">';
    echo '<a class="premium-menu-item premium-menu-item-logout" href="logout.php" style="justify-content: center;">';
    echo '<svg class="sidebar-icon w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>'; // Logout icon
    echo '</svg>';
    echo '<span class="sidebar-text">Logout</span>';
    echo '</a>';
    echo '</div>';
    // --- END OF UPDATE ---
    
    echo '</div>';
}

// --- UPDATED renderHeader to accept $lowStockCount ---
// --- AND TO INCLUDE THE "REPORTS" DROPDOWN ITEM ---
function renderHeader($pageTitle, $userRole, $username, $lowStockCount = 0) {
    echo '<div class="premium-admin-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">';
    echo '<div class="font-onest text-[20px] font-semibold text-white">' . htmlspecialchars($pageTitle) . '</div>';
    echo '<div class="admin-user-section">';

    // --- MOVED NOTIFICATION BELL LOGIC HERE ---
    if ($userRole === 'admin') {
        echo '<div class="notification-container">';
        echo '<div class="notification-bell" onclick="toggleNotificationDropdown()">';
        echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>';
        if ($lowStockCount > 0) {
            echo '<div class="notification-badge" id="notificationBadge">' . $lowStockCount . '</div>';
        }
        echo '</div>';
        
        echo '<div class="notification-dropdown" id="notificationDropdown">';
        echo '<div class="notification-header">';
        echo '<div class="notification-title">Notifications</div>';
        echo '<button class="notification-clear" onclick="clearAllNotifications()">Clear All</button>';
        echo '</div>';
        echo '<div class="notification-list" id="notificationList">';
        echo '<div class="notification-empty">Loading...</div>'; // Will be populated by JS
        echo '</div>';
        echo '</div>';
        echo '</div>'; // end .notification-container
    }
    // --- END MOVED NOTIFICATION LOGIC ---

    echo '<div class="user-profile" onclick="toggleUserDropdown()">';
    echo '<img src="https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=51D55A&color=fff&size=40&bold=true" alt="User Avatar" class="user-avatar" />';
    echo '<div class="user-details">';
    echo '<div class="user-name">' . htmlspecialchars($username) . '</div>';
    echo '<div class="user-role">' . ucfirst($userRole) . '</div>';
    echo '</div>';
    echo '<div class="user-dropdown" id="userDropdown">';

    // Conditionally add "Reports" link for admin and manager
    if ($userRole === 'admin' || $userRole === 'manager') {
        echo '<a href="reports.php" class="dropdown-item">';
        // SVG Icon for Reports (copied from your $icons array)
        echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />';
        echo '</svg>';
        echo 'Reports</a>';
    }

    echo '<a href="settings.php" class="dropdown-item">';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
    echo 'Settings</a>';
    echo '<a href="logout.php" class="dropdown-item">';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>';
    echo 'Logout</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // close header wrapper
    echo '</div>'; 
}

// --- UPDATED renderLayoutStart to accept $lowStockCount ---
function renderLayoutStart($pageTitle, $userRole, $username, $lowStockCount = 0) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link href="styles.css" rel="stylesheet" />';

    // --- CONDITIONALLY ADD DASHBOARD CSS ---
    if (basename($_SERVER['PHP_SELF']) === 'dash_admin.php') {
        echo '<link href="admin_dashboard.css" rel="stylesheet">';
    }

    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;5m0;600;700&display=swap" rel="stylesheet">';
    echo '<title>' . htmlspecialchars($pageTitle) . ' | IntelliWare</title>';
    echo '<style>';
    echo 'body { font-family: \'Inter\', sans-serif; }';
    echo '.premium-sidebar { background: linear-gradient(180deg, #1D387B 0%, #13275B 100%); border-right: 1px solid rgba(42, 68, 132, 0.6); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3), inset -1px 0 0 rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }';
    echo '.premium-menu-item { border: 1px solid rgba(42, 68, 132, 0.6); color: #E3E3E3; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; text-decoration: none; font-family: \'Onest\', sans-serif; font-weight: 500; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }';
    echo '.premium-menu-item:hover { background: linear-gradient(135deg, rgba(19, 39, 91, 0.8) 0%, rgba(29, 56, 123, 0.6) 100%); border-color: rgba(81, 213, 90, 0.3); transform: translateX(4px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }';
    
    // --- ADDED LOGOUT BUTTON STYLE ---
    echo '.premium-menu-item-logout:hover { 
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.3) 100%); 
        border-color: rgba(239, 68, 68, 0.4); 
        color: #fca5a5;
    }';
    echo '.premium-menu-item-logout:hover svg { color: #fca5a5; }';
    // --- END OF ADDED STYLE ---

    echo '.premium-menu-item.active { background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%); border-color: rgba(81, 213, 90, 0.4); }';
    echo '.sidebar-icon { width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; }';
    echo '.sidebar-text { font-size: 14px; }';
    echo '.premium-button { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); color: #fff; border: none; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2); text-decoration: none; display: inline-block; }';
    echo '.premium-button:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3); }';
    
    // --- THIS IS THE MODIFIED LINE ---
    echo '.premium-admin-header { background: linear-gradient(135deg, #1D387B); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); border-bottom: 1px solid rgba(0, 0, 0, 0.05); backdrop-filter: blur(10px); position: relative; z-index: 100000; }';
    
    echo '.admin-user-section { display: flex; align-items: center; gap: 16px; position: relative; z-index: 100000; }';
    echo '.user-profile { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(81, 213, 90, 0.1); border-radius: 12px; backdrop-filter: blur(10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(81, 213, 90, 0.2); cursor: pointer; position: relative; }';
    echo '.user-profile:hover { background: rgba(81, 213, 90, 0.15); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2); }';
    echo '.user-avatar { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #51D55A; object-fit: cover; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3); }';
    echo '.user-details { display: flex; flex-direction: column; gap: 2px; }';
    echo '.user-name { font-family: \'Onest\', sans-serif; font-weight: 600; font-size: 14px; color: #1f2937; }';
    echo '.user-role { font-family: \'Onest\', sans-serif; font-weight: 400; font-size: 12px; color: #6b7280; }';
    echo '.user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px); border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(81, 213, 90, 0.1); border: 1px solid rgba(81, 213, 90, 0.2); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 99999; }';
    echo '.user-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); display: block !important; }';
    echo '.dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; font-family: \'Onest\', sans-serif; font-size: 14px; font-weight: 500; transition: all 0.2s ease; border-bottom: 1px solid rgba(0, 0, 0, 0.05); }';
    echo '.dropdown-item:last-child { border-bottom: none; }';
    echo '.dropdown-item:hover { background: rgba(81, 213, 90, 0.1); color: #1f2937; }';
    echo '.dropdown-item svg { width: 16px; height: 16px; color: #6b7280; }';
    echo '.dropdown-item:hover svg { color: #51D55A; }';
    
    // --- NOTIFICATION STYLES (MOVED FROM DASHBOARD) ---
    echo '.notification-container { position: relative; display: flex; align-items: center; gap: 16px; z-index: 100000; }';
    echo '.notification-bell { position: relative; width: 40px; height: 40px; background: rgba(81, 213, 90, 0.1); border: 1px solid rgba(81, 213, 90, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px); }';
    echo '.notification-bell:hover { background: rgba(81, 213, 90, 0.15); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2); }';
    echo '.notification-bell svg { width: 20px; height: 20px; color: #51D55A; transition: all 0.3s ease; }';
    echo '.notification-bell:hover svg { color: #4BC052; transform: scale(1.1); }';
    echo '.notification-badge { position: absolute; top: -4px; right: -4px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; font-family: \'Onest\', sans-serif; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4); animation: pulse 2s infinite; }';
    echo '@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }';
    echo '.notification-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px); border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(81, 213, 90, 0.1); border: 1px solid rgba(81, 213, 90, 0.2); min-width: 320px; max-width: 400px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 99998; }';
    echo '.notification-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); display: block !important; }';
    echo '.notification-header { padding: 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: center; justify-content: space-between; }';
    echo '.notification-title { font-family: \'Onest\', sans-serif; font-weight: 600; font-size: 16px; color: #1f2937; }';
    echo '.notification-clear { background: none; border: none; color: #6b7280; font-size: 12px; cursor: pointer; transition: color 0.2s ease; }';
    echo '.notification-clear:hover { color: #51D55A; }';
    echo '.notification-list { max-height: 300px; overflow-y: auto; }';
    echo '.notification-item { padding: 12px 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: flex-start; gap: 12px; transition: background-color 0.2s ease; }';
    echo '.notification-item:hover { background: rgba(81, 213, 90, 0.05); }';
    echo '.notification-item:last-child { border-bottom: none; }';
    echo '.notification-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }';
    echo '.notification-icon.low-stock { background: rgba(239, 68, 68, 0.1); color: #ef4444; }';
    echo '.notification-content { flex: 1; }';
    echo '.notification-message { font-family: \'Onest\', sans-serif; font-size: 14px; color: #374151; margin-bottom: 4px; line-height: 1.4; }';
    echo '.notification-time { font-family: \'Onest\', sans-serif; font-size: 12px; color: #6b7280; }';
    echo '.notification-empty { padding: 32px 16px; text-align: center; color: #6b7280; font-family: \'Onest\', sans-serif; font-size: 14px; }';
    
    echo '.dropdown-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.1); backdrop-filter: blur(2px); z-index: 99990; opacity: 0; visibility: hidden; transition: all 0.3s ease; }';
    echo '.dropdown-backdrop.show { opacity: 1; visibility: visible; }';
    echo '</style>';
    echo '</head>';
    echo '<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">';
    echo '<div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">';
    
    renderSidebar($pageTitle, $userRole);
    
    echo '<div class="flex-1 flex flex-col">';
    // --- PASS $lowStockCount to renderHeader ---
    renderHeader($pageTitle, $userRole, $username, $lowStockCount);
    echo '<div class="p-6 premium-scrollbar" style="background:#0b1438; flex:1; overflow-y:auto; overflow-x:hidden; position:relative; z-index:1; padding:24px 32px;">';
}

function renderLayoutEnd() {
    echo '</div>'; // Close content area
    echo '</div>'; // Close main content
    echo '</div>'; // Close main container
    
    echo '';
    echo '<div class="dropdown-backdrop" id="dropdownBackdrop"></div>';
    
    echo '<script>';
    // --- MOVED ALL JAVASCRIPT to renderLayoutEnd ---
    echo 'function toggleUserDropdown() {';
    echo 'const dropdown = document.getElementById(\'userDropdown\');';
    echo 'const notificationDropdown = document.getElementById(\'notificationDropdown\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'dropdown.classList.toggle(\'show\');';
    echo 'if (notificationDropdown) notificationDropdown.classList.remove(\'show\');';
    echo 'if (dropdown.classList.contains(\'show\')) { backdrop.classList.add(\'show\'); } else { backdrop.classList.remove(\'show\'); }';
    echo '}';
    
    echo 'function toggleNotificationDropdown() {';
    echo 'const dropdown = document.getElementById(\'notificationDropdown\');';
    echo 'const userDropdown = document.getElementById(\'userDropdown\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'if (!dropdown) return;';
    echo 'dropdown.classList.toggle(\'show\');';
    echo 'if (userDropdown) userDropdown.classList.remove(\'show\');';
    echo 'if (dropdown.classList.contains(\'show\')) { backdrop.classList.add(\'show\'); fetchNotifications(); } else { backdrop.classList.remove(\'show\'); }';
    echo '}';

    echo 'function fetchNotifications() {';
    echo 'const notificationList = document.getElementById(\'notificationList\');';
    echo 'if (!notificationList) return;';
    echo 'notificationList.innerHTML = \'<div class="notification-empty">Loading...</div>\';';
    // This is a placeholder. You would replace this with a real fetch call
    // to a PHP file (e.g., 'api/get_notifications.php')
    echo 'setTimeout(() => {';
    echo 'const badge = document.getElementById(\'notificationBadge\');';
    echo 'if (badge && parseInt(badge.textContent, 10) > 0) {';
    echo 'notificationList.innerHTML = \'<div class="notification-item"><div class="notification-icon low-stock"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg></div><div class="notification-content"><div class="notification-message">You have \' + badge.textContent + \' items running low on stock.</div><div class="notification-time">Just now</div></div></div>\';';
    echo '} else {';
    echo 'notificationList.innerHTML = \'<div class="notification-empty">No new notifications</div>\';';
    echo '}';
    echo '}, 500);'; // Simulate network delay
    echo '}';

    echo 'function clearAllNotifications() {';
    echo 'const notificationList = document.getElementById(\'notificationList\');';
    echo 'const notificationBadge = document.getElementById(\'notificationBadge\');';
    echo 'if (notificationList) notificationList.innerHTML = \'<div class="notification-empty">No notifications</div>\';';
    echo 'if (notificationBadge) notificationBadge.style.display = \'none\';';
    echo 'if (document.getElementById(\'notificationDropdown\')) document.getElementById(\'notificationDropdown\').classList.remove(\'show\');';
    echo '}';

    echo 'document.addEventListener(\'click\', function(event) {';
    echo 'const userDropdown = document.getElementById(\'userDropdown\');';
    echo 'const notificationDropdown = document.getElementById(\'notificationDropdown\');';
    echo 'const userProfile = document.querySelector(\'.user-profile\');';
    echo 'const notificationBell = document.querySelector(\'.notification-bell\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    
    echo 'if (userProfile && !userProfile.contains(event.target) && userDropdown) {';
    echo 'userDropdown.classList.remove(\'show\');';
    echo '}';
    
    echo 'if (notificationBell && !notificationBell.contains(event.target) && notificationDropdown) {';
    echo 'notificationDropdown.classList.remove(\'show\');';
    echo '}';
    
    echo 'if (backdrop && (!userDropdown || !userDropdown.classList.contains(\'show\')) && (!notificationDropdown || !notificationDropdown.classList.contains(\'show\'))) {';
    echo 'backdrop.classList.remove(\'show\');';
    echo '}';
    echo '});';
    
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'if (backdrop) {';
    echo 'backdrop.addEventListener(\'click\', function() {';
    echo 'if (document.getElementById(\'userDropdown\')) document.getElementById(\'userDropdown\').classList.remove(\'show\');';
    echo 'if (document.getElementById(\'notificationDropdown\')) document.getElementById(\'notificationDropdown\').classList.remove(\'show\');';
    echo 'this.classList.remove(\'show\');';
    echo '});';
    echo '}';

    echo '</script>';
    echo '</body>';
    echo '</html>';
}
?>