<?php
session_start();
// 1. --- Include Layout & Check Admin ---
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); } // Kept your admin-only check

require_once 'src/layout.php'; // Include your layout functions

// 2. --- Get Session Data & Flash Messages ---
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// 3. --- Handle POST Request (e.g., Backup) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        // --- SIMULATED BACKUP ---
        // In a real app, you would run 'mysqldump' or a similar PHP backup script here.
        // For now, we just show a success message.
        $_SESSION['success'] = 'Database backup initiated (simulation).';
    }

    // Redirect to prevent form re-submission
    header('Location: settings.php');
    exit();
}


// 4. --- Set variables and Render Page ---
$pageTitle = 'System Settings';
$role = $_SESSION['Role'];
$username = $_SESSION['Username'];
$lowStockCount = 0; // We can set this to 0, or fetch the real count if needed

renderLayoutStart($pageTitle, $role, $username, $lowStockCount);
?>

<style>
  .card { 
    background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
    border: 1px solid rgba(42, 68, 132, 0.6);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    max-width: 640px; /* Set a max width for settings cards */
  }
  .label {
    color: #E3E3E3;
    font-family: 'Onest';
    font-size: 13px;
    margin-bottom: 12px;
    display: block;
    font-weight: 500;
  }
  .alert {
    padding: 16px;
    border-radius: 12px;
    font-family: 'Onest';
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    border: 1px solid;
    max-width: 640px; /* Match card width */
  }
  .alert-success {
    background: rgba(16, 185, 129, 0.18);
    color: #6ee7b7;
    border-color: rgba(16, 185, 129, 0.35);
  }
  
  /* Toggle Button Styles */
  .btn-toggle {
    background: rgba(19, 39, 91, 0.8);
    border: 1px solid rgba(48, 67, 116, 0.6);
    color: #E3E3E3;
    border-radius: 12px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    cursor: pointer;
    transition: all 0.3s;
  }
  .btn-toggle:hover {
    border-color: #51D55A;
  }
  .btn-toggle.active {
    background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3);
  }
  
  /* Secondary/Outline Button Style */
  .premium-button-outline {
    background: transparent;
    border: 1px solid rgba(48, 67, 116, 0.6);
    color: #E3E3E3;
    border-radius: 12px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-block; /* For label */
  }
  .premium-button-outline:hover {
    background: rgba(19, 39, 91, 0.8);
    border-color: #51D55A;
  }
  
  .hidden-file-input {
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    position: absolute;
    z-index: -1;
  }
</style>

<?php if ($success): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card">
  <h2 class="text-xl font-semibold text-white font-onest mb-5">Notification Settings</h2>
  
  <label class="label">Browser Notifications (e.g., Low Stock Alerts)</label>
  <div class="flex gap-4">
    <button class="btn-toggle" type="button" id="notify-on">Enable</button>
    <button class="btn-toggle" type="button" id="notify-off">Disable</button>
  </div>
  <p class="text-sm text-gray-400 mt-4 font-onest">
    Enabling this will ask for browser permission to show alerts.
    Your preference is saved locally in your browser.
  </p>
</div>

<div class="card mt-6">
  <h2 class="text-xl font-semibold text-white font-onest mb-5">Data Management</h2>
  <label class="label">Backup / Restore Database</label>
  <div class="flex flex-col sm:flex-row gap-4">
    
    <form method="POST" class="w-full sm:w-auto">
      <button class="premium-button w-full" type="submit" name="action" value="backup">
        Backup Database
      </button>
    </form>
    
    <label class="premium-button-outline w-full sm:w-auto text-center">
      <span>Restore from Backup</span>
      <input type="file" class="hidden-file-input" onchange="alert('File selected: ' + this.files[0].name + '. A real implementation would now upload this file.')">
    </label>
    
  </div>
  <p class="text-sm text-gray-400 mt-4 font-onest">
    Restoring from a backup will overwrite all current data. Proceed with caution.
  </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const notifyOnBtn = document.getElementById('notify-on');
  const notifyOffBtn = document.getElementById('notify-off');

  // Function to set the visual state of buttons
  function setNotificationState(preference) {
    if (preference === 'enabled') {
      notifyOnBtn.classList.add('active');
      notifyOffBtn.classList.remove('active');
    } else {
      notifyOnBtn.classList.remove('active');
      notifyOffBtn.classList.add('active');
    }
    // Save the preference to localStorage
    localStorage.setItem('notificationPreference', preference);
  }

  // Check for saved preference on page load
  const savedPref = localStorage.getItem('notificationPreference') || 'disabled'; // Default to disabled
  setNotificationState(savedPref);

  // --- Event Listeners ---
  
  notifyOnBtn.addEventListener('click', () => {
    // Check if Notification API is available
    if (!("Notification" in window)) {
      alert("This browser does not support desktop notification.");
    } 
    // Check if permission is already granted
    else if (Notification.permission === "granted") {
      setNotificationState('enabled');
      new Notification("Notifications Enabled!", { body: "You will now receive alerts." });
    } 
    // Ask for permission
    else if (Notification.permission !== "denied") {
      Notification.requestPermission().then((permission) => {
        if (permission === "granted") {
          setNotificationState('enabled');
          new Notification("Notifications Enabled!", { body: "You will now receive alerts." });
        }
      });
    }
  });

  notifyOffBtn.addEventListener('click', () => {
    setNotificationState('disabled');
  });
});
</script>

<?php
// 5. --- Render the end of the layout ---
renderLayoutEnd();
?>