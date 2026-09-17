<?php
// File: src/layout.php

if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

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
    $dashHref = ($userRole === 'admin') ? 'dash_admin.php' : (($userRole === 'manager') ? 'dash_manager.php' : 'dash_staff.php');

    $icons = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1.5"></rect><rect width="7" height="5" x="14" y="3" rx="1.5"></rect><rect width="7" height="9" x="14" y="12" rx="1.5"></rect><rect width="7" height="5" x="3" y="16" rx="1.5"></rect>',
        'inventory' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
        'insights' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />',
        'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />',
        'history' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'logs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
        'reports' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87" /><path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>'
    ];

    $currentScript = basename($_SERVER['PHP_SELF']);
    $pageLower = strtolower($currentPage);

    $isItemActive = function($item) use ($currentScript, $pageLower) {
        if ($currentScript === basename($item['href'])) return true;
        if (!empty($item['aliases'])) {
            foreach ($item['aliases'] as $alias) {
                if ($currentScript === $alias) return true;
            }
        }
        $lbl = strtolower($item['label']);
        if ($lbl === $pageLower) return true;
        if ($item['label'] === 'Dashboard' && strpos($currentScript, 'dash_') === 0) return true;
        if ($item['label'] === 'Receive & release' && in_array($pageLower, ['stock transactions', 'transactions', 'release inventory', 'receive inventory'])) return true;
        if ($item['label'] === 'History' && in_array($pageLower, ['transaction history', 'inventory history'])) return true;
        if ($item['label'] === 'Audit log' && in_array($pageLower, ['transaction logs', 'audit log', 'audit logs'])) return true;
        if ($item['label'] === 'Users' && in_array($pageLower, ['user management', 'users'])) return true;
        return false;
    };

    $renderMenuItem = function($item, $isSubItem = false) use ($isItemActive, $icons) {
        $isActive = $isItemActive($item);
        $activeClass = $isActive ? 'active' : '';
        $iconClass = $isSubItem ? 'w-4 h-4 mr-2.5' : 'w-5 h-5 mr-3';
        echo '<a class="premium-menu-item ' . $activeClass . '" href="' . $item['href'] . '">';
        echo '<svg class="sidebar-icon ' . $iconClass . '" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">';
        echo $icons[$item['icon']];
        echo '</svg>';
        echo '<span class="sidebar-text">' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
    };

    echo '<div id="main-sidebar" class="premium-sidebar w-[275px] shrink-0 text-white p-3 pt-5 flex flex-col transition-all duration-300 ease-in-out select-none">';
    echo '<div class="ml-2 mb-4">';
    echo '<img src="img/iLogo.png" alt="IntelliWare" class="w-[210px] iw-sidebar-logo iw-sidebar-logo-dark" style="object-fit: contain; max-height: 48px;" />';
    echo '<img src="img/darkmode.png" alt="IntelliWare" class="w-[210px] iw-sidebar-logo iw-sidebar-logo-light" style="object-fit: contain; max-height: 48px;" />';
    echo '</div>';
    echo '<div class="p-1.5 flex flex-col gap-[4px] overflow-y-auto premium-scrollbar" style="flex: 1 1 auto; min-height: 0;">';

    // 1. Top Section
    $renderMenuItem(['href' => $dashHref, 'label' => 'Dashboard', 'icon' => 'dashboard']);
    $renderMenuItem(['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory']);
    if ($userRole === 'admin' || $userRole === 'manager') {
        $renderMenuItem(['href' => 'insights.php', 'label' => 'Insights', 'icon' => 'insights']);
    }

    // 2. Transactions Group
    echo '<div class="sidebar-section-title">Transactions</div>';
    echo '<div class="sidebar-tree-branch">';
    $renderMenuItem(['href' => 'transactions.php', 'label' => 'Receive & release', 'icon' => 'transactions', 'aliases' => ['release.php', 'receive.php']], true);
    $renderMenuItem(['href' => 'inventory_history.php', 'label' => 'History', 'icon' => 'history'], true);
    if ($userRole === 'admin' || $userRole === 'manager') {
        $renderMenuItem(['href' => 'transaction_logs.php', 'label' => 'Audit log', 'icon' => 'logs'], true);
    }
    echo '</div>';

    // 3. Admin Section
    if ($userRole === 'admin' || $userRole === 'manager') {
        echo '<div class="sidebar-section-title">Admin</div>';
        echo '<div class="flex flex-col gap-[4px]">';
        $renderMenuItem(['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports']);
        if ($userRole === 'admin') {
            $renderMenuItem(['href' => 'users.php', 'label' => 'Users', 'icon' => 'users']);
        }
        $renderMenuItem(['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']);
        echo '</div>';
    } else {
        // Staff settings
        echo '<div class="mt-2">';
        $renderMenuItem(['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']);
        echo '</div>';
    }

    echo '</div>';

    echo '<div class="mt-auto p-2 pt-3 border-t border-[#2A4484]/40">';
    echo '<a class="premium-menu-item premium-menu-item-logout" href="logout.php" style="justify-content: center;">';
    echo '<svg class="sidebar-icon w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>';
    echo '</svg>';
    echo '<span class="sidebar-text">Logout</span>';
    echo '</a>';
    echo '</div>';

    echo '</div>';
}

