#!/bin/bash
# FreeRADIUS Auto-Reload Script
# Place on each FreeRADIUS server and run via cron
# Example cron: */1 * * * * /usr/local/bin/radius-check-reload.sh

# Database configuration (match your radius server mysql connection)
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-radius}"
DB_PASS="${DB_PASS:-your_radius_password}"
DB_NAME="${DB_NAME:-radius}"
SERVER_ID="${RADIUS_SERVER_ID:-1}"  # Get from radius_server_config table

# Check if reload is needed
NEEDS_RELOAD=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN \
  -e "SELECT needs_reload FROM radius_server_config WHERE id = $SERVER_ID")

if [ "$NEEDS_RELOAD" = "1" ]; then
    echo "$(date): Reload flag detected, reloading FreeRADIUS..."
    
    # Reload FreeRADIUS (try systemd first, fallback to HUP signal)
    if systemctl reload freeradius 2>/dev/null; then
        echo "$(date): FreeRADIUS reloaded via systemctl"
    elif killall -HUP radiusd 2>/dev/null; then
        echo "$(date): FreeRADIUS reloaded via HUP signal"
    else
        echo "$(date): ERROR: Failed to reload FreeRADIUS"
        exit 1
    fi
    
    # Clear the reload flag
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "UPDATE radius_server_config SET needs_reload = FALSE WHERE id = $SERVER_ID"
    
    echo "$(date): Reload flag cleared"
fi
