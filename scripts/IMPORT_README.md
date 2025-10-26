# KEA Configuration Import Script

This script imports existing DHCPv6 configuration from Kea's `kea-dhcp6.conf` file into the database, making it manageable through the web UI.

## What It Imports

### ✅ Subnets
- Subnet prefixes (e.g., `2001:db8::/64`)
- Subnet IDs
- Address pools
- Interface bindings
- Timers (valid/preferred lifetime)
- Rapid commit settings

### ✅ Host Reservations (Static Leases)
- DUID/MAC-based reservations
- Reserved IPv6 addresses
- Hostnames
- Per-subnet reservations

### ✅ DHCPv6 Options
- Global options
- Subnet-specific options
- Custom option definitions
- Option data

## Usage

### Basic Usage

```bash
# Import from default Kea config location
php scripts/import_kea_config.php

# Import from specific file
php scripts/import_kea_config.php /path/to/kea-dhcp6.conf

# With full path
php /var/www/html/kea-api-admin/scripts/import_kea_config.php /etc/kea/kea-dhcp6.conf
```

### Example Output

```
╔═══════════════════════════════════════════════════════════╗
║      KEA DHCPv6 Configuration Import Script               ║
║      Import subnets, pools, and reservations              ║
╚═══════════════════════════════════════════════════════════╝

Reading configuration from: /etc/kea/kea-dhcp6.conf

Importing Option Definitions...
  ✓ Imported option definition: vendor-class (code 16)
  ✓ Imported option definition: vendor-opts (code 17)

Importing Global Options...

Importing Subnets...

  Processing subnet: 2001:db8:1::/64 (ID: 1)
    ✓ Imported subnet: 2001:db8:1::/64
      Pools: 2001:db8:1::100-2001:db8:1::200
    Importing reservations...
      ✓ Imported reservation: 2001:db8:1::50 (server01)
      ✓ Imported reservation: 2001:db8:1::51 (server02)

  Processing subnet: 2001:db8:2::/64 (ID: 2)
    ✓ Imported subnet: 2001:db8:2::/64
      Pools: 2001:db8:2::100-2001:db8:2::200

╔═══════════════════════════════════════════════════════════╗
║                    Import Summary                         ║
╚═══════════════════════════════════════════════════════════╝
Subnets:            2 imported, 0 skipped
Reservations:       2 imported, 0 skipped
Options:            2 imported, 0 skipped

Total Imported: 6
```

## Before Running

### 1. Prepare Kea Configuration File

The script expects valid JSON. If your `kea-dhcp6.conf` has comments, you have two options:

**Option A: Remove comments manually**
```bash
# Create a clean version without comments
grep -v '^\s*#' /etc/kea/kea-dhcp6.conf | grep -v '^\s*//' > /tmp/kea-dhcp6-clean.conf
php scripts/import_kea_config.php /tmp/kea-dhcp6-clean.conf
```

**Option B: Use Kea's config-get command**
```bash
# Get running config via Kea API (clean JSON)
curl -X POST http://localhost:8000/ \
  -H "Content-Type: application/json" \
  -d '{"command": "config-get", "service": ["dhcp6"]}' \
  | jq '.arguments' > /tmp/kea-config.json

php scripts/import_kea_config.php /tmp/kea-config.json
```

### 2. Backup Database

Always backup before importing:

```bash
mysqldump -u kea_db_user -p kea_db > backup_before_import.sql
```

### 3. Check Database Connection

Ensure `.env` file has correct database credentials:

```
DB_HOST=localhost
DB_NAME=kea_db
DB_USER=kea_db_user
DB_PASSWORD=your_password
```

## What Happens During Import

### Duplicate Detection
- **Subnets**: Checks by subnet prefix - skips if exists
- **Reservations**: Checks by subnet ID + IP address - skips if exists
- **Options**: Checks by code + scope - skips if exists

### Data Mapping

| Kea Config | Database Table | Notes |
|------------|----------------|-------|
| `subnet6[].subnet` | `ipv6_subnets.subnet` | Subnet prefix |
| `subnet6[].pools[]` | `ipv6_subnets.pools` | JSON array of pools |
| `subnet6[].reservations[]` | `hosts` | Host reservations |
| `option-def[]` | `dhcp6_option_def` | Custom options |
| `option-data[]` | `dhcp6_options` | Option values |

