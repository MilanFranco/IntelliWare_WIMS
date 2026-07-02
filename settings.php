<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$currentUser = $_SESSION['Username'];
$currentRole = $_SESSION['Role'] ?? 'staff';

require_once 'src/layout.php';
require_once 'config/db.php';

// 2. --- Handle Form Submissions (LOGIC PRESERVED) ---

// A. Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['new_username']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newUsername)) {
        $_SESSION['error'] = "Username cannot be empty.";
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $_SESSION['error'] = "New passwords do not match.";
    } else {
        try {
            if ($newUsername !== $currentUser) {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $newUsername);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) throw new Exception("Username taken.");
                $stmt->close();
            }

            if (!empty($newPassword)) {
                $hashedPwd = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE username = ?");
                $stmt->bind_param("sss", $newUsername, $hashedPwd, $currentUser);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
                $stmt->bind_param("ss", $newUsername, $currentUser);
            }

            if ($stmt->execute()) {
                $_SESSION['Username'] = $newUsername;
                $_SESSION['success'] = "Profile credentials updated successfully.";
            } else {
                throw new Exception("Database update failed.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }
    header('Location: settings.php');
    exit();
}

// B. Handle System Defaults
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_defaults'])) {
    $currency = $_POST['currency_symbol'];
    $vat = floatval($_POST['vat_rate']);
    $_SESSION['success'] = "System defaults updated: Currency ($currency), VAT ($vat%).";
    header('Location: settings.php');
    exit();
}

// C. Handle Notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $emailAlerts = isset($_POST['alert_email']) ? 'On' : 'Off';
    $smsAlerts = isset($_POST['alert_sms']) ? 'On' : 'Off';
    $_SESSION['success'] = "Notification preferences saved. Email: $emailAlerts, SMS: $smsAlerts.";
    header('Location: settings.php');
    exit();
}

// D. Handle Logic (Mock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rules'])) {
    $_SESSION['success'] = "Inventory thresholds updated successfully.";
    header('Location: settings.php');
    exit();
}

// E. Handle ML Forecast Training (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['train_forecast_model'])) {
    if ($currentRole !== 'admin') {
        $_SESSION['error'] = "Only administrators can train the forecasting model.";
    } else {
        require_once 'src/ml/MLForecastManager.php';
        try {
            set_time_limit(300);
            $manager = new MLForecastManager();
            $result = $manager->runForecast();
            $metrics = $result['result']['metrics'] ?? [];
            $acc = $metrics['forecast_accuracy'] ?? 'N/A';
            $_SESSION['success'] = "Forecast model trained successfully. Accuracy: {$acc}%. Model: " . ($result['result']['model_type'] ?? 'unknown');
        } catch (Exception $e) {
            $_SESSION['error'] = "Model training failed: " . $e->getMessage();
        }
    }
    header('Location: settings.php');
    exit();
}

