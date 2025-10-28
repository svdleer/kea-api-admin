# RADIUS Management Scripts

## Overview
These scripts help maintain RADIUS database integrity by detecting and cleaning up orphaned NAS entries.

## Scripts

### 1. `check_radius_orphans.php`
**Purpose:** Detect orphaned RADIUS entries that reference deleted BVI interfaces.

**Usage:**
```bash
php scripts/check_radius_orphans.php
```

**What it checks:**
- Local `nas` table in `kea_db`
- Remote RADIUS Primary database
- Remote RADIUS Secondary database

**Output:**
- Reports orphaned entries (nas entries with `bvi_interface_id` pointing to non-existent BVI interfaces)
- Shows valid BVI interface IDs
- Lists all NAS entries and their status

### 2. `clean_radius_orphans.php`
**Purpose:** Remove orphaned RADIUS entries from all databases.

**Usage:**
```bash
php scripts/clean_radius_orphans.php
```

**What it does:**
1. Identifies valid BVI interface IDs from `cin_switch_bvi_interfaces`
2. Deletes orphaned entries from local `nas` table
3. Deletes orphaned entries from all configured remote RADIUS servers
4. Reports number of entries deleted

**⚠️ Warning:** This script performs DELETE operations. Run `check_radius_orphans.php` first to see what will be deleted.

## When to Use These Scripts

### Automatic Prevention (Built-in)
With the foreign key constraints added in the database migration, orphaned entries **should not occur** in normal operation:
- When a BVI interface is deleted, the CASCADE DELETE removes the RADIUS entry automatically
- When a switch is deleted, all BVI interfaces cascade delete, which cascade deletes RADIUS entries

### Manual Cleanup (When Needed)
Use these scripts when:
1. **After manual database manipulation** - If you manually deleted records without using the API
2. **Legacy data cleanup** - Cleaning up data from before foreign key constraints were added
3. **After sync failures** - If RADIUS sync to remote servers failed, causing inconsistencies
4. **Periodic maintenance** - Run occasionally to ensure all RADIUS databases are in sync

## Example Workflow

### 1. Check for orphans
```bash
ssh kea_admin@kea.useless.nl "cd /home/httpd/vhosts/kea.useless.nl/httpdocs && php scripts/check_radius_orphans.php"
```

**Example output:**
```
Valid BVI interface IDs: 10
Server: FreeRADIUS Primary
  ❌ ID 3: 2001:b88:8005:f007::1 - bvi_interface_id 47 doesn't exist!
  ❌ Found 1 orphaned entries in FreeRADIUS Primary
```

### 2. Clean up orphans (if found)
```bash
ssh kea_admin@kea.useless.nl "cd /home/httpd/vhosts/kea.useless.nl/httpdocs && php scripts/clean_radius_orphans.php"
```

### 3. Verify cleanup
```bash
ssh kea_admin@kea.useless.nl "cd /home/httpd/vhosts/kea.useless.nl/httpdocs && php scripts/check_radius_orphans.php"
```

**Expected output:**
```
✅ No orphaned entries in local nas table
Server: FreeRADIUS Primary
  ℹ️  No entries found
```

## Technical Details

### How Orphans Occur
Orphaned entries can occur when:
1. A BVI interface is deleted but the RADIUS entry remains (before CASCADE was implemented)
2. Manual database edits bypass the application logic
3. Sync failures between local and remote RADIUS databases

### What Gets Deleted
- Any `nas` entry where `bvi_interface_id` points to a non-existent row in `cin_switch_bvi_interfaces`
- Entries with `bvi_interface_id = NULL` are **NOT** deleted (these are legacy/manual entries)

### RADIUS Database Configuration
The scripts read RADIUS server configuration from the `radius_server_config` table in `kea_db`:
- Server hostname, port, database name
- Credentials (automatically decrypted)
- Enabled/disabled status

## Safety Features

### Check Script (`check_radius_orphans.php`)
- **Read-only** - Makes no changes
- Safe to run anytime
- No impact on production

### Clean Script (`clean_radius_orphans.php`)
- Validates BVI interfaces exist before deleting
- Reports each deletion
- Operates on both local and remote databases
- Skips disabled RADIUS servers

## Maintenance Schedule

**Recommended:**
- Run `check_radius_orphans.php` weekly
- Run `clean_radius_orphans.php` only when orphans are detected
- After major database operations or migrations

## Troubleshooting

### No orphans found but RADIUS not working
- Check RADIUS server connectivity
- Verify credentials in `radius_server_config` table
- Check RADIUS server logs

### Script fails to connect to RADIUS server
- Verify server is enabled: `SELECT * FROM radius_server_config;`
- Test database credentials manually
- Check network connectivity

### Entries not getting deleted
- Verify you have DELETE permissions on RADIUS databases
- Check for foreign key constraints on remote RADIUS `nas` tables

## Related Files
- `src/Helpers/RadiusDatabaseSync.php` - RADIUS sync helper
- `src/Models/RadiusClient.php` - RADIUS client model
- `database/migrations/fix_foreign_keys_and_orphans.sql` - Foreign key migration
