#!/usr/bin/env python3
"""
FreeRADIUS Auto-Reload Script
Checks local radius database for reload flag and sends HUP signal to FreeRADIUS
Reads database credentials from FreeRADIUS SQL module configuration

Install: sudo cp freeradius-reload-check.py /opt/scripts/
Run: python3 /opt/scripts/freeradius-reload-check.py (via cron every 5 minutes)
"""

import sys
import os
import signal
import logging
import re
from datetime import datetime

# Auto-install MySQL connector if not available
try:
    import pymysql as mysql_connector
    # pymysql uses slightly different API, make it compatible
    mysql_connector.connect = pymysql.connect
except ImportError:
    print("pymysql not found, attempting to install...")
    import subprocess
    try:
        # Use apt to install system package (works on Ubuntu/Debian)
        subprocess.check_call(["apt", "install", "-y", "python3-pymysql"], 
                            stderr=subprocess.DEVNULL, 
                            stdout=subprocess.DEVNULL)
        import pymysql as mysql_connector
        mysql_connector.connect = pymysql.connect
        print("python3-pymysql installed successfully via apt")
    except Exception as e:
        print(f"Failed to install python3-pymysql via apt: {e}")
        print("Please install manually: sudo apt install python3-pymysql")
        sys.exit(1)

# ============= CONFIGURATION =============
# FreeRADIUS SQL config file locations (try in order)
FREERADIUS_SQL_CONFIGS = [
    '/etc/freeradius/3.0/mods-enabled/sql',
    '/etc/freeradius/mods-enabled/sql',
    '/etc/raddb/mods-enabled/sql',
    '/etc/freeradius/3.0/mods-available/sql',
    '/etc/raddb/mods-available/sql'
]

LOG_FILE = '/var/log/freeradius-reload.log'
FREERADIUS_PID_FILE = '/var/run/radiusd/radiusd.pid'  # or /var/run/freeradius/freeradius.pid
# =========================================

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler()
    ]
)

def parse_freeradius_sql_config():
    """Parse FreeRADIUS SQL module config to extract database credentials"""
    config = {
        'host': 'localhost',
        'port': 3306,
        'database': 'radius',
        'user': 'radius',
        'password': None
    }
    
    # Find first existing config file
    sql_config_file = None
    for config_path in FREERADIUS_SQL_CONFIGS:
        if os.path.exists(config_path):
            sql_config_file = config_path
            logging.info(f"Found FreeRADIUS SQL config: {config_path}")
            break
    
    if not sql_config_file:
        logging.warning(f"No FreeRADIUS SQL config found, using defaults")
        return config
    
    try:
        with open(sql_config_file, 'r') as f:
            content = f.read()
            
            # Extract MySQL connection details using regex
            # Looking for patterns like: server = "localhost"
            patterns = {
                'host': r'server\s*=\s*["\']([^"\']+)["\']',
                'port': r'port\s*=\s*(\d+)',
                'database': r'radius_db\s*=\s*["\']([^"\']+)["\']',
                'user': r'login\s*=\s*["\']([^"\']+)["\']',
                'password': r'password\s*=\s*["\']([^"\']+)["\']'
            }
            
            for key, pattern in patterns.items():
                match = re.search(pattern, content, re.IGNORECASE)
                if match:
                    value = match.group(1)
                    if key == 'port':
                        config[key] = int(value)
                    else:
                        config[key] = value
                    logging.debug(f"Parsed {key}: {value if key != 'password' else '***'}")
        
        if not config['password']:
            logging.error("Could not find password in FreeRADIUS SQL config")
            
        return config
        
    except Exception as e:
        logging.error(f"Error parsing FreeRADIUS SQL config: {e}")
        return config

def get_freeradius_pid():
    """Get FreeRADIUS process ID from PID file"""
    try:
        # Try common PID file locations
        pid_files = [
            FREERADIUS_PID_FILE,
            '/var/run/freeradius/freeradius.pid',
            '/var/run/radiusd/radiusd.pid',
            '/run/freeradius/freeradius.pid'
        ]
        
        for pid_file in pid_files:
            if os.path.exists(pid_file):
                with open(pid_file, 'r') as f:
                    return int(f.read().strip())
        
        logging.error(f"FreeRADIUS PID file not found in common locations")
        return None
    except Exception as e:
        logging.error(f"Error reading PID file: {e}")
        return None

def send_hup_signal(pid):
    """Send HUP signal to FreeRADIUS process"""
    try:
        os.kill(pid, signal.SIGHUP)
        logging.info(f"Sent HUP signal to FreeRADIUS (PID: {pid})")
        return True
    except ProcessLookupError:
        logging.error(f"Process {pid} not found")
        return False
    except PermissionError:
        logging.error(f"Permission denied to send signal to PID {pid}. Run as root/sudo.")
        return False
    except Exception as e:
        logging.error(f"Error sending HUP signal: {e}")
        return False

def check_and_reload():
    """Check database flag and reload FreeRADIUS if needed"""
    conn = None
    cursor = None
    
    try:
        # Parse FreeRADIUS SQL config for credentials
        db_config = parse_freeradius_sql_config()
        
        if not db_config['password']:
            logging.error("No database password found in FreeRADIUS config")
            return
        
        # Connect to local radius database
        conn = mysql_connector.connect(
            host=db_config['host'],
            port=db_config['port'],
            user=db_config['user'],
            password=db_config['password'],
            database=db_config['database']
        )
        cursor = conn.cursor()
        
        # Check if reload is needed
        cursor.execute("SELECT needs_reload FROM radius_reload_flag WHERE id = 1")
        result = cursor.fetchone()
        
        if not result:
            logging.error("No reload flag found in database")
            return
        
        needs_reload = result[0]
        
        if needs_reload:
            logging.info("Reload flag is set, reloading FreeRADIUS...")
            
            # Get FreeRADIUS PID
            pid = get_freeradius_pid()
            if not pid:
                logging.error("Cannot reload: FreeRADIUS PID not found")
                return
            
            # Send HUP signal
            if send_hup_signal(pid):
                # Clear the flag and update last_reload timestamp
                cursor.execute("""
                    UPDATE radius_reload_flag 
                    SET needs_reload = FALSE, last_reload = NOW() 
                    WHERE id = 1
                """)
                conn.commit()
                logging.info("FreeRADIUS reloaded successfully, flag cleared")
            else:
                logging.error("Failed to send HUP signal to FreeRADIUS")
        else:
            logging.debug("No reload needed")
            
    except Exception as e:
        logging.error(f"Database error: {e}")
    except Exception as e:
        logging.error(f"Unexpected error: {e}")
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

if __name__ == '__main__':
    try:
        check_and_reload()
    except Exception as e:
        logging.error(f"Fatal error: {e}")
        sys.exit(1)
