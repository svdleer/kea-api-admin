# Auto-Reload FreeRADIUS After NAS Client Changes

## Overview

The system now automatically sends a HUP signal to FreeRADIUS servers after NAS client changes, causing FreeRADIUS to reload its configuration from MySQL without dropping active sessions.

## Setup Steps

### 1. Run Database Migration

Add SSH configuration fields to `radius_server_config` table:

```bash
mysql -u kea_admin -p kea_api < database/migrations/add_radius_ssh_config.sql
```

Or manually:

```sql
ALTER TABLE radius_server_config 
ADD COLUMN ssh_host VARCHAR(255) DEFAULT NULL COMMENT 'SSH hostname/IP for FreeRADIUS reload',
ADD COLUMN ssh_user VARCHAR(50) DEFAULT 'root' COMMENT 'SSH username',
ADD COLUMN ssh_port INT DEFAULT 22 COMMENT 'SSH port',
ADD COLUMN auto_reload BOOLEAN DEFAULT TRUE COMMENT 'Auto-reload FreeRADIUS after sync';
```

### 2. Configure SSH Access

For each RADIUS server, update the configuration:

```sql
UPDATE radius_server_config SET 
  ssh_host = 'radius1.gt.local',     -- FreeRADIUS server hostname/IP
  ssh_user = 'root',                  -- SSH user
  ssh_port = 22,                      -- SSH port
  auto_reload = TRUE                  -- Enable auto-reload
WHERE name = 'FreeRADIUS Primary';
```

### 3. Set Up SSH Key Authentication

On the **DAA Admin server** (where PHP runs):

```bash
# Generate SSH key if not exists
ssh-keygen -t ed25519 -f ~/.ssh/id_freeradius -N ""

# Copy public key to RADIUS servers
ssh-copy-id -i ~/.ssh/id_freeradius root@radius1.gt.local
ssh-copy-id -i ~/.ssh/id_freeradius root@radius2.gt.local

# Test passwordless login
ssh root@radius1.gt.local 'echo "SSH works!"'
```

### 4. Configure Sudo Access (If Not Root)

If using a non-root SSH user, allow passwordless sudo for reload:

```bash
# On each FreeRADIUS server
sudo visudo

# Add this line:
radius_admin ALL=(ALL) NOPASSWD: /bin/systemctl reload freeradius, /usr/bin/killall -HUP radiusd
```

### 5. Test the Setup

From the DAA Admin web interface:

1. Go to **Switches** → Select a switch → **BVI Interfaces**
2. Add or modify a BVI interface
3. Check logs to confirm FreeRADIUS reload:

```bash
# On DAA Admin server
docker-compose logs -f kea-api-admin | grep "Auto-reloading FreeRADIUS"

# On FreeRADIUS server
tail -f /var/log/freeradius/radius.log | grep "Re-reading"
```

## How It Works

### Automatic Reload

After any NAS client change (add/update/delete):
1. System syncs NAS client to MySQL on all RADIUS servers
2. If `auto_reload = TRUE` and `ssh_host` is configured
3. SSH command is executed in background: `systemctl reload freeradius`
4. FreeRADIUS reads updated NAS clients from MySQL
5. No active RADIUS sessions are dropped

### Manual Reload

You can also manually trigger reload via Admin Tools or API:

```php
$radiusSync = new \App\Helpers\RadiusDatabaseSync();
$results = $radiusSync->reloadFreeRadius();
```

### SSH Command Used

```bash
ssh -o ConnectTimeout=5 -p 22 root@radius1.gt.local \
  'sudo systemctl reload freeradius 2>/dev/null || sudo killall -HUP radiusd 2>/dev/null'
```

Falls back to `killall -HUP radiusd` if systemd is not available.

## Troubleshooting

### Reload Not Working

Check logs:
```bash
docker-compose logs kea-api-admin | grep "FreeRADIUS"
```

Common issues:
- SSH key not configured → Set up passwordless SSH
- Wrong SSH host/port → Verify `ssh_host` and `ssh_port` in database
- Sudo password required → Configure passwordless sudo
- FreeRADIUS service name wrong → May be `radiusd` instead of `freeradius`

### Test SSH Access Manually

```bash
# From DAA Admin server
docker-compose exec kea-api-admin ssh root@radius1.gt.local 'systemctl status freeradius'
```

### Disable Auto-Reload

For a specific server:
```sql
UPDATE radius_server_config SET auto_reload = FALSE WHERE name = 'FreeRADIUS Secondary';
```

## Security Considerations

- SSH key is stored inside Docker container only
- SSH connections use timeout (5 seconds)
- Reload command runs in background (non-blocking)
- Failed reloads are logged but don't block NAS sync
- Only specific sudo commands are allowed (if non-root)

## Benefits

✅ **No manual intervention** - NAS clients automatically take effect  
✅ **No dropped sessions** - HUP signal reloads config gracefully  
✅ **Fast** - Runs in background, doesn't slow down web UI  
✅ **Safe** - Failed reload doesn't affect NAS sync to database  
✅ **Configurable** - Can enable/disable per server