### Limitations

⚠️ **The following are NOT automatically imported:**

1. **BVI Interface Links**: Imported subnets won't be linked to BVI interfaces. You must:
   - Go to web UI → Subnets
   - Edit each subnet
   - Link to appropriate BVI interface

2. **Kea Control Socket**: Not imported (must configure manually)

3. **Hooks Libraries**: Not imported (e.g., lease_cmds, host_cmds)

4. **Advanced Options**: 
   - Client classes
   - Relay information
   - Shared networks
   - PD pools (prefix delegation)

## After Import

### 1. Verify Imported Data

```bash
# Check imported subnets
mysql -u kea_db_user -p kea_db -e "SELECT id, subnet, pools FROM ipv6_subnets;"

# Check imported reservations
mysql -u kea_db_user -p kea_db -e "SELECT dhcp6_subnet_id, inet6_ntoa(ipv6_address) as ip, hostname FROM hosts;"

# Check imported options
mysql -u kea_db_user -p kea_db -e "SELECT code, name, data FROM dhcp6_options;"
```

### 2. Link Subnets to BVI Interfaces

Via Web UI:
1. Go to **DHCP** → **Subnets**
2. Edit each imported subnet
3. Select the corresponding BVI interface
4. Save

Via SQL:
```sql
UPDATE ipv6_subnets 
SET bvi_interface_id = (SELECT id FROM cin_switch_bvi_interfaces WHERE ipv6_address = '2001:db8:1::1') 
WHERE subnet = '2001:db8:1::/64';
```

### 3. Test Configuration

1. Open web UI: `https://kea.useless.nl/dhcp/subnets`
2. Verify all subnets appear
3. Check subnet details (pools, options)
4. Verify reservations in **Leases** page

### 4. Push to Kea Servers

If everything looks good, push the configuration:

```bash
# Via web UI
Go to Dashboard → Click "Sync to Kea Primary"

# Or via API
curl -X POST https://kea.useless.nl/api/dhcp/subnets/sync \
  -H "X-API-Key: your-api-key"
```

## Troubleshooting

### "Failed to parse configuration file"

**Cause**: Comments in JSON file

**Solution**: Remove comments or use `config-get` API method (see above)

### "Subnet already exists in database"

**Cause**: Subnet was already imported or manually created

**Solution**: This is normal - duplicates are automatically skipped

### "Invalid reservation (missing DUID or IP)"

**Cause**: Incomplete reservation data in config

**Solution**: Check your kea-dhcp6.conf for reservations missing DUID or ip-addresses

### "Permission denied"

**Cause**: Script not executable or database access issues

**Solution**:
```bash
chmod +x scripts/import_kea_config.php
# Check database credentials in .env
```

## Example Kea Config Structure

The script expects this structure:

```json
{
  "Dhcp6": {
    "option-def": [
      {
        "code": 100,
        "name": "custom-option",
        "type": "string"
      }
    ],
    "option-data": [
      {
        "code": 23,
        "data": "2001:db8::1",
        "name": "dns-servers"
      }
    ],
    "subnet6": [
      {
        "id": 1,
        "subnet": "2001:db8:1::/64",
        "pools": [
          { "pool": "2001:db8:1::100-2001:db8:1::200" }
        ],
        "interface": "eth0",
        "option-data": [],
        "reservations": [
          {
            "duid": "00:01:00:01:2a:3b:4c:5d",
            "ip-addresses": ["2001:db8:1::50"],
            "hostname": "server01"
          }
        ]
      }
    ]
  }
}
```

## Safety Features

✅ **Duplicate Detection**: Won't create duplicates  
✅ **Transaction Support**: Rolls back on errors  
✅ **Detailed Logging**: Shows exactly what was imported  
✅ **Skip on Error**: Continues even if one item fails  
✅ **Statistics**: Summary of what was imported/skipped  

## Related Scripts

- `create_admin.php` - Create admin user
- `fix_dhcp_subnet.php` - Fix subnet issues
- `fix_bvi_interface_numbers.sql` - Fix BVI numbering

## Support

For issues:
1. Check script output for specific error messages
2. Verify database connectivity
3. Check Kea config file syntax
4. Review logs: `/logs/error_log`
