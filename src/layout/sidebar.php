<?php
// src/layout.php

function renderSidebar($currentPage, $userRole) {
    // 1. Define the Menu Configuration (ORIGINAL - NO ICONS)
    $menuItems = [
        [
            'href' => ($userRole === 'admin') ? 'dash_admin.php' : (($userRole === 'manager') ? 'dash_manager.php' : 'dash_staff.php'),
            'label' => 'Dashboard',
            'roles' => ['admin', 'manager', 'staff']
        ],
        [
            'href' => 'products.php', 
            'label' => 'Inventory', 
            'roles' => ['admin', 'manager', 'staff']
        ],
        [
            'href' => 'transactions.php', 
            'label' => 'Stock Transactions', 
            'roles' => ['admin', 'manager', 'staff']
        ],
        [
            'href' => 'transaction_logs.php', 
            'label' => 'Transaction Logs', 
            'roles' => ['admin'] 
        ],
        [
            'href' => 'insights.php', 
            'label' => 'Data Mining Insights', 
            'roles' => ['admin', 'manager'] 
        ],
        [
            'href' => 'reports.php', 
            'label' => 'Reports', 
            'roles' => ['admin', 'manager']
        ],
        [
            'href' => 'users.php', 
            'label' => 'User Management', 
            'roles' => ['admin'] 
        ],
        [
            'href' => 'settings.php', 
            'label' => 'Settings', 
            'roles' => ['admin', 'manager', 'staff']
        ]
    ];

    // Added ID 'main-sidebar' and transition class. Kept original 'w-[290px]'
    echo '<div id="main-sidebar" class="premium-sidebar w-[290px] text-white p-3 pt-5 flex flex-col transition-all duration-300 ease-in-out">';
    
    // LOGO
    echo '<div class="ml-2 mb-4">';
    echo '<img src="img/logo.svg" alt="IntelliWare" class="w-[220px]" />';
    echo '</div>';

    // MENU ITEMS
    echo '<div class="p-2 flex flex-col gap-[8px]">';
    
    foreach ($menuItems as $item) {
        if (in_array($userRole, $item['roles'])) {
            $isActive = (basename($_SERVER['PHP_SELF']) === basename($item['href']));
            // Visual active state
            $activeClass = $isActive ? 'bg-white/10 border-white/20 shadow-lg' : ''; 
            
            echo '<a class="premium-menu-item ' . $activeClass . '" href="' . $item['href'] . '">';
            echo $item['label'];
            echo '</a>';
        }
    }
    
    echo '</div>';

    // LOGOUT
    echo '<div class="mt-auto p-2">';
    echo '<a class="premium-button w-full text-center block" href="logout.php">Logout</a>';
    echo '</div>';
    
    echo '</div>'; 
}

function renderHeader($pageTitle, $userRole, $username, $lowStockCount = 0) {
    echo '<div class="premium-admin-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">';
    
    // LEFT SIDE: Toggle Button + Page Title
    echo '<div class="flex items-center gap-4">';
        // Hamburger Toggle Button
        echo '<button onclick="toggleSidebar()" class="text-white/80 hover:text-white transition-colors focus:outline-none">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        echo '</button>';
        
        echo '<div class="font-onest text-[20px] font-semibold text-white">' . htmlspecialchars($pageTitle) . '</div>';
    echo '</div>';

    echo '<div class="admin-user-section">';
    
    echo '<div class="user-profile">';
    $userInitial = strtoupper(substr($username, 0, 1));
    echo '<div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:#51D55A;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;">' . htmlspecialchars($userInitial) . '</div>';
    echo '<div class="user-details">';
    echo '<div class="user-name">' . htmlspecialchars($username) . '</div>';
    echo '<div class="user-role">' . ucfirst($userRole) . '</div>';
    echo '</div>';
    echo '</div>'; 

    echo '</div>'; 
    echo '</div>'; 
}

