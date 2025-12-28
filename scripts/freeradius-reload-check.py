#!/usr/bin/env python3
"""
FreeRADIUS Auto-Reload Script
Checks local radius database for reload flag and sends HUP signal to FreeRADIUS

Install: sudo cp freeradius-reload-check.py /opt/scripts/
Run: python3 /opt/scripts/freeradius-reload-check.py (via cron every 5 minutes)
"""

import sys
import os
import signal
import logging
import mysql.connector
from datetime import datetime

# ============= CONFIGURATION =============
DB_HOST = 'localhost'  # Local MySQL
DB_PORT = 3306
DB_NAME = 'radius'     # FreeRADIUS database
DB_USER = 'radius'
DB_PASS = 'radpass'    # Change this!

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
        # Connect to local radius database
        conn = mysql.connector.connect(
            host=DB_HOST,
            port=DB_PORT,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME
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
            
    except mysql.connector.Error as e:
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
