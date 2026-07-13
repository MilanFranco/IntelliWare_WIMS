<?php
// File: src/layout.php

function getUserInitials($name) {
    $name = trim(preg_replace('/\s+/', ' ', (string)$name));
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function renderUserAvatar($displayName, $size = 40, $extraClass = '') {
    $initials = htmlspecialchars(getUserInitials($displayName), ENT_QUOTES, 'UTF-8');
    $size = max(16, (int)$size);
    $fontSize = max(10, (int)round($size * 0.35));
    $class = trim('user-avatar iw-avatar-initials ' . $extraClass);
    echo '<span class="' . $class . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fontSize . 'px" aria-hidden="true">' . $initials . '</span>';
}

function renderSidebar($currentPage, $userRole) {
    $menuItems = [];
    
    // Define menu items based on role
    if ($userRole === 'admin') {
        $menuItems = [
            ['href' => 'dash_admin.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'insights.php', 'label' => 'Insights', 'icon' => 'insights'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'inventory_history.php', 'label' => 'Transaction History', 'icon' => 'history'],
            ['href' => 'transaction_logs.php', 'label' => 'Transaction Logs', 'icon' => 'logs'],
            ['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
            ['href' => 'users.php', 'label' => 'User Management', 'icon' => 'users'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    } elseif ($userRole === 'manager') {
        $menuItems = [
            ['href' => 'dash_manager.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'insights.php', 'label' => 'Insights', 'icon' => 'insights'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'inventory_history.php', 'label' => 'Transaction History', 'icon' => 'history'],
            ['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    } else { // staff
        $menuItems = [
            ['href' => 'dash_staff.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'inventory_history.php', 'label' => 'Transaction History', 'icon' => 'history'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    }
    
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
        'inventory' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
        'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
        'receive' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
        'release' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />',
        'history' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'logs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 0 6.75 5.625v1.5c0 .621.504 1.125 1.125 1.125h.375m-1.5-3-3.75 3.75" />',
        'insights' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />',
        'reports' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20a2 2 0 00-2-2H9a2 2 0 00-2 2" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25a3 3 0 100-6 3 3 0 000 6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12.75a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" /><circle cx="12" cy="12" r="3" />'
    ];
    
    // UPDATED: Added ID and transition classes for toggling
    echo '<div id="main-sidebar" class="premium-sidebar w-[290px] shrink-0 text-white p-3 pt-5 flex flex-col transition-all duration-300 ease-in-out">';
    echo '<div class="ml-2 mb-4">';
    echo '<img src="img/logo.svg" alt="IntelliWare" class="w-[220px] iw-sidebar-logo iw-sidebar-logo-dark" />';
    echo '<img src="img/logo-light.svg" alt="IntelliWare" class="w-[220px] iw-sidebar-logo iw-sidebar-logo-light" />';
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
    
    echo '<div class="mt-auto p-2">';
    echo '<a class="premium-menu-item premium-menu-item-logout" href="logout.php" style="justify-content: center;">';
    echo '<svg class="sidebar-icon w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>'; // Logout icon
    echo '</svg>';
    echo '<span class="sidebar-text">Logout</span>';
    echo '</a>';
    echo '</div>';
    
    echo '</div>';
}

function renderHeader($pageTitle, $userRole, $username) {
    $displayName = trim($_SESSION['full_name'] ?? '') ?: $username;

    echo '<div class="premium-admin-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">';
    
    // UPDATED: Added Flex container for Toggle Button + Page Title
    echo '<div class="flex items-center gap-4">';
        // HAMBURGER TOGGLE BUTTON
        echo '<button onclick="toggleSidebar()" class="iw-sidebar-toggle transition-colors focus:outline-none">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        echo '</button>';

        echo '<div class="font-onest text-[20px] font-semibold iw-header-title">' . htmlspecialchars($pageTitle) . '</div>';
    echo '</div>';

    echo '<div class="admin-user-section">';

    echo '<div id="header-scanner-status" class="header-scanner-status header-scanner-status--checking" title="Detecting barcode scanner connection.">';
    echo '<svg class="header-scanner-status__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>';
    echo '</svg>';
    echo '<span class="header-scanner-status__dot" aria-hidden="true"></span>';
    echo '<span class="header-scanner-status__text">Checking scanner…</span>';
    echo '</div>';

    if ($userRole === 'admin') {
        echo '<div class="notification-container">';
        
        // Bell Icon
        echo '<div class="notification-bell" id="bellBtn" onclick="toggleNotificationDropdown(event)">';
        echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>';
        // Badge (Starts hidden)
        echo '<div class="notification-badge" id="notificationBadge" style="display:none;">0</div>';
        echo '</div>';
        
        // Dropdown
        echo '<div class="notification-dropdown" id="notificationDropdown" onclick="event.stopPropagation()">';
        echo '<div class="notification-header">';
        echo '<div class="notification-title">Notifications</div>';
        echo '<button class="notification-clear" onclick="fetchNotifications()">Refresh</button>';
        echo '</div>';
        echo '<div class="notification-list" id="notificationList">';
        echo '<div class="notification-empty">Loading...</div>'; 
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '<button type="button" class="theme-toggle" id="themeToggleBtn" onclick="IWTheme.toggleTheme()" title="Toggle light/dark mode" aria-label="Toggle light/dark mode">';
    echo '<svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path stroke-linecap="round" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg>';
    echo '<svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>';
    echo '</button>';

    echo '<div class="user-profile" onclick="toggleUserDropdown(event)">';
    renderUserAvatar($displayName, 40);
    echo '<div class="user-details">';
    echo '<div class="user-name">' . htmlspecialchars($displayName) . '</div>';
    echo '<div class="user-role">' . ucfirst($userRole) . '</div>';
    echo '</div>';
    echo '<div class="user-dropdown" id="userDropdown">';

    if ($userRole === 'admin' || $userRole === 'manager') {
        echo '<a href="reports.php" class="dropdown-item">';
        echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>';
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
    echo '</div>'; // close header wrapper
}

function renderLayoutStart($pageTitle, $userRole, $username, $lowStockCount = 0) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
    echo '<script>(function(){var t=localStorage.getItem("iw-theme")||"dark";document.documentElement.setAttribute("data-theme",t);})();</script>';
    echo '<link href="styles.css" rel="stylesheet" />';
    echo '<link href="theme.css" rel="stylesheet" />';
    echo '<script src="theme.js" defer></script>';
    echo '<script src="https://cdn.tailwindcss.com" onerror="document.documentElement.classList.add(\'iw-no-tailwind\')"></script>';

    if (basename($_SERVER['PHP_SELF']) === 'dash_admin.php') {
        echo '<link href="admin_dashboard.css" rel="stylesheet">';
    }

    echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'">';
    echo '<noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>';
    echo '<title>' . htmlspecialchars($pageTitle) . ' | IntelliWare</title>';
    echo '<style>';
    echo 'body { font-family: \'Inter\', sans-serif; }';
    echo '.premium-menu-item { border: 1px solid var(--iw-border); color: var(--iw-text); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; text-decoration: none; font-family: \'Onest\', sans-serif; font-weight: 500; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }';
    echo '.premium-menu-item:hover { background: var(--iw-bg-card-hover); border-color: var(--iw-border-accent); transform: translateX(4px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }';
    echo '.premium-menu-item-logout:hover { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.3) 100%); border-color: rgba(239, 68, 68, 0.4); color: #fca5a5; }';
    echo '.premium-menu-item-logout:hover svg { color: #fca5a5; }';
    echo '.premium-menu-item.active { background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%); border-color: var(--iw-border-accent); }';
    echo '.sidebar-icon { width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; }';
    echo '.sidebar-text { font-size: 14px; }';
    echo '.btn, .btn-primary, .premium-button, .button-primary { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); color: #fff; border: none; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }';
    echo '.btn::before, .button-primary::before, .premium-button::before { content: \'\'; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: left 0.5s; }';
    echo '.btn:hover, .btn-primary:hover, .premium-button:hover, .button-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3); }';
    echo '.btn:hover::before, .btn-primary:hover::before, .premium-button:hover::before, .button-primary:hover::before { left: 100%; }';
    echo '.btn:active, .btn-primary:active, .button-primary:active, .premium-button:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1); }';
    echo '.btn-secondary, .button-secondary, .btn-outline { background: var(--iw-bg-btn-secondary); color: var(--iw-text); border: 1px solid var(--iw-border-accent); border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.25s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; }';
    echo '.btn-secondary:hover, .button-secondary:hover, .btn-outline:hover { background: var(--iw-bg-btn-secondary-hover); }';
    echo '.btn-danger, .button-danger, .btn-destructive { background: transparent; color: #f87171; border: 1px solid rgba(248, 113, 113, 0.4); border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.25s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; }';
    echo '.btn-danger:hover, .button-danger:hover, .btn-destructive:hover { background: rgba(248, 113, 113, 0.1); }';
    echo '.btn[disabled], .premium-button:disabled, .btn-primary:disabled, .btn-secondary:disabled, .btn-danger:disabled, .button-danger:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }';
    echo '.premium-admin-header { background: var(--iw-bg-header); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); border-bottom: 1px solid var(--iw-border); backdrop-filter: blur(10px); position: relative; z-index: 100000; }';
    echo '.admin-user-section { display: flex; align-items: center; gap: 16px; position: relative; z-index: 100000; }';
    echo '.user-profile { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: var(--iw-bg-profile); border-radius: 12px; backdrop-filter: blur(10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--iw-border-dropdown); cursor: pointer; position: relative; }';
    echo '.user-profile:hover { background: var(--iw-bg-profile-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2); }';
    echo '.user-avatar { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #51D55A; object-fit: cover; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3); }';
    echo '.user-details { display: flex; flex-direction: column; gap: 2px; }';
    echo '.user-name { font-family: \'Onest\', sans-serif; font-weight: 600; font-size: 14px; color: var(--iw-text-profile-name); }';
    echo '.user-role { font-family: \'Onest\', sans-serif; font-weight: 400; font-size: 12px; color: var(--iw-text-profile-role); }';
    echo '.user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--iw-bg-dropdown); backdrop-filter: blur(20px); border-radius: 12px; box-shadow: var(--iw-shadow-dropdown); border: 1px solid var(--iw-border-dropdown); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 99999; }';
    echo '.user-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); display: block !important; }';
    echo '.dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--iw-text-dropdown); text-decoration: none; font-family: \'Onest\', sans-serif; font-size: 14px; font-weight: 500; transition: all 0.2s ease; border-bottom: 1px solid var(--iw-border-subtle); }';
    echo '.dropdown-item:last-child { border-bottom: none; }';
    echo '.dropdown-item:hover { background: rgba(81, 213, 90, 0.1); color: var(--iw-text-dropdown-title); }';
    echo '.dropdown-item svg { width: 16px; height: 16px; color: var(--iw-text-subtle); }';
    echo '.dropdown-item:hover svg { color: #51D55A; }';
    
    // NOTIFICATION STYLES
    echo '.notification-container { position: relative; display: flex; align-items: center; gap: 16px; z-index: 100000; }';
    echo '.notification-bell { position: relative; width: 40px; height: 40px; background: var(--iw-bg-profile); border: 1px solid var(--iw-border-dropdown); border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px); }';
    echo '.notification-bell:hover { background: var(--iw-bg-profile-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2); }';
    echo '.notification-bell svg { width: 20px; height: 20px; color: #51D55A; transition: all 0.3s ease; }';
    echo '.notification-bell:hover svg { color: #4BC052; transform: scale(1.1); }';
    echo '.notification-badge { position: absolute; top: -4px; right: -4px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; font-family: \'Onest\', sans-serif; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4); animation: pulse 2s infinite; }';
    echo '@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }';
    echo '.notification-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--iw-bg-dropdown); backdrop-filter: blur(20px); border-radius: 12px; box-shadow: var(--iw-shadow-dropdown); border: 1px solid var(--iw-border-dropdown); min-width: 320px; max-width: 400px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 99998; }';
    echo '.notification-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); display: block !important; }';
    echo '.notification-header { padding: 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: center; justify-content: space-between; }';
    echo '.notification-title { font-family: \'Onest\', sans-serif; font-weight: 600; font-size: 16px; color: var(--iw-text-dropdown-title); }';
    echo '.notification-clear { background: none; border: none; color: var(--iw-text-subtle); font-size: 12px; cursor: pointer; transition: color 0.2s ease; }';
    echo '.notification-clear:hover { color: #51D55A; }';
    echo '.notification-list { max-height: 300px; overflow-y: auto; }';
    echo '.notification-item { padding: 12px 16px; border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: flex-start; gap: 12px; transition: background-color 0.2s ease; }';
    echo '.notification-item:hover { background: rgba(81, 213, 90, 0.05); }';
    echo '.notification-item:last-child { border-bottom: none; }';
    echo '.notification-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }';
    echo '.notification-icon.low-stock { background: rgba(239, 68, 68, 0.1); color: #ef4444; }';
    echo '.notification-content { flex: 1; }';
    echo '.notification-message { font-family: \'Onest\', sans-serif; font-size: 14px; color: var(--iw-text-dropdown); margin-bottom: 4px; line-height: 1.4; }';
    echo '.notification-time { font-family: \'Onest\', sans-serif; font-size: 12px; color: var(--iw-text-subtle); }';
    echo '.notification-empty { padding: 32px 16px; text-align: center; color: var(--iw-text-subtle); font-family: \'Onest\', sans-serif; font-size: 14px; }';

    echo '.header-scanner-status { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 12px; font-family: \'Onest\', sans-serif; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px); border: 1px solid transparent; }';
    echo '.header-scanner-status__icon { width: 18px; height: 18px; flex-shrink: 0; }';
    echo '.header-scanner-status__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }';
    echo '.header-scanner-status--ready { color: #51D55A; background: rgba(81, 213, 90, 0.12); border-color: rgba(81, 213, 90, 0.28); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.12); }';
    echo '.header-scanner-status--ready .header-scanner-status__dot { background: #51D55A; box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.18); animation: scannerPulse 2s infinite; }';
    echo '.header-scanner-status--disconnected { color: #fca5a5; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.24); }';
    echo '.header-scanner-status--disconnected .header-scanner-status__dot { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15); }';
    echo '.header-scanner-status--checking { color: #fcd34d; background: rgba(251, 191, 36, 0.1); border-color: rgba(251, 191, 36, 0.24); }';
    echo '.header-scanner-status--checking .header-scanner-status__dot { background: #fbbf24; animation: scannerPulse 1.2s infinite; }';
    echo '@keyframes scannerPulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.15); opacity: 0.75; } 100% { transform: scale(1); opacity: 1; } }';
    echo '@media (max-width: 900px) { .header-scanner-status__text { display: none; } .header-scanner-status { padding: 8px 10px; } }';
    
    echo '.dropdown-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--iw-bg-backdrop); backdrop-filter: blur(2px); z-index: 99990; opacity: 0; visibility: hidden; transition: all 0.3s ease; }';
    echo '.dropdown-backdrop.show { opacity: 1; visibility: visible; }';
    echo '</style>';
    echo '<style id="iw-tailwind-overrides">';
    echo '[data-theme="light"] .header-scanner-status--checking { color: #92400E !important; background: rgba(251, 191, 36, 0.28) !important; border-color: rgba(217, 119, 6, 0.5) !important; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.12) !important; }';
    echo '[data-theme="light"] .header-scanner-status--checking .header-scanner-status__dot { background: #D97706 !important; box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2) !important; }';
    echo '[data-theme="light"] .header-scanner-status--ready { color: #15803D !important; background: rgba(22, 163, 74, 0.18) !important; border-color: rgba(22, 163, 74, 0.45) !important; }';
    echo '[data-theme="light"] .header-scanner-status--ready .header-scanner-status__dot { background: #16A34A !important; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2) !important; }';
    echo '[data-theme="light"] .header-scanner-status--disconnected { color: #B91C1C !important; background: rgba(239, 68, 68, 0.16) !important; border-color: rgba(220, 38, 38, 0.42) !important; }';
    echo '[data-theme="light"] .header-scanner-status--disconnected .header-scanner-status__dot { background: #DC2626 !important; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18) !important; }';
    echo '[data-theme="light"] .hero-forecast-btn { background: linear-gradient(to right, #E8EFF6, #D8E6F0) !important; background-image: linear-gradient(to right, #E8EFF6, #D8E6F0) !important; color: #0F172A !important; border-color: #7A92A8 !important; box-shadow: 0 2px 8px rgba(55, 75, 99, 0.1) !important; --tw-gradient-from: #E8EFF6 !important; --tw-gradient-to: #D8E6F0 !important; }';
    echo '[data-theme="light"] .hero-forecast-btn:hover { background: linear-gradient(to right, #DCE8F2, #CDDFEA) !important; background-image: linear-gradient(to right, #DCE8F2, #CDDFEA) !important; border-color: rgba(22, 163, 74, 0.45) !important; color: #0F172A !important; box-shadow: 0 4px 12px rgba(55, 75, 99, 0.12) !important; }';
    echo '.iw-sidebar-logo-dark { display: block; }';
    echo '.iw-sidebar-logo-light { display: none; }';
    echo '[data-theme="light"] .iw-sidebar-logo-dark { display: none !important; }';
    echo '[data-theme="light"] .iw-sidebar-logo-light { display: block !important; filter: none; opacity: 1; }';
    echo '</style>';
    echo '</head>';
    echo '<body class="w-full h-screen iw-body-shell px-3 pt-3 flex items-start justify-center">';
    echo '<div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden iw-main-wrapper shadow-lg">';
    
    renderSidebar($pageTitle, $userRole);
    
    echo '<div class="flex-1 flex flex-col min-w-0">';
    renderHeader($pageTitle, $userRole, $username);
    echo '<div class="p-6 premium-scrollbar iw-main-content min-w-0" style="flex:1; overflow-y:auto; overflow-x:hidden; position:relative; z-index:1; padding:24px 32px;">';
}

/** Close the scrollable page content area. Output modals AFTER renderLayoutShellEnd() so fixed overlays are not clipped. */
function renderLayoutContentEnd() {
    echo '</div>'; // Close content scroll area
}

function renderLayoutShellEnd() {
    echo '</div>'; // Close flex-1 flex-col (main column)
    echo '</div>'; // Close main container
}

function renderLayoutEnd() {
    renderLayoutShellEnd();
    renderLayoutFooter();
}

function renderLayoutFooter() {
    echo '<div class="dropdown-backdrop" id="dropdownBackdrop"></div>';
    echo '<input type="text" id="global-scanner-wedge-input" class="hid-barcode-capture" autocomplete="off" aria-hidden="true" tabindex="-1">';
    echo '<script src="src/scripts/barcode-scanner.js"></script>';
    
    echo '<script>';
    ?>
    
    function toggleSidebar() {
        const sidebar = document.getElementById("main-sidebar");
        sidebar.classList.toggle("sidebar-hidden");
        // Save preference
        const isHidden = sidebar.classList.contains("sidebar-hidden");
        localStorage.setItem("sidebarHidden", isHidden ? "true" : "false");
    }
    
    // Load sidebar state on start
    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById("main-sidebar");
        const isHidden = localStorage.getItem("sidebarHidden");
        if (isHidden === "true") {
            sidebar.classList.add("sidebar-hidden");
        }
    });

    // --------------------------------------------------------
    // REALTIME NOTIFICATION & DROPDOWN LOGIC
    // --------------------------------------------------------

    const notifDropdown = document.getElementById('notificationDropdown');
    const userDropdown = document.getElementById('userDropdown');
    const backdrop = document.getElementById('dropdownBackdrop');
    const notifList = document.getElementById('notificationList');
    const notifBadge = document.getElementById('notificationBadge');

    // 1. Toggle User Dropdown
    function toggleUserDropdown(e) {
        e.stopPropagation(); 
        if (notifDropdown) notifDropdown.classList.remove('show');
        if (userDropdown) {
            userDropdown.classList.toggle('show');
            toggleBackdrop(userDropdown.classList.contains('show'));
        }
    }

    // 2. Toggle Notification Dropdown
    function toggleNotificationDropdown(e) {
        e.stopPropagation(); 
        if (userDropdown) userDropdown.classList.remove('show');
        
        if (notifDropdown) {
            notifDropdown.classList.toggle('show');
            toggleBackdrop(notifDropdown.classList.contains('show'));
            
            // Fetch data immediately when opening
            if(notifDropdown.classList.contains('show')) {
                fetchNotifications();
            }
        }
    }

    function toggleBackdrop(show) {
        if (show) backdrop.classList.add('show');
        else backdrop.classList.remove('show');
    }

    // 3. FETCH NOTIFICATIONS (REALTIME AJAX)
    async function fetchNotifications() {
        if (!navigator.onLine) {
            if (notifList) {
                notifList.innerHTML = '<div class="notification-empty">Offline — notifications unavailable</div>';
            }
            return;
        }

        try {
            // Call the PHP API
            const response = await fetch('api/get_notifications.php');
            if (!response.ok) {
                throw new Error('Notification request failed');
            }
            const data = await response.json();
            
            // A. Update Badge
            if (notifBadge) {
                if (data.count > 0) {
                    notifBadge.innerText = data.count;
                    notifBadge.style.display = 'flex';
                } else {
                    notifBadge.style.display = 'none';
                }
            }

            // B. Update List
            if (notifList) {
                if (data.count === 0) {
                    notifList.innerHTML = '<div class="notification-empty">Stock levels are healthy.</div>';
                } else {
                    let html = '';
                    data.items.forEach(item => {
                        // CLICKABLE: Redirect to inventory filtered by product name
                        const searchUrl = 'products.php?search=' + encodeURIComponent(item.product);
                        
                        html += `
                        <div class="notification-item" onclick="window.location.href='${searchUrl}'" style="cursor:pointer;">
                            <div class="notification-icon low-stock">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message">${item.message}</div>
                                <div class="notification-time">${item.subtext}</div>
                            </div>
                        </div>`;
                    });
                    notifList.innerHTML = html;
                }
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
            if(notifList) notifList.innerHTML = '<div class="notification-empty" style="color:#ef4444">Connection Error</div>';
        }
    }

    // 4. Close dropdowns when clicking outside
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            if (userDropdown) userDropdown.classList.remove('show');
            if (notifDropdown) notifDropdown.classList.remove('show');
            toggleBackdrop(false);
        });
    }

    // 5. AUTO-POLL: Check for notifications every 5 seconds (skip while offline)
    setInterval(function() {
        if (navigator.onLine) {
            fetchNotifications();
        }
    }, 5000);

    window.addEventListener('online', fetchNotifications);
    
    // Initial fetch on page load
    document.addEventListener('DOMContentLoaded', fetchNotifications);

    <?php
    echo '</script>';
    echo '</body>';
    echo '</html>';
}
?>