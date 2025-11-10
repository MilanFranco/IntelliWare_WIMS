# Python Setup for ML Features

The AI forecast and recommendation features require Python 3.9 or later to be installed on your system.

## Installation Steps

### 1. Download Python
- Visit https://www.python.org/downloads/
- Download Python 3.9 or later (Python 3.11 or 3.12 recommended)
- Run the installer

### 2. During Installation
- **IMPORTANT**: Check the box "Add Python to PATH" when installing
- This allows the system to automatically find Python
- Choose "Install Now" or "Customize installation"

### 3. Verify Installation
Open a command prompt and run:
```bash
python --version
```

You should see something like:
```
Python 3.11.5
```

### 4. Install Required Packages
Navigate to the `src/ml` directory and run:
```bash
pip install -r requirements.txt
```

This will install all required Python packages:
- numpy
- pandas
- prophet
- scikit-learn
- mysql-connector-python
- joblib
- python-dotenv

### 5. Manual Path Configuration (Optional)
If Python is installed but not detected automatically, you can manually set the path in:
- `src/ml/MLForecastManager.php` - Line 18: Set `$customPath`
- `src/ml/ProductRecommendationManager.php` - Line 17: Set `$customPath`

Example:
```php
$customPath = "C:\\Python311\\python.exe";
```

## Troubleshooting

### Python Not Found
If you get an error that Python is not found:
1. Verify Python is installed: `python --version`
2. Check if Python is in PATH: `where python`
3. If not in PATH, either:
   - Reinstall Python with "Add to PATH" checked
   - Or manually set the path in the PHP files (see step 5 above)

### Package Installation Errors
If `pip install` fails:
1. Make sure you're using the correct Python: `python -m pip install -r requirements.txt`
2. Try upgrading pip: `python -m pip install --upgrade pip`
3. On Windows, you may need to run as Administrator

### Database Connection Issues
The Python scripts need database access. Make sure:
1. MySQL/MariaDB is running
2. Database credentials are correct in `.env` file (if using) or in the Python scripts
3. The database user has proper permissions

## Testing
After setup, you can test the Python scripts directly:
```bash
python src/ml/forecast_engine.py
python src/ml/product_recommender.py
```

These should output JSON with forecast/recommendation data.

