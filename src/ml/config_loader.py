"""
Configuration loader for data mining scripts
Reads from .env file or PHP config file
"""

import os
import json
import re

def load_db_config():
    """Load database configuration from .env or PHP config"""
    config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'warehouse_system'
    }
    
    # Try to load from .env file first
    env_file = os.path.join(os.path.dirname(__file__), '..', '..', '.env')
    if os.path.exists(env_file):
        with open(env_file, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    key = key.strip()
                    value = value.strip()
                    
                    if key == 'DB_HOST':
                        config['host'] = value
                    elif key == 'DB_USER':
                        config['user'] = value
                    elif key == 'DB_PASSWORD':
                        config['password'] = value
                    elif key == 'DB_NAME':
                        config['database'] = value
    
    # Try to read from PHP config file as fallback
    php_config = os.path.join(os.path.dirname(__file__), '..', '..', 'config', 'db.php')
    if os.path.exists(php_config):
        try:
            with open(php_config, 'r') as f:
                content = f.read()
                
                # Extract database config using regex
                host_match = re.search(r"\$dbHost\s*=\s*['\"]([^'\"]+)['\"]", content)
                user_match = re.search(r"\$dbUser\s*=\s*['\"]([^'\"]+)['\"]", content)
                pass_match = re.search(r"\$dbPass\s*=\s*['\"]([^'\"]+)['\"]", content)
                name_match = re.search(r"\$dbName\s*=\s*['\"]([^'\"]+)['\"]", content)
                
                if host_match:
                    config['host'] = host_match.group(1)
                if user_match:
                    config['user'] = user_match.group(1)
                if pass_match:
                    config['password'] = pass_match.group(1)
                if name_match:
                    config['database'] = name_match.group(1)
        except Exception as e:
            pass  # Fall back to defaults
    
    # Override with environment variables if set
    config['host'] = os.getenv('DB_HOST', config['host'])
    config['user'] = os.getenv('DB_USER', config['user'])
    config['password'] = os.getenv('DB_PASSWORD', config['password'])
    config['database'] = os.getenv('DB_NAME', config['database'])
    
    return config