function renderHeader($pageTitle, $userRole, $username) {
    $displayName = trim($_SESSION['full_name'] ?? '') ?: $username;

    echo '<div class="premium-admin-header px-4 md:px-10 lg:px-[50px] py-[14px] lg:py-[20px] h-auto lg:h-[67px] flex justify-between items-center">';

    
    // UPDATED: Added Flex container for Toggle Button + Page Title
    echo '<div class="flex items-center gap-4">';
        // HAMBURGER TOGGLE BUTTON
        echo '<button onclick="toggleSidebar()" class="iw-sidebar-toggle transition-colors focus:outline-none">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        echo '</button>';

        echo '<div class="font-onest text-[20px] font-semibold iw-header-title">' . htmlspecialchars($pageTitle) . '</div>';
    echo '</div>';

    echo '<div class="admin-user-section">';



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
    echo '<link rel="icon" type="image/svg+xml" href="img/systemlogo.svg?v=2">';
    echo '<script>(function(){var t=localStorage.getItem("iw-theme")||"dark";document.documentElement.setAttribute("data-theme",t);})();</script>';
    $cssVerStyles = file_exists(__DIR__ . '/../styles.css') ? filemtime(__DIR__ . '/../styles.css') : time();
    $cssVerTheme  = file_exists(__DIR__ . '/../theme.css') ? filemtime(__DIR__ . '/../theme.css') : time();
    $cssVerMobile = file_exists(__DIR__ . '/../mobile.css') ? filemtime(__DIR__ . '/../mobile.css') : time();
    echo '<link href="styles.css?v=' . $cssVerStyles . '" rel="stylesheet" />';
    echo '<link href="theme.css?v=' . $cssVerTheme . '" rel="stylesheet" />';
    echo '<link href="mobile.css?v=' . $cssVerMobile . '" rel="stylesheet" />';
    echo '<script src="theme.js" defer></script>';
    echo '<script src="https://cdn.tailwindcss.com" onerror="document.documentElement.classList.add(\'iw-no-tailwind\')"></script>';

    if (basename($_SERVER['PHP_SELF']) === 'dash_admin.php') {
        $cssVerAdmin = file_exists(__DIR__ . '/../admin_dashboard.css') ? filemtime(__DIR__ . '/../admin_dashboard.css') : time();
        echo '<link href="admin_dashboard.css?v=' . $cssVerAdmin . '" rel="stylesheet">';
    }

    echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'">';
    echo '<noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>';
    echo '<title>' . htmlspecialchars($pageTitle) . ' | IntelliWare</title>';
    echo '<style>';
    echo 'body { font-family: \'Inter\', sans-serif; }';
    echo '.premium-menu-item { border: 1px solid transparent; color: #8BA2C4; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; text-decoration: none; font-family: \'Onest\', sans-serif; font-weight: 500; font-size: 14px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }';
    echo '.premium-menu-item:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }';
    echo '.premium-menu-item:hover .sidebar-icon { color: #FFFFFF; stroke: #FFFFFF; }';
    echo '.premium-menu-item-logout:hover { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.3) 100%); border-color: rgba(239, 68, 68, 0.4); color: #fca5a5; }';
    echo '.premium-menu-item-logout:hover svg { color: #fca5a5; }';
    echo '.premium-menu-item.active { background: #2563EB !important; border-color: #3B82F6 !important; color: #FFFFFF !important; font-weight: 600 !important; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.38) !important; transform: none !important; }';
    echo '.premium-menu-item.active .sidebar-icon, .premium-menu-item.active .sidebar-text { color: #FFFFFF !important; stroke: #FFFFFF !important; }';
    echo '.sidebar-icon { width: 18px; height: 18px; margin-right: 10px; flex-shrink: 0; }';
    echo '.sidebar-text { font-size: 14px; }';
    echo '.sidebar-section-title { font-size: 11.5px; font-weight: 600; color: #6882a9; letter-spacing: 0.03em; padding: 12px 14px 4px 14px; user-select: none; font-family: \'Onest\', sans-serif; }';
    echo '.sidebar-tree-branch { margin-left: 18px; padding-left: 10px; border-left: 1.5px solid rgba(42, 68, 132, 0.55); display: flex; flex-direction: column; gap: 4px; margin-top: 3px; margin-bottom: 4px; }';
    echo '.sidebar-tree-branch .premium-menu-item { padding: 8px 12px; font-size: 13.5px; border-radius: 8px; }';
    echo '.sidebar-tree-branch .sidebar-icon { width: 16px; height: 16px; margin-right: 8px; }';
    echo '.btn, .btn-primary, .premium-button, .button-primary { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); color: #fff; border: 1px solid #51D55A; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; user-select: none; }';
    echo '.btn::before, .button-primary::before, .premium-button::before { content: \'\'; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: left 0.5s; }';
    echo '.btn:hover, .btn-primary:hover, .premium-button:hover, .button-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3); }';
    echo '.btn:hover::before, .btn-primary:hover::before, .premium-button:hover::before, .button-primary:hover::before { left: 100%; }';
    echo '.btn:active, .btn-primary:active, .button-primary:active, .premium-button:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1); }';
    // Light mode: solid flat green (no gradient) for primary buttons
    echo '[data-theme="light"] .btn, [data-theme="light"] .btn-primary, [data-theme="light"] .premium-button, [data-theme="light"] .button-primary { background: #1B8F3E !important; color: #FFFFFF !important; border-color: #157832 !important; box-shadow: 0 4px 12px -4px rgba(27, 143, 62, 0.45) !important; }';
    echo '[data-theme="light"] .btn:hover, [data-theme="light"] .btn-primary:hover, [data-theme="light"] .premium-button:hover, [data-theme="light"] .button-primary:hover { background: #157832 !important; box-shadow: 0 6px 16px -4px rgba(27, 143, 62, 0.55) !important; }';
    echo '.btn-secondary, .button-secondary, .btn-outline { background: rgba(81, 213, 90, 0.12) !important; color: #51D55A !important; border: 1.5px solid rgba(81, 213, 90, 0.45) !important; border-radius: 12px; padding: 10px 22px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(81, 213, 90, 0.1); user-select: none; }';
    echo '.btn-secondary:hover, .button-secondary:hover, .btn-outline:hover { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 16px rgba(81, 213, 90, 0.4) !important; transform: translateY(-1.5px); }';
    echo '.btn-secondary:hover svg, .button-secondary:hover svg, .btn-outline:hover svg { stroke: #ffffff !important; color: #ffffff !important; }';
    echo '.btn-danger, .button-danger, .btn-destructive { background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1.5px solid rgba(239, 68, 68, 0.45); border-radius: 12px; padding: 10px 22px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2); }';
    echo '.btn-danger:hover, .button-danger:hover, .btn-destructive:hover { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff !important; border-color: #ef4444; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4); transform: translateY(-1.5px); }';
    echo '.btn[disabled], .premium-button:disabled, .btn-primary:disabled, .btn-secondary:disabled, .btn-danger:disabled, .button-danger:disabled { opacity: 0.4 !important; cursor: not-allowed !important; pointer-events: none !important; box-shadow: none !important; }';
    // Visual Hierarchy: Tier 2 Item Action Buttons
    echo '.action-btn, .check-stock-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 16px; background: rgba(81, 213, 90, 0.12) !important; border: 1.5px solid rgba(81, 213, 90, 0.45) !important; border-radius: 9px; color: #51D55A !important; font-family: \'Onest\', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2); user-select: none; }';
    echo '.action-btn:hover, .check-stock-btn:hover { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4); transform: translateY(-1.5px); }';
    echo '.action-btn:hover svg, .check-stock-btn:hover svg { stroke: #ffffff !important; color: #ffffff !important; }';
    // Visual Hierarchy: Tier 3 Pagination & Navigation Buttons
    echo '.pagination-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; min-width: 38px; padding: 0 12px; background: rgba(19, 39, 91, 0.95) !important; border: 1.5px solid rgba(81, 213, 90, 0.5) !important; border-radius: 10px; color: #51D55A !important; font-family: \'Onest\', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08); text-decoration: none; user-select: none; white-space: nowrap; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }';
    echo '.pagination-btn.pagination-num { min-width: 38px !important; width: 38px !important; height: 38px !important; padding: 0 !important; }';
    echo '.pagination-btn.pagination-nav { height: 38px !important; min-width: 86px !important; padding: 0 16px !important; }';
    echo '.pagination-btn:hover:not(:disabled):not(.disabled) { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 16px rgba(81, 213, 90, 0.45); transform: translateY(-1.5px); }';
    echo '.pagination-btn:hover:not(:disabled):not(.disabled) svg { stroke: #ffffff !important; }';
    echo '.pagination-btn:active:not(:disabled):not(.disabled) { transform: translateY(1px); box-shadow: 0 1px 4px rgba(81, 213, 90, 0.2); }';
    echo '.pagination-btn.active, .pagination-btn.active:disabled { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4) !important; opacity: 1 !important; pointer-events: none !important; cursor: default !important; font-weight: 800 !important; }';
    echo '.pagination-btn:disabled:not(.active), .pagination-btn.disabled:not(.active) { opacity: 0.35 !important; cursor: not-allowed !important; pointer-events: none !important; border-color: rgba(42, 68, 132, 0.35) !important; color: #6b7aab !important; background: rgba(19, 39, 91, 0.4) !important; box-shadow: none !important; }';
    echo '.pagination-info-pill { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 10px; background: rgba(19, 39, 91, 0.6); border: 1px solid rgba(42, 68, 132, 0.45); color: #9aa6d1; font-size: 13px; font-weight: 500; font-family: \'Onest\', sans-serif; }';
    echo '.modal-close-btn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; background: rgba(19, 39, 91, 0.85); border: 1.5px solid rgba(81, 213, 90, 0.4); color: #51D55A; font-size: 22px; line-height: 1; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 6px rgba(0,0,0,0.2); user-select: none; }';
    echo '.modal-close-btn:hover { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff !important; border-color: #ef4444; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.45); transform: translateY(-1px); }';
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
    
    echo '.dropdown-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--iw-bg-backdrop); backdrop-filter: blur(2px); z-index: 99990; opacity: 0; visibility: hidden; transition: all 0.3s ease; }';
    echo '.dropdown-backdrop.show { opacity: 1; visibility: visible; }';
    echo '</style>';
    echo '<style id="iw-tailwind-overrides">';
    /* Hero eyebrow spans */
    echo '[data-theme="light"] .hero-brand { color: #14509E !important; font-weight: 700; }';
    echo '[data-theme="light"] .hero-dot { color: #94A3B8 !important; }';
    echo '[data-theme="light"] .hero-ccenter { color: #0F6E2C !important; font-weight: 700; }';
    echo '[data-theme="light"] .hero-eyebrow { text-transform: none !important; letter-spacing: 0 !important; font-size: 11px !important; }';
    /* Hero card greeting */
    echo '[data-theme="light"] .premium-card h2.font-bold.text-white { color: #0B1526 !important; }';
    echo '[data-theme="light"] .dashboard-greeting-title { color: #0B1526 !important; }';
    echo '[data-theme="light"] .dashboard-username { color: #0F6E2C !important; }';
    /* Role badge */
    echo '[data-theme="light"] .dashboard-role-badge, [data-theme="light"] .premium-card .bg-blue-500\/15.text-blue-300 { background: #DCEAFC !important; color: #14509E !important; border-color: #AECCF2 !important; }';
    /* Hero subtext */
    echo '[data-theme="light"] .dashboard-subtext, [data-theme="light"] .premium-card p.text-\\[\\#9aa6d1\\] { color: #45526B !important; }';
    /* Status summary numbers via Tailwind class prose */
    echo '[data-theme="light"] .premium-card p strong { color: #0B1526 !important; }';
    /* Primary action button (View full forecast) — spec green */
    echo '[data-theme="light"] .premium-button { background: linear-gradient(180deg, #22A34A 0%, #0F6E2C 100%) !important; color: #FFFFFF !important; border-color: #0F6E2C !important; box-shadow: 0 8px 18px -8px rgba(15,110,44,0.5) !important; }';
    echo '[data-theme="light"] .premium-button:hover { background: linear-gradient(180deg, #1D9442 0%, #0D6028 100%) !important; }';
    /* Also override hero-forecast-btn if used elsewhere */
    echo '[data-theme="light"] .hero-forecast-btn { background: linear-gradient(180deg, #22A34A 0%, #0F6E2C 100%) !important; color: #FFFFFF !important; border-color: #0F6E2C !important; box-shadow: 0 8px 18px -8px rgba(15,110,44,0.5) !important; }';
    echo '[data-theme="light"] .hero-forecast-btn:hover { background: linear-gradient(180deg, #1D9442 0%, #0D6028 100%) !important; border-color: #0D6028 !important; }';
    /* Secondary button (Add product) — flat pale green */
    echo '[data-theme="light"] .btn-secondary { background: #E3F5E8 !important; color: #0F6E2C !important; border: 1.5px solid #A9DFB8 !important; box-shadow: none !important; }';
    echo '[data-theme="light"] .btn-secondary svg { stroke: #0F6E2C !important; color: #0F6E2C !important; }';
    echo '.iw-sidebar-logo-dark { display: block; }';
    echo '.iw-sidebar-logo-light { display: none; }';
    echo '[data-theme="light"] .iw-sidebar-logo-dark { display: none !important; }';
    echo '[data-theme="light"] .iw-sidebar-logo-light { display: block !important; filter: none; opacity: 1; }';
    echo '[data-theme="light"] .sidebar-section-title { color: #64748B !important; }';
    echo '[data-theme="light"] .sidebar-tree-branch { border-left-color: #CBD5E1 !important; }';
    echo '[data-theme="light"] .premium-sidebar .premium-menu-item { color: #475569 !important; border-color: transparent !important; }';
    echo '[data-theme="light"] .premium-sidebar .premium-menu-item:hover { background: #EDF4FF !important; color: #14509E !important; }';
    echo '[data-theme="light"] .premium-sidebar .premium-menu-item:hover .sidebar-icon { color: #14509E !important; stroke: #14509E !important; }';
    echo '[data-theme="light"] .premium-menu-item.active { background: #2563EB !important; border-color: #2563EB !important; color: #FFFFFF !important; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28) !important; }';
    echo '[data-theme="light"] .premium-menu-item.active .sidebar-icon, [data-theme="light"] .premium-menu-item.active .sidebar-text { color: #FFFFFF !important; stroke: #FFFFFF !important; }';
    echo '</style>';
    echo '</head>';
    echo '<body class="w-full h-screen iw-body-shell flex items-start justify-center">';
    // Mobile sidebar overlay backdrop
    echo '<div id="sidebar-overlay" onclick="closeSidebar()"></div>';
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
    echo '<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>';

    echo '<script>';
    ?>

    window.showToast = function(message, type) {
        var toast = document.getElementById('toast-container');
        var toastMsg = document.getElementById('toast-message');
        var toastIcon = document.getElementById('toast-icon');
        if (!toast || !toastMsg) return;
        toastMsg.innerText = message;
        type = (type === 'success') ? 'success' : 'error';
        toast.className = 'show ' + type;
        if (toastIcon) {
            if (type === 'success') {
                toastIcon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            } else {
                toastIcon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
            }
        }
        clearTimeout(window.__toastTimer);
        window.__toastTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 4000);
    };
    
    function isOverlayMode() {
        return window.innerWidth <= 1024;
    }

    function isMobile() {
        return window.innerWidth <= 1024;
    }

    function openSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.remove('sidebar-hidden');
        if (overlay && isOverlayMode()) {
            overlay.classList.add('active');
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.add('sidebar-hidden');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const isHidden = sidebar.classList.contains('sidebar-hidden');
        if (isHidden) {
            openSidebar();
        } else {
            closeSidebar();
        }
        if (!isOverlayMode()) {
            // Persist preference on desktop only (sidebar pushes content there)
            localStorage.setItem('sidebarHidden', sidebar.classList.contains('sidebar-hidden') ? 'true' : 'false');
        }
    }
    
    // Load sidebar state on start
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (isOverlayMode()) {
            // Mobile & tablet: always start closed — sidebar is an overlay drawer
            sidebar.classList.add('sidebar-hidden');
            if (overlay) overlay.classList.remove('active');
        } else {
            // Desktop: restore saved preference
            const isHidden = localStorage.getItem('sidebarHidden');
            if (isHidden === 'true') {
                sidebar.classList.add('sidebar-hidden');
            }
        }
    });

    // Handle resize across breakpoints
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!isOverlayMode()) {
            // Entered desktop: remove overlay, restore desktop preference
            if (overlay) {
                overlay.classList.remove('active');
            }
            const isHidden = localStorage.getItem('sidebarHidden');
            if (isHidden === 'true') {
                sidebar.classList.add('sidebar-hidden');
            } else {
                sidebar.classList.remove('sidebar-hidden');
            }
        } else {
            // Entered mobile/tablet overlay mode: close sidebar, hide overlay
            sidebar.classList.add('sidebar-hidden');
            if (overlay) {
                overlay.classList.remove('active');
            }
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
                        const searchUrl = 'products.php?search=' + encodeURIComponent(item.product);
                        const isOut = (item.type === 'out_of_stock' || item.qty === 0);

                        const iconHtml = isOut
                            ? `<div class="notification-icon out-of-stock" style="width:34px; height:34px; border-radius:50%; background:rgba(239, 68, 68, 0.18); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.4); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                               </div>`
                            : `<div class="notification-icon low-stock" style="width:34px; height:34px; border-radius:50%; background:rgba(245, 158, 11, 0.18); color:#fbbf24; border:1px solid rgba(245, 158, 11, 0.35); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                               </div>`;

                        const badgeHtml = isOut
                            ? `<span style="display:inline-block; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; background:#ef4444; color:#ffffff; padding:2px 6px; border-radius:4px; margin-bottom:4px; font-family:'Onest',sans-serif;">OUT OF STOCK</span>`
                            : `<span style="display:inline-block; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; background:rgba(245, 158, 11, 0.22); color:#fbbf24; border:1px solid rgba(245, 158, 11, 0.4); padding:2px 6px; border-radius:4px; margin-bottom:4px; font-family:'Onest',sans-serif;">LOW STOCK</span>`;

                        html += `
                        <div class="notification-item ${isOut ? 'notif-item-critical' : ''}" onclick="window.location.href='${searchUrl}'" style="cursor:pointer; display:flex; gap:12px; align-items:flex-start; padding:12px 14px; border-bottom:1px solid rgba(42, 68, 132, 0.25); transition:background 0.15s; background:${isOut ? 'rgba(239, 68, 68, 0.04)' : 'transparent'};">
                            ${iconHtml}
                            <div class="notification-content" style="flex:1; min-width:0;">
                                <div>${badgeHtml}</div>
                                <div class="notification-message" style="font-size:12.5px; line-height:1.4; color:#ffffff;">${item.message}</div>
                                <div class="notification-time" style="font-size:11px; color:#9aa6d1; margin-top:3px;">${item.subtext}</div>
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

    // Auto-wrap tables in scrollable containers on narrow screens
    // AND stamp data-label on every td for the mobile card layout (≤767px)
    document.addEventListener('DOMContentLoaded', function() {
        const isMobileCard = window.innerWidth <= 767;
        const isTablet = window.innerWidth < 1024;
        const content = document.querySelector('.iw-main-content');
        if (!content) return;

        content.querySelectorAll('table.premium-table, table.table').forEach(function(tbl) {
            // Wrap in iw-table-scroll if not already wrapped
            if (isTablet && tbl.parentElement && !tbl.parentElement.classList.contains('iw-table-scroll')) {
                var wrapper = document.createElement('div');
                wrapper.className = 'iw-table-scroll';
                tbl.parentNode.insertBefore(wrapper, tbl);
                wrapper.appendChild(tbl);
            }

            // On mobile (≤767px): stamp each td with data-label from thead th
            if (isMobileCard) {
                var headers = [];
                tbl.querySelectorAll('thead th').forEach(function(th) {
                    headers.push(th.textContent.trim());
                });
                tbl.querySelectorAll('tbody tr').forEach(function(row) {
                    row.querySelectorAll('td').forEach(function(td, idx) {
                        if (headers[idx] && !td.getAttribute('data-label')) {
                            td.setAttribute('data-label', headers[idx]);
                        }
                    });
                });
            }
        });

        // Also handle .overflow-x-auto > table.premium-table (transactions.php, inventory_history.php)
        if (isMobileCard) {
            document.querySelectorAll('.overflow-x-auto table.premium-table, .overflow-x-auto table.table').forEach(function(tbl) {
                var headers = [];
                tbl.querySelectorAll('thead th').forEach(function(th) {
                    headers.push(th.textContent.trim());
                });
                tbl.querySelectorAll('tbody tr').forEach(function(row) {
                    row.querySelectorAll('td').forEach(function(td, idx) {
                        if (headers[idx] && !td.getAttribute('data-label')) {
                            td.setAttribute('data-label', headers[idx]);
                        }
                    });
                });
            });

            // #mainLogTable (Transaction Logs — Full Transaction History section)
            var mainLog = document.getElementById('mainLogTable');
            if (mainLog) {
                var logHeaders = [];
                mainLog.querySelectorAll('thead th').forEach(function(th) {
                    logHeaders.push(th.textContent.trim());
                });
                mainLog.querySelectorAll('tbody tr').forEach(function(row) {
                    row.querySelectorAll('td').forEach(function(td, idx) {
                        if (logHeaders[idx] && !td.getAttribute('data-label')) {
                            td.setAttribute('data-label', logHeaders[idx]);
                        }
                    });
                });
            }

            // Tag <body> with current page slug so CSS can scope page-specific rules
            var page = document.location.pathname.split('/').pop().replace('.php', '') || 'index';
            document.body.classList.add('iw-page-' + page);

            // Users page: prevent .overflow-x-auto inside .premium-card from going card-mode
            // (the users Active Accounts table should scroll, not stack as cards)
            if (page === 'users') {
                document.querySelectorAll('.premium-card .overflow-x-auto').forEach(function(wrap) {
                    wrap.classList.add('iw-users-table-wrap');
                });
            }
        }

        // Sidebar: close drawer when a nav item is tapped on mobile
        if (isMobileCard) {
            var sidebar = document.getElementById('main-sidebar');
            if (sidebar) {
                sidebar.querySelectorAll('.premium-menu-item').forEach(function(link) {
                    link.addEventListener('click', function() {
                        closeSidebar();
                    });
                });
            }
        }
    });
    
    // Initial fetch on page load
    document.addEventListener('DOMContentLoaded', fetchNotifications);

    <?php
    $queuedFlash = null;
    $queuedType = 'success';
    if (!empty($_SESSION['flash'])) {
        $queuedFlash = $_SESSION['flash']['message'] ?? '';
        $queuedType = $_SESSION['flash']['type'] ?? 'success';
        unset($_SESSION['flash']);
    } elseif (!empty($_SESSION['toast'])) {
        $queuedFlash = $_SESSION['toast']['message'] ?? '';
        $queuedType = $_SESSION['toast']['type'] ?? 'success';
        unset($_SESSION['toast']);
    } elseif (!empty($_SESSION['success'])) {
        $queuedFlash = $_SESSION['success'];
        $queuedType = 'success';
        unset($_SESSION['success']);
    } elseif (!empty($_SESSION['error'])) {
        $queuedFlash = $_SESSION['error'];
        $queuedType = 'error';
        unset($_SESSION['error']);
    }

    if ($queuedFlash): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast(<?php echo json_encode($queuedFlash); ?>, <?php echo json_encode($queuedType); ?>);
    });
    <?php endif; ?>

    <?php
    echo '</script>';
    echo '</body>';
    echo '</html>';
}
?>