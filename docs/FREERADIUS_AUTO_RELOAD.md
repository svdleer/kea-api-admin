# FreeRADIUS Auto-Reload Setup

Simple flag-based approach for automatic FreeRADIUS reload when NAS clients change.

## How It Works

1. **GUI changes NAS** → Writes to local `radius.nas` on FreeRADIUS server
2. **GUI sets flag** → Sets `needs_reload=1` in local `radius.radius_reload_flag`
3. **Python script polls** → Checks flag every 5 minutes
4. **Reload** → Sends HUP signal to FreeRADIUS → Clears flag

## Setup (Per FreeRADIUS Server)

### 1. Add Reload Flag Table to Radius Database

```bash
mysql -u root -p radius < database/migrations/radius_add_reload_flag.sql
```

Or manually:
```sql
USE radius;

CREATE TABLE IF NOT EXISTS radius_reload_flag (
    id INT PRIMARY KEY DEFAULT 1,
    needs_reload BOOLEAN DEFAULT FALSE,
    last_reload TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (id = 1)
);

INSERT INTO radius_reload_flag (id, needs_reload) VALUES (1, FALSE)
ON DUPLICATE KEY UPDATE id=id;
```

### 2. Install Python Script

```bash
# Create scripts directory
sudo mkdir -p /opt/scripts

# Download script
sudo wget -O /opt/scripts/freeradius-reload-check.py \
  https://raw.githubusercontent.com/svdleer/kea-api-admin/main/scripts/freeradius-reload-check.py

# Make executable
sudo chmod +x /opt/scripts/freeradius-reload-check.py
```

**No configuration needed:**
- Script automatically reads database credentials from FreeRADIUS SQL module config
- Script auto-installs `python3-pymysql` if missing (requires internet)

### 3. Test Script Manually

```bash
# Set flag manually
mysql -u radius -p radius -e "UPDATE radius_reload_flag SET needs_reload = TRUE WHERE id = 1"

# Run script (as root to send signals)
sudo python3 /opt/scripts/freeradius-reload-check.py

# Check logs
tail -f /var/log/freeradius-reload.log
```

### 4. Install Cron Job

```bash
# Auto-install cron job (runs every 5 minutes, outputs to /dev/null)
sudo python3 /opt/scripts/freeradius-reload-check.py --install-cron
```

This will:
- Check if cron job already exists (won't create duplicates)
- Add: `*/5 * * * * /usr/bin/python3 /opt/scripts/freeradius-reload-check.py > /dev/null 2>&1`
- Redirect all output to /dev/null (logs go to /var/log/freeradius-reload.log)

**Or install manually:**

```bash
sudo crontab -e
```

Add:
```
*/5 * * * * /usr/bin/python3 /opt/scripts/freeradius-reload-check.py
```

Or for more verbose logging:
```
*/5 * * * * /usr/bin/python3 /opt/scripts/freeradius-reload-check.py >> /var/log/freeradius-reload.log 2>&1
```

## Verification

### Check if Flag Works

```sql
-- Check current flag status
SELECT * FROM radius.radius_reload_flag;

-- Set flag manually to test
UPDATE radius.radius_reload_flag SET needs_reload = TRUE WHERE id = 1;
```

Wait 5 minutes or run script manually, then check:
```sql
-- Flag should be cleared and last_reload updated
SELECT * FROM radius.radius_reload_flag;
```

### Check FreeRADIUS Reload

```bash
# Watch FreeRADIUS log
sudo tail -f /var/log/freeradius/radius.log | grep -i "re-reading"

# Watch script log
sudo tail -f /var/log/freeradius-reload.log
```

### Test from GUI

1. Add/modify a BVI interface (creates NAS client)
2. Check flag is set:
   ```sql
   SELECT needs_reload FROM radius.radius_reload_flag WHERE id = 1;
   ```
3. Wait up to 5 minutes
4. Verify flag is cleared and FreeRADIUS reloaded

## Troubleshooting

### Script Can't Find PID File

Edit `FREERADIUS_PID_FILE` in the Python script:
```python
FREERADIUS_PID_FILE = '/var/run/freeradius/freeradius.pid'  # Ubuntu/Debian
# or
FREERADIUS_PID_FILE = '/var/run/radiusd/radiusd.pid'        # CentOS/RHEL
```

### Permission Denied

Script must run as root to send signals:
```bash
sudo python3 /opt/scripts/freeradius-reload-check.py
```

Cron must be root's crontab:
```bash
sudo crontab -e
```

### Database Connection Failed

Check MySQL credentials and permissions:
```bash
mysql -u radius -p radius -e "SELECT 1"
```

### Check Python Dependencies

```bash
python3 -c "import pymysql; print('OK')"
```

Install if missing:
```bash
sudo apt install python3-pymysql
```

## Benefits

✅ **Simple** - Flag in local database, script on same server  
✅ **Fast** - Reloads within 5 minutes of change  
✅ **No network** - Everything local to FreeRADIUS server  
✅ **Independent** - Each server manages its own reload  
✅ **Reliable** - Direct HUP signal to FreeRADIUS process  
✅ **Auditable** - Last reload timestamp tracked