function renderLayoutStart($pageTitle, $userRole, $username, $lowStockCount = 0) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
    echo '<link rel="icon" type="image/svg+xml" href="img/systemlogo.svg?v=2">';
    echo '<script src="assets/vendor/tailwindcss.js"></script>';
    echo '<link href="assets/css/styles.css" rel="stylesheet" />'; 
    echo '<link href="assets/fonts.css" rel="stylesheet">';
    echo '<title>' . htmlspecialchars($pageTitle) . ' | IntelliWare</title>';
    
    // --- LAYOUT CSS ---
    echo '<style>';
    echo 'body { font-family: \'Inter\', sans-serif; }';
    
    echo '.premium-sidebar { background: linear-gradient(180deg, #1D387B 0%, #13275B 100%); border-right: 1px solid rgba(42, 68, 132, 0.6); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3); }';
    
    echo '.premium-menu-item { border: 1px solid rgba(42, 68, 132, 0.6); color: #E3E3E3; border-radius: 12px; padding: 14px 18px; display: block; text-decoration: none; font-family: \'Onest\', sans-serif; font-weight: 500; transition: all 0.2s ease; }';
    echo '.premium-menu-item:hover { background: linear-gradient(135deg, rgba(19, 39, 91, 0.8) 0%, rgba(29, 56, 123, 0.6) 100%); border-color: rgba(81, 213, 90, 0.3); transform: translateX(4px); }';
    
    echo '.btn, .btn-primary, .premium-button, .button-primary { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); color: #fff; border: 1px solid #51D55A; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; user-select: none; }';
    echo '.premium-button::before { content: \'\'; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: left 0.5s; }';
    echo '.btn:hover, .btn-primary:hover, .premium-button:hover, .button-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3); }';
    echo '.btn-secondary, .button-secondary, .btn-outline { background: rgba(81, 213, 90, 0.12) !important; color: #51D55A !important; border: 1.5px solid rgba(81, 213, 90, 0.45) !important; border-radius: 12px; padding: 10px 22px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(81, 213, 90, 0.1); user-select: none; }';
    echo '.btn-secondary:hover, .button-secondary:hover, .btn-outline:hover { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 16px rgba(81, 213, 90, 0.4) !important; transform: translateY(-1.5px); }';
    echo '.btn-secondary:hover svg, .button-secondary:hover svg, .btn-outline:hover svg { stroke: #ffffff !important; color: #ffffff !important; }';
    echo '.btn-danger, .button-danger, .btn-destructive { background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1.5px solid rgba(239, 68, 68, 0.45); border-radius: 12px; padding: 10px 22px; font-size: 14px; font-weight: 600; font-family: \'Onest\', sans-serif; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2); }';
    echo '.btn-danger:hover, .button-danger:hover, .btn-destructive:hover { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff !important; border-color: #ef4444; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4); transform: translateY(-1.5px); }';
    echo '.btn[disabled], .premium-button:disabled, .btn-primary:disabled, .btn-secondary:disabled, .btn-danger:disabled, .button-danger:disabled { opacity: 0.4 !important; cursor: not-allowed !important; pointer-events: none !important; box-shadow: none !important; }';
    echo '.premium-button:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4); }';
    // Visual Hierarchy: Tier 2 Item Action Buttons
    echo '.action-btn, .check-stock-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 16px; background: rgba(81, 213, 90, 0.12) !important; border: 1.5px solid rgba(81, 213, 90, 0.45) !important; border-radius: 9px; color: #51D55A !important; font-family: \'Onest\', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2); user-select: none; }';
    echo '.action-btn:hover, .check-stock-btn:hover { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4); transform: translateY(-1.5px); }';
    echo '.action-btn:hover svg, .check-stock-btn:hover svg { stroke: #ffffff !important; color: #ffffff !important; }';
    // Visual Hierarchy: Tier 3 Pagination & Navigation Buttons
    echo '.pagination-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; min-width: 38px; padding: 0 14px; background: rgba(19, 39, 91, 0.95) !important; border: 1.5px solid rgba(81, 213, 90, 0.5) !important; border-radius: 10px; color: #51D55A !important; font-family: \'Onest\', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08); text-decoration: none; user-select: none; white-space: nowrap; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }';
    echo '.pagination-btn:hover:not(:disabled):not(.disabled) { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 16px rgba(81, 213, 90, 0.45); transform: translateY(-1.5px); }';
    echo '.pagination-btn:hover:not(:disabled):not(.disabled) svg { stroke: #ffffff !important; }';
    echo '.pagination-btn:active:not(:disabled):not(.disabled) { transform: translateY(1px); box-shadow: 0 1px 4px rgba(81, 213, 90, 0.2); }';
    echo '.pagination-btn.active { background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important; color: #ffffff !important; border-color: #51D55A !important; box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4) !important; pointer-events: none; cursor: default; }';
    echo '.pagination-btn:disabled, .pagination-btn.disabled { opacity: 0.35 !important; cursor: not-allowed !important; pointer-events: none !important; border-color: rgba(42, 68, 132, 0.35) !important; color: #6b7aab !important; background: rgba(19, 39, 91, 0.4) !important; box-shadow: none !important; }';
    echo '.pagination-info-pill { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 10px; background: rgba(19, 39, 91, 0.6); border: 1px solid rgba(42, 68, 132, 0.45); color: #9aa6d1; font-size: 13px; font-weight: 500; font-family: \'Onest\', sans-serif; }';
    
    echo '.premium-admin-header { background: linear-gradient(135deg, #1D387B); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }';
    
    echo '.user-profile { display: flex; align-items: center; gap: 12px; padding: 6px 12px; background: rgba(255, 255, 255, 0.05); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); }';
    echo '.user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #51D55A; }';
    echo '.user-details { display: flex; flex-direction: column; }';
    echo '.user-name { font-family: \'Onest\', sans-serif; font-size: 14px; color: #fff; font-weight: 600; }';
    echo '.user-role { font-family: \'Onest\', sans-serif; font-size: 11px; color: #9aa6d1; }';
    
    echo '::-webkit-scrollbar { width: 8px; }';
    echo '::-webkit-scrollbar-track { background: #020A27; }';
    echo '::-webkit-scrollbar-thumb { background: #1D387B; border-radius: 4px; }';
    echo '::-webkit-scrollbar-thumb:hover { background: #51D55A; }';

    // --- HIDDEN STATE CSS ---
    // When hidden, we use negative margin to slide it off screen
    echo '.sidebar-hidden { margin-left: -290px !important; opacity: 0; }';

    echo '</style>';
    echo '</head>';
    
    echo '<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">';
    echo '<div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-[#020A27] shadow-2xl">';
    
    renderSidebar($pageTitle, $userRole);
    
    echo '<div class="flex-1 flex flex-col h-full">';
    renderHeader($pageTitle, $userRole, $username, $lowStockCount);
    
    echo '<div class="flex-1 overflow-y-auto p-6" style="background:#020A27;">';
}

function renderLayoutEnd() {
    echo '</div>'; // End Content Area
    echo '</div>'; // End Main Flex Column
    echo '</div>'; // End Main Container
    
    // --- JS: Toggle Functionality ---
    echo '<script>';
    echo 'function toggleSidebar() {';
    echo '  const sidebar = document.getElementById("main-sidebar");';
    echo '  sidebar.classList.toggle("sidebar-hidden");';
    echo '  // Save preference';
    echo '  const isHidden = sidebar.classList.contains("sidebar-hidden");';
    echo '  localStorage.setItem("sidebarHidden", isHidden ? "true" : "false");';
    echo '}';
    
    // Load preference on start
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo '  const sidebar = document.getElementById("main-sidebar");';
    echo '  const isHidden = localStorage.getItem("sidebarHidden");';
    echo '  if (isHidden === "true") {';
    echo '      sidebar.classList.add("sidebar-hidden");';
    echo '  }';
    echo '});';
    echo '</script>';

    echo '</body>';
    echo '</html>';
}
?>