<?php
// Shared layout component for consistent design
function renderSidebar($currentPage, $userRole) {
    $menuItems = [];
    
    // Define menu items based on role
    if ($userRole === 'admin') {
        $menuItems = [
            ['href' => 'dash_admin.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'reorder.php', 'label' => 'Set Reorder Point', 'icon' => 'reorder'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'transaction_logs.php', 'label' => 'Transaction Logs', 'icon' => 'logs'],
            ['href' => 'insights.php', 'label' => 'Data Mining Insights', 'icon' => 'insights'],
            ['href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
            ['href' => 'users.php', 'label' => 'User Management', 'icon' => 'users'],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings']
        ];
    } elseif ($userRole === 'manager') {
        $menuItems = [
            ['href' => 'dash_manager.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => 'products.php', 'label' => 'Inventory', 'icon' => 'inventory'],
            ['href' => 'transactions.php', 'label' => 'Stock Transactions', 'icon' => 'transactions'],
            ['href' => 'insights.php', 'label' => 'Data Mining Insights', 'icon' => 'insights'],
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
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>',
        'inventory' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>',
        'reorder' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>',
        'logs' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
        'insights' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
        'reports' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>'
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
        echo '<svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo $icons[$item['icon']];
        echo '</svg>';
        echo $item['label'];
        echo '</a>';
    }
    
    echo '</div>';
    echo '<div class="mt-auto p-2"><a class="premium-button" href="logout.php">Logout</a></div>';
    echo '</div>';
}

function renderHeader($pageTitle, $userRole, $username) {
    echo '<div class="premium-admin-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">';
    echo '<div class="font-onest text-[20px] font-semibold text-gray-800">' . htmlspecialchars($pageTitle) . '</div>';
    echo '<div class="admin-user-section">';
    echo '<div class="user-profile" onclick="toggleUserDropdown()">';
    echo '<img src="https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=51D55A&color=fff&size=40&bold=true" alt="User Avatar" class="user-avatar" />';
    echo '<div class="user-details">';
    echo '<div class="user-name">' . htmlspecialchars($username) . '</div>';
    echo '<div class="user-role">' . ucfirst($userRole) . '</div>';
    echo '</div>';
    echo '<div class="user-dropdown" id="userDropdown">';
    echo '<a href="settings.php" class="dropdown-item">';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
    echo 'Settings</a>';
    echo '<a href="logout.php" class="dropdown-item">';
    echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>';
    echo 'Logout</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function renderLayoutStart($pageTitle, $userRole, $username) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link href="styles.css" rel="stylesheet" />';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<title>' . htmlspecialchars($pageTitle) . ' | IntelliWare</title>';
    echo '<style>';
    echo 'body { font-family: \'Inter\', sans-serif; }';
    echo '.premium-sidebar { background: linear-gradient(180deg, #1D387B 0%, #13275B 100%); border-right: 1px solid rgba(42, 68, 132, 0.6); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3), inset -1px 0 0 rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }';
    echo '.premium-menu-item { border: 1px solid rgba(42, 68, 132, 0.6); color: #E3E3E3; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; text-decoration: none; font-family: \'Onest\', sans-serif; font-weight: 500; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }';
    echo '.premium-menu-item:hover { background: linear-gradient(135deg, rgba(19, 39, 91, 0.8) 0%, rgba(29, 56, 123, 0.6) 100%); border-color: rgba(81, 213, 90, 0.3); transform: translateX(4px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }';
    echo '.premium-menu-item.active { background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%); border-color: rgba(81, 213, 90, 0.4); }';
    echo '.premium-button { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); color: #fff; border: none; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2); text-decoration: none; display: inline-block; }';
    echo '.premium-button:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3); }';
    echo '.premium-admin-header { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1), 0 1px 0 rgba(255, 255, 255, 0.8); border-bottom: 1px solid rgba(0, 0, 0, 0.05); backdrop-filter: blur(10px); position: relative; z-index: 100000; }';
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
    echo '.dropdown-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.1); backdrop-filter: blur(2px); z-index: 99990; opacity: 0; visibility: hidden; transition: all 0.3s ease; }';
    echo '.dropdown-backdrop.show { opacity: 1; visibility: visible; }';
    echo '</style>';
    echo '</head>';
    echo '<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">';
    echo '<div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">';
    
    renderSidebar($pageTitle, $userRole);
    
    echo '<div class="flex-1 flex flex-col">';
    renderHeader($pageTitle, $userRole, $username);
    echo '<div class="p-6 premium-scrollbar" style="background:#0b1438; flex:1; overflow-y:auto; overflow-x:hidden; position:relative; z-index:1; padding:24px 32px;">';
}

function renderLayoutEnd() {
    echo '</div>'; // Close content area
    echo '</div>'; // Close main content
    echo '</div>'; // Close main container
    
    echo '<!-- Dropdown Backdrop -->';
    echo '<div class="dropdown-backdrop" id="dropdownBackdrop"></div>';
    
    echo '<script>';
    echo 'function toggleUserDropdown() {';
    echo 'const dropdown = document.getElementById(\'userDropdown\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'dropdown.classList.toggle(\'show\');';
    echo 'if (dropdown.classList.contains(\'show\')) {';
    echo 'backdrop.classList.add(\'show\');';
    echo '} else {';
    echo 'backdrop.classList.remove(\'show\');';
    echo '}';
    echo '}';
    echo 'document.addEventListener(\'click\', function(event) {';
    echo 'const userDropdown = document.getElementById(\'userDropdown\');';
    echo 'const userProfile = document.querySelector(\'.user-profile\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'if (!userProfile.contains(event.target)) {';
    echo 'userDropdown.classList.remove(\'show\');';
    echo '}';
    echo 'if (!userDropdown.classList.contains(\'show\')) {';
    echo 'backdrop.classList.remove(\'show\');';
    echo '}';
    echo '});';
    echo 'document.getElementById(\'dropdownBackdrop\').addEventListener(\'click\', function() {';
    echo 'const userDropdown = document.getElementById(\'userDropdown\');';
    echo 'const backdrop = document.getElementById(\'dropdownBackdrop\');';
    echo 'userDropdown.classList.remove(\'show\');';
    echo 'backdrop.classList.remove(\'show\');';
    echo '});';
    echo '</script>';
    echo '</body>';
    echo '</html>';
}
?>
