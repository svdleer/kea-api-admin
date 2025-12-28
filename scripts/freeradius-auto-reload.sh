#!/bin/bash
# FreeRADIUS Auto-Reload Script
# Place in /opt/scripts/freeradius-auto-reload.sh
# Run via cron every minute: * * * * * /opt/scripts/freeradius-auto-reload.sh

# MySQL connection details - CONFIGURE THESE
DB_HOST="mysql.gt.local"
DB_PORT="3306"
DB_NAME="kea_api"
DB_USER="radius"
DB_PASS="your_password"

# Server identification (must match database name)
SERVER_NAME="FreeRADIUS Primary"

# Lockfile to prevent concurrent runs
LOCKFILE="/var/lock/freeradius-auto-reload.lock"
LOGFILE="/var/log/freeradius-auto-reload.log"

# Exit if another instance is running
if [ -f "$LOCKFILE" ]; then
    exit 0
fi

# Create lockfile
touch "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

# Function to log with timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

# Check if reload flag is set for this server
NEEDS_RELOAD=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -sN -e "SELECT needs_reload FROM radius_server_config WHERE name = '$SERVER_NAME' LIMIT 1" 2>/dev/null)

if [ "$NEEDS_RELOAD" = "1" ]; then
    log "Reload flag detected, reloading FreeRADIUS..."
    
    # Reload FreeRADIUS (sends HUP signal)
    if systemctl reload freeradius 2>/dev/null; then
        log "FreeRADIUS reloaded successfully via systemctl"
        RELOAD_SUCCESS=1
    elif killall -HUP radiusd 2>/dev/null; then
        log "FreeRADIUS reloaded successfully via killall"
        RELOAD_SUCCESS=1
    else
        log "ERROR: Failed to reload FreeRADIUS"
        RELOAD_SUCCESS=0
    fi
    
    # Clear the reload flag if successful
    if [ "$RELOAD_SUCCESS" = "1" ]; then
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
            -e "UPDATE radius_server_config SET needs_reload = FALSE WHERE name = '$SERVER_NAME'" 2>/dev/null
        log "Reload flag cleared"
    fi
fi
