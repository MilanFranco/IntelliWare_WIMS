<?php
session_start();
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['Username']) || ($_SESSION['Role'] ?? '') !== 'admin')) {
    header('Location: signin.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Find Venv Python</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 4px; }
        .section { margin: 20px 0; padding: 15px; background: #f9fafb; border-left: 4px solid #1D387B; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Finding Your Virtual Environment Python</h1>
        
        <?php
        $projectRoot = __DIR__;
        $found = false;
        $pythonPath = null;
        
        // Check common venv locations
        $venvLocations = [
            $projectRoot . '\\venv\\Scripts\\python.exe',
            $projectRoot . '\\env\\Scripts\\python.exe',
            $projectRoot . '\\virtualenv\\Scripts\\python.exe',
            'C:\\venv\\Scripts\\python.exe',
            'C:\\env\\Scripts\\python.exe',
        ];
        
        echo "<div class='section'>";
        echo "<h2>Checking Virtual Environment Locations:</h2>";
        foreach ($venvLocations as $path) {
            if (file_exists($path)) {
                echo "<p class='success'>✓ Found: <code>$path</code></p>";
                $found = true;
                $pythonPath = $path;
                
                // Test it
                $output = [];
                exec(escapeshellcmd($path) . " --version 2>&1", $output);
                if (!empty($output)) {
                    echo "<p>Version: " . htmlspecialchars($output[0]) . "</p>";
                }
                break;
            } else {
                echo "<p>✗ Not found: <code>$path</code></p>";
            }
        }
        echo "</div>";
        
        if (!$found) {
            echo "<div class='section'>";
            echo "<h2>Virtual Environment Not Found</h2>";
            echo "<p>Your venv might be in a different location. Please find it manually:</p>";
            echo "<ol>";
            echo "<li>Open File Explorer</li>";
            echo "<li>Navigate to where you created your venv</li>";
            echo "<li>Go into the <code>Scripts</code> folder</li>";
            echo "<li>Copy the full path to <code>python.exe</code></li>";
            echo "</ol>";
            echo "<p><strong>Common locations:</strong></p>";
            echo "<ul>";
            echo "<li><code>C:\\venv\\Scripts\\python.exe</code></li>";
            echo "<li><code>C:\\Users\\YourName\\venv\\Scripts\\python.exe</code></li>";
            echo "<li><code>C:\\xampp\\htdocs\\IntelliWare\\venv\\Scripts\\python.exe</code></li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='section'>";
            echo "<h2>✓ Python Found!</h2>";
            echo "<p>Your venv Python is at: <code>" . htmlspecialchars($pythonPath) . "</code></p>";
            echo "<h3>How to Configure:</h3>";
            echo "<ol>";
            echo "<li>Open <code>src/ml/PythonHelper.php</code></li>";
            echo "<li>Find the <code>getPythonPath()</code> method (around line 78)</li>";
            echo "<li>Uncomment and set the manual path:</li>";
            echo "</ol>";
            echo "<pre>";
            echo "\$manualPath = '" . htmlspecialchars($pythonPath) . "';\n";
            echo "if (\$manualPath && file_exists(\$manualPath)) {\n";
            echo "    return \$manualPath;\n";
            echo "}";
            echo "</pre>";
            echo "<p><strong>Or click below to auto-configure:</strong></p>";
            echo "<form method='post' action='apply_venv_path.php'>";
            echo "<input type='hidden' name='python_path' value='" . htmlspecialchars($pythonPath) . "'>";
            echo "<button type='submit' style='background:#51D55A; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; font-size:16px;'>🔧 Auto-Configure Python Path</button>";
            echo "</form>";
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>