$mlRunStatus = null;
$mlHasModel = false;
$mlLastMetrics = null;
if ($currentRole === 'admin') {
    require_once 'src/ml/ForecastDataService.php';
    require_once 'src/ml/MLForecastManager.php';
    $mlForecastService = new ForecastDataService($conn);
    $mlManager = new MLForecastManager();
    $mlHasModel = $mlForecastService->hasTrainedModel();
    $mlLastMetrics = $mlForecastService->getLatestMetrics();
    $mlRunStatus = $mlManager->getLastRunStatus();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

renderLayoutStart('System Configuration', $currentRole, $_SESSION['Username']);
?>

<style>
    /* Premium Card Override */
    .premium-card {
        background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .premium-card-header {
        padding: 20px;
        border-bottom: 1px solid rgba(42, 68, 132, 0.5);
        background: rgba(19, 39, 91, 0.3);
        border-radius: 16px 16px 0 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .premium-card-body {
        padding: 24px;
        flex: 1;
    }

    /* Premium Input Override */
    .premium-input {
        background: rgba(13, 22, 56, 0.6);
        color: #E3E3E3;
        border: 1px solid rgba(48, 67, 116, 0.6);
        border-radius: 10px;
        padding: 0 16px;
        font-size: 14px;
        font-family: 'Onest', sans-serif;
        height: 44px;
        width: 100%;
        transition: all 0.3s;
    }
    .premium-input:focus {
        border-color: #51D55A;
        outline: none;
        background: rgba(13, 22, 56, 0.9);
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.1);
    }

    /* Toggle Switch UI */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(255, 255, 255, 0.1);
        transition: .4s;
        border-radius: 34px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #51D55A;
        border-color: #51D55A;
    }
    input:checked + .slider:before {
        transform: translateX(20px);
    }

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
    .alert-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border-color: rgba(16, 185, 129, 0.3); }
    .alert-error { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php if ($success): ?>
    <div class="alert alert-success">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <div class="lg:col-span-2">
        <div class="premium-card">
            <div class="premium-card-header">
                <div class="w-8 h-8 rounded-lg bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div>
                    <h3 class="font-onest font-semibold text-lg text-white">Profile & Security</h3>
                    <p class="text-xs text-[#9aa6d1]">Manage your login credentials</p>
                </div>
                <div class="ml-auto">
                    <span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold border border-[#51D55A]/30 bg-[#51D55A]/10 text-[#51D55A]"><?php echo ucfirst($currentRole); ?></span>
                </div>
            </div>
            
            <div class="premium-card-body">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Username</label>
                            <input type="text" name="new_username" class="premium-input" value="<?php echo htmlspecialchars($currentUser); ?>" required>
                        </div>
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Email (Optional)</label>
                            <input type="email" name="email" class="premium-input" placeholder="admin@intelliware.ph">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">New Password</label>
                            <input type="password" name="new_password" class="premium-input" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" class="premium-input" placeholder="••••••••">
                        </div>
                    </div>

                    <div class="md:col-span-2 pt-2 border-t border-[#2A4484]/30 flex justify-end">
                        <button type="submit" class="premium-button flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="premium-card">
            <div class="premium-card-header">
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                <h3 class="font-onest font-semibold text-lg text-white">Notifications</h3>
            </div>
            
            <div class="premium-card-body">
                <form method="POST">
                    <input type="hidden" name="update_notifications" value="1">
                    
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[#E3E3E3] font-medium text-sm">Low Stock Alerts</div>
                                <div class="text-[#9aa6d1] text-xs">Notify when reorder point hit</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="alert_low_stock" checked>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[#E3E3E3] font-medium text-sm">Critical Outages</div>
                                <div class="text-[#9aa6d1] text-xs">Instant alert on zero stock</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="alert_outage" checked>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="border-t border-[#2A4484]/30 my-4"></div>

                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="alert_email" checked class="accent-[#51D55A] w-4 h-4">
                                <span class="text-sm text-[#E3E3E3] group-hover:text-white transition-colors">Email</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="alert_sms" class="accent-[#51D55A] w-4 h-4">
                                <span class="text-sm text-[#E3E3E3] group-hover:text-white transition-colors">SMS</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="premium-button w-full mt-6 flex justify-center">Save Preferences</button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="premium-card">
            <div class="premium-card-header">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="font-onest font-semibold text-lg text-white">Global Logic</h3>
            </div>
            <div class="premium-card-body">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_rules" value="1">
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Default Low Stock Threshold</label>
                        <div class="relative">
                            <input type="number" name="low_stock" class="premium-input pl-8" value="15">
                            <span class="absolute left-3 top-2.5 text-red-400 font-bold">&lt;</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Default Overstock Threshold</label>
                        <div class="relative">
                            <input type="number" name="over_stock" class="premium-input pl-8" value="1000">
                            <span class="absolute left-3 top-2.5 text-blue-400 font-bold">&gt;</span>
                        </div>
                    </div>

                    <button type="submit" class="premium-button w-full">Update Rules</button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="premium-card">
            <div class="premium-card-header">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="font-onest font-semibold text-lg text-white">System Defaults</h3>
            </div>
            <div class="premium-card-body">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_defaults" value="1">
                    
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Currency</label>
                        <select name="currency_symbol" class="premium-input cursor-pointer">
                            <option value="PHP">₱ - Philippine Peso</option>
                            <option value="USD">$ - US Dollar</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">VAT Rate (%)</label>
                        <input type="number" name="vat_rate" class="premium-input" value="12" step="0.1">
                    </div>

                    <button type="submit" class="premium-button w-full">Save Defaults</button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="premium-card border-red-500/30">
            <div class="premium-card-header bg-red-500/5">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <h3 class="font-onest font-semibold text-lg text-white">Maintenance</h3>
            </div>
            <div class="premium-card-body space-y-3">
                <div class="text-xs text-[#9aa6d1] mb-2">Last backup: <?php echo date('M d, Y'); ?></div>
                
                <form method="POST">
                    <input type="hidden" name="backup_trigger" value="1">
                    <button class="w-full h-[44px] rounded-xl border border-[#2A4484] bg-[#13275B] text-[#E3E3E3] hover:text-white hover:bg-[#1D387B] hover:border-[#51D55A]/50 transition-all font-medium text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download Backup
                    </button>
                </form>

                <button onclick="alert('Cache cleared')" class="w-full h-[44px] rounded-xl border border-red-500/30 bg-red-500/5 text-red-400 hover:bg-red-500/10 transition-all font-medium text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Clear System Cache
                </button>
            </div>
        </div>
    </div>

    <?php if ($currentRole === 'admin'): ?>
    <div class="lg:col-span-2">
        <div class="premium-card">
            <div class="premium-card-header">
                <div class="w-8 h-8 rounded-lg bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <div>
                    <h3 class="font-onest font-semibold text-lg text-white">ML Demand Forecasting</h3>
                    <p class="text-xs text-[#9aa6d1]">Train model on transaction history</p>
                </div>
            </div>
            <div class="premium-card-body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 rounded-xl bg-[#0D1638]/60 border border-[#2A4484]/40">
                        <div class="text-xs text-[#9aa6d1] mb-1">Model Status</div>
                        <div class="text-lg font-semibold <?php echo $mlHasModel ? 'text-[#51D55A]' : 'text-amber-400'; ?>">
                            <?php echo $mlHasModel ? 'Trained' : 'Not Trained'; ?>
                        </div>
                    </div>
                    <?php if ($mlLastMetrics): ?>
                    <div class="p-4 rounded-xl bg-[#0D1638]/60 border border-[#2A4484]/40">
                        <div class="text-xs text-[#9aa6d1] mb-1">Forecast Accuracy</div>
                        <div class="text-lg font-semibold text-[#51D55A]"><?php echo number_format((float)$mlLastMetrics['forecast_accuracy'], 1); ?>%</div>
                    </div>
                    <div class="p-4 rounded-xl bg-[#0D1638]/60 border border-[#2A4484]/40">
                        <div class="text-xs text-[#9aa6d1] mb-1">Last Trained</div>
                        <div class="text-sm font-medium text-[#E3E3E3]"><?php echo date('M d, Y H:i', strtotime($mlLastMetrics['training_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($mlRunStatus): ?>
                <div class="text-xs text-[#9aa6d1] mb-4">
                    Last run: <span class="text-[#E3E3E3]"><?php echo htmlspecialchars($mlRunStatus['status']); ?></span>
                    <?php if (!empty($mlRunStatus['error_message'])): ?>
                        — <span class="text-red-400"><?php echo htmlspecialchars(substr($mlRunStatus['error_message'], 0, 120)); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <p class="text-sm text-[#9aa6d1] mb-4">
                    Trains on real <strong>stock_out</strong> transaction history using Prophet or Random Forest.
                    Results appear on the Insights dashboard. Training may take 1–3 minutes.
                </p>

                <form method="POST" id="trainForecastForm">
                    <input type="hidden" name="train_forecast_model" value="1">
                    <button type="submit" class="premium-button flex items-center gap-2" onclick="return confirm('Start ML model training? This may take a few minutes.');">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Train Forecast Model
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php renderLayoutEnd(); ?>