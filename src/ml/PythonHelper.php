<?php
/**
 * Helper class to locate Python executable
 */
class PythonHelper {
    private static $pythonPaths = [
        // Common installation paths
        'C:\\Python39\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python312\\python.exe',
        'C:\\Python313\\python.exe',
        'C:\\Program Files\\Python39\\python.exe',
        'C:\\Program Files\\Python310\\python.exe',
        'C:\\Program Files\\Python311\\python.exe',
        'C:\\Program Files\\Python312\\python.exe',
        'C:\\Program Files (x86)\\Python39\\python.exe',
        'C:\\Program Files (x86)\\Python310\\python.exe',
        'C:\\Program Files (x86)\\Python311\\python.exe',
    ];
    
    /**
     * Find Python executable
     * @return string|null Path to Python executable or null if not found
     */
    public static function findPython() {
        // 0. Check custom environment variable
        $envPython = getenv('PYTHON_PATH') ?: ($_ENV['PYTHON_PATH'] ?? null);
        if (!empty($envPython) && file_exists($envPython)) {
            return $envPython;
        }

        // Check for venv Python in project directory first (Windows and Linux)
        $projectRoot = dirname(__DIR__, 2); // Go up from src/ml to project root
        $venvPaths = [
            // Linux / Unix virtualenv paths
            $projectRoot . '/venv/bin/python3',
            $projectRoot . '/venv/bin/python',
            $projectRoot . '/.venv/bin/python3',
            $projectRoot . '/.venv/bin/python',
            '/opt/venv/bin/python3',
            '/opt/venv/bin/python',
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            // Windows virtualenv paths
            $projectRoot . '\\.venv\\Scripts\\python.exe',
            $projectRoot . '\\venv\\Scripts\\python.exe',
            $projectRoot . '\\env\\Scripts\\python.exe',
            $projectRoot . '\\virtualenv\\Scripts\\python.exe',
            'C:\\venv\\Scripts\\python.exe',
            'C:\\env\\Scripts\\python.exe',
        ];
        
        foreach ($venvPaths as $venvPath) {
            if (file_exists($venvPath)) {
                return $venvPath;
            }
        }
        
        // Check if python3 or python is in PATH
        foreach (['python3', 'python'] as $cmd) {
            $output = [];
            $return_var = 0;
            @exec("$cmd --version 2>&1", $output, $return_var);
            if ($return_var === 0) {
                $paths = [];
                @exec("which $cmd 2>&1", $paths, $return_var);
                if (!empty($paths) && file_exists(trim($paths[0]))) {
                    return trim($paths[0]);
                }
                @exec("where $cmd 2>&1", $paths, $return_var);
                if (!empty($paths) && file_exists(trim($paths[0]))) {
                    return trim($paths[0]);
                }
            }
        }
        
        // Check common installation paths
        foreach (self::$pythonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Check user's AppData Local folder
        $userProfile = getenv('USERPROFILE');
        if ($userProfile) {
            $appDataPath = $userProfile . '\\AppData\\Local\\Programs\\Python';
            if (is_dir($appDataPath)) {
                $dirs = scandir($appDataPath);
                foreach ($dirs as $dir) {
                    if ($dir !== '.' && $dir !== '..' && is_dir($appDataPath . '\\' . $dir)) {
                        $pythonPath = $appDataPath . '\\' . $dir . '\\python.exe';
                        if (file_exists($pythonPath)) {
                            return $pythonPath;
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get Python executable path with error handling
     * @param string $customPath Optional custom path to check first
     * @return string Path to Python executable
     * @throws Exception If Python is not found
     */
    public static function getPythonPath($customPath = null) {
        // Check custom path first if provided
        if ($customPath && file_exists($customPath)) {
            return $customPath;
        }
        
        // Try to auto-detect (finds project .venv first)
        $pythonPath = self::findPython();
        
        if ($pythonPath) {
            return $pythonPath;
        }
        
        // Python not found - provide helpful error message
        $errorMsg = "Python executable not found. Python 3.9 or later is required for AI forecast features.\n\n";
        $errorMsg .= "QUICK FIX:\n";
        $errorMsg .= "1. Download Python from: https://www.python.org/downloads/\n";
        $errorMsg .= "2. During installation, check 'Add Python to PATH'\n";
        $errorMsg .= "3. Restart your web server after installation\n\n";
        $errorMsg .= "ALTERNATIVE:\n";
        $errorMsg .= "If Python is already installed, set the path in:\n";
        $errorMsg .= "- src/ml/MLForecastManager.php (line 18)\n";
        $errorMsg .= "- src/ml/ProductRecommendationManager.php (line 17)\n\n";
        $errorMsg .= "For detailed setup instructions, see: src/ml/README_PYTHON_SETUP.md";
        
        throw new Exception($errorMsg);
    }
}
?>

