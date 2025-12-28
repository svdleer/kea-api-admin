# Auto-Reload FreeRADIUS After NAS Client Changes

## Overview

Simple database flag approach: When NAS clients change, a `needs_reload` flag is set. FreeRADIUS servers poll this flag via cron and automatically reload when needed.

## How It Works

1. **NAS Client Change**: User adds/updates/deletes NAS client
2. **Flag Set**: System sets `needs_reload = TRUE` in database
3. **Cron Polls**: FreeRADIUS servers check flag every minute
4. **Reload**: If TRUE, sends HUP signal to FreeRADIUS
5. **Clear Flag**: Resets `needs_reload = FALSE` after reload

## Setup (5 Minutes)

### 1. Database Migration

```bash
mysql -u kea_admin -p kea_api < database/migrations/add_radius_ssh_config.sql
```

### 2. Install Cron Script on Each RADIUS Server

```bash
# Create directory
sudo mkdir -p /opt/scripts

# Download script
sudo wget -O /opt/scripts/freeradius-auto-reload.sh \
  https://raw.githubusercontent.com/svdleer/kea-api-admin/main/scripts/freeradius-auto-reload.sh

sudo chmod +x /opt/scripts/freeradius-auto-reload.sh
sudo nano /opt/scripts/freeradius-auto-reload.sh
```

**Configure:**
```bash
DB_HOST="mysql.gt.local"
DB_USER="radius"
DB_PASS="your_password"
SERVER_NAME="FreeRADIUS Primary"  # Match database name
```

### 3. Add Cron Job

```bash
sudo crontab -e
# Add: * * * * * /opt/scripts/freeradius-auto-reload.sh
```

### 4. Test

```sql
UPDATE radius_server_config SET needs_reload = TRUE WHERE name = 'FreeRADIUS Primary';
```

Check logs: `tail -f /var/log/freeradius-auto-reload.log`

## Benefits

✅ No SSH keys needed  
✅ No network access from web container  
✅ Simple and reliable  
✅ RADIUS servers control their own reload  
✅ Typically reloads within 1 minute  
✅ No dropped sessions (HUP signal)
