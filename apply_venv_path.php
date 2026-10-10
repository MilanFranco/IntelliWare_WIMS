<?php
/**
 * Apply Venv Python Path
 */

session_start();
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['Username']) || ($_SESSION['Role'] ?? '') !== 'admin')) {
    http_response_code(403);
    die("Error: Admin access required.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['python_path'])) {
    $python_path = $_POST['python_path'];
    $helper_file = __DIR__ . '/src/ml/PythonHelper.php';
    
    if (!file_exists($helper_file)) {
        die("Error: PythonHelper.php not found");
    }
    
    $content = file_get_contents($helper_file);
    
    // Replace or add manual path
    if (preg_match('/\/\/ Manual Python path.*?if \(\$manualPath && file_exists\(\$manualPath\)\) \{.*?return \$manualPath;.*?\}/s', $content)) {
        // Update existing
        $content = preg_replace(
            '/(\/\/ Manual Python path.*?)\$manualPath = \'\';(.*?if \(\$manualPath && file_exists\(\$manualPath\)\) \{.*?return \$manualPath;.*?\})/s',
            '$1$manualPath = \'' . addslashes($python_path) . '\';$2',
            $content
        );
    } else {
        // Add new
        $content = preg_replace(
            '/(public static function getPythonPath\(\$customPath = null\) \{)/',
            "$1\n        // Manual Python path (venv)\n        \$manualPath = '" . addslashes($python_path) . "';\n        if (\$manualPath && file_exists(\$manualPath)) {\n            return \$manualPath;\n        }",
            $content
        );
    }
    
    // Backup
    $backup = $helper_file . '.backup.' . date('YmdHis');
    copy($helper_file, $backup);
    
    if (file_put_contents($helper_file, $content)) {
        echo "<!DOCTYPE html><html><head><title>Success</title><style>body{font-family:Arial;margin:40px;background:#f5f5f5;} .container{max-width:600px;margin:0 auto;background:white;padding:30px;border-radius:8px;} .success{color:#10b981;font-weight:bold;}</style></head><body>";
        echo "<div class='container'>";
        echo "<h1 class='success'>✓ Python Path Configured!</h1>";
        echo "<p>Your venv Python path has been set to:</p>";
        echo "<p><code>" . htmlspecialchars($python_path) . "</code></p>";
        echo "<p>Backup saved to: <code>" . htmlspecialchars(basename($backup)) . "</code></p>";
        echo "<hr>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ol>";
        echo "<li><a href='insights.php'>Go to Insights Page</a></li>";
        echo "<li>Click 'Run Data Mining'</li>";
        echo "<li>It should work now!</li>";
        echo "</ol>";
        echo "</div></body></html>";
    } else {
        die("Error: Could not write to file. Check permissions.");
    }
} else {
    header('Location: find_venv_python.php');
}
?>



