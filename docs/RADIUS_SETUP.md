# FreeRADIUS and Kea Setup with Local MySQL on Each Kea Server

This guide helps you set up a fully redundant system where each Kea DHCP server has its own local MySQL database for both DHCP leases and RADIUS.

## Architecture

- **Main Server (172.16.6.101)**: Kea API Admin (management only) + main MySQL
- **Kea VM 1 (172.16.6.222)**: Kea DHCP + FreeRADIUS + **local MySQL** (for Kea leases + RADIUS)
- **Kea VM 2 (172.17.7.222)**: Kea DHCP + FreeRADIUS + **local MySQL** (for Kea leases + RADIUS)

**Benefits:**
- Each Kea server is fully independent with its own MySQL database
- Kea DHCP stores leases in local MySQL (survives network issues)
- FreeRADIUS uses same local MySQL (survives network issues)
- Kea API Admin syncs RADIUS clients to both servers
- Main MySQL server failure doesn't affect DHCP or RADIUS operations

---

## Step 1: Install FreeRADIUS and MySQL on Each Kea VM

Run these commands on **BOTH** Kea VMs (172.16.6.222 and 172.17.7.222):

```bash
# Update system
sudo apt update

# Install MySQL server
sudo apt install -y mysql-server

# Install FreeRADIUS with MySQL support
sudo apt install -y freeradius freeradius-mysql freeradius-utils

# Secure MySQL installation
sudo mysql_secure_installation
```

---

## Step 2: Create RADIUS Database on Each Kea VM

On **EACH** Kea VM, create the RADIUS database (same MySQL that Kea DHCP uses):

```bash
sudo mysql
```

```sql
-- Create RADIUS database (Kea database should already exist)
CREATE DATABASE IF NOT EXISTS radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create RADIUS user
CREATE USER IF NOT EXISTS 'radius'@'localhost' IDENTIFIED BY 'your_secure_radius_password';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';

-- Allow remote access from Kea API Admin server (for syncing RADIUS clients)
CREATE USER IF NOT EXISTS 'radius'@'172.16.6.101' IDENTIFIED BY 'your_secure_radius_password';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'172.16.6.101';

-- For Docker network access from Kea API Admin
CREATE USER IF NOT EXISTS 'radius'@'172.18.%' IDENTIFIED BY 'your_secure_radius_password';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'172.18.%';

-- Verify databases
SHOW DATABASES;
-- You should see: kea, radius

FLUSH PRIVILEGES;
EXIT;
```

**Allow remote MySQL connections:**

```bash
sudo vim /etc/mysql/mysql.conf.d/mysqld.cnf
```

Change:
```ini
bind-address = 0.0.0.0
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

---

## Step 3: Import FreeRADIUS Schema

On **EACH** Kea VM:

```bash
# Import FreeRADIUS MySQL schema
sudo mysql radius < /etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql
```

If the schema file doesn't exist, use this minimal schema:

```bash
sudo mysql radius
```

```sql
-- NAS (Network Access Server) clients table
CREATE TABLE IF NOT EXISTS nas (
  id int(10) NOT NULL auto_increment,
  nasname varchar(128) NOT NULL,
  shortname varchar(32),
  type varchar(30) DEFAULT 'other',
  ports int(5),
  secret varchar(60) NOT NULL,
  server varchar(64),
  community varchar(50),
  description varchar(200) DEFAULT 'RADIUS Client',
  PRIMARY KEY (id),
  KEY nasname (nasname)
);

-- RADIUS accounting table
CREATE TABLE IF NOT EXISTS radacct (
  radacctid bigint(21) NOT NULL auto_increment,
  acctsessionid varchar(64) NOT NULL default '',
  acctuniqueid varchar(32) NOT NULL default '',
  username varchar(64) NOT NULL default '',
  nasipaddress varchar(15) NOT NULL default '',
  nasportid varchar(15) default NULL,
  acctstarttime datetime NULL default NULL,
  acctupdatetime datetime NULL default NULL,
  acctstoptime datetime NULL default NULL,
  acctsessiontime int(12) unsigned default NULL,
  PRIMARY KEY (radacctid),
  UNIQUE KEY acctuniqueid (acctuniqueid),
  KEY username (username),
  KEY acctstarttime (acctstarttime),
  KEY acctstoptime (acctstoptime),
  KEY nasipaddress (nasipaddress)
);

-- Check tables were created
SHOW TABLES;
EXIT;
```

---

## Step 4: Configure FreeRADIUS to Use MySQL

On **EACH** Kea VM:

```bash
# Enable SQL module
sudo ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# Edit SQL configuration
sudo vim /etc/freeradius/3.0/mods-enabled/sql
```

Update these settings:
```conf
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    server = "localhost"
    port = 3306
    login = "radius"
    password = "your_secure_radius_password"
    radius_db = "radius"
    
    # Read clients from database
    read_clients = yes
    client_table = "nas"
}
```

**Test FreeRADIUS configuration:**
```bash
sudo freeradius -X
# Press Ctrl+C after checking for errors
```

**Restart FreeRADIUS:**
```bash
sudo systemctl restart freeradius
sudo systemctl enable freeradius
```

---

## Step 5: Configure Kea API Admin to Sync to Both RADIUS Databases

On your **Kea API Admin server**, update the RADIUS server configuration in the database:

```bash
ssh access-engineering.nl
mysql -u kea_user -p kea_db
```

```sql
-- Update RADIUS server configs to point to each Kea VM
UPDATE radius_server_config 
SET host = '172.16.6.222', 
    password = 'your_secure_radius_password',
    enabled = 1
WHERE display_order = 0;

UPDATE radius_server_config 
SET host = '172.17.7.222', 
    password = 'your_secure_radius_password',
    enabled = 1
WHERE display_order = 1;

SELECT * FROM radius_server_config;
EXIT;
```

Or configure via the web UI at: **http://172.16.6.101:8080/admin/radius**

---

## Step 6: Test RADIUS NAS Sync

1. **Access Kea API Admin:** http://172.16.6.101:8080/admin/radius
2. **Add a test NAS client:**
   - Name: Test Switch
   - IP Address: 192.168.1.100
   - Secret: testing123
   - Type: other

3. **Check both RADIUS databases:**

On Kea VM 1:
```bash
mysql -u radius -p radius -e "SELECT * FROM nas;"
```

On Kea VM 2:
```bash
mysql -u radius -p radius -e "SELECT * FROM nas;"
```

Both should show the same NAS client!

---

## Step 7: Test RADIUS Authentication

On **EACH** Kea VM:

```bash
# Test with radtest (use your switch's credentials)
radtest testing PAP123 localhost 0 testing123

# Should return: Access-Accept (if credentials are valid)
```

---

## Kea DHCP Local MySQL Configuration

Your Kea DHCP servers are already configured to use local MySQL on each VM. The configuration is typically in:

```bash
# Check Kea configuration
cat /etc/kea/kea-dhcp4.conf | grep -A10 '"lease-database"'
```

**Example Kea configuration with local MySQL:**
```json
{
  "Dhcp4": {
    "lease-database": {
      "type": "mysql",
      "host": "localhost",
      "name": "kea",
      "user": "kea",
      "password": "kea_password"
    }
  }
}
```

**Important:** Since each Kea server has its own MySQL database:
- Leases are **NOT** shared between servers
- Each server manages its own IP pool independently
- This is typical for Kea HA failover mode
- Main server (Sylvester - 172.16.6.222) handles most requests
- Secondary server (Speedy - 172.17.7.222) takes over during failover

---

## High Availability Notes

✅ **Benefits of this fully local setup:**
- **Kea DHCP:** Each server stores leases in local MySQL
  - Network issues to main server don't affect DHCP
  - Faster lease lookups (no network latency)
  - Each server operates independently
  
- **RADIUS:** Each server has local NAS client database
  - Network issues don't affect 802.1X authentication
  - RADIUS continues working during main server outage
  - Automatic sync from Kea API Admin to both servers

- **Full Independence:** Each Kea VM is self-contained
  - Local MySQL for both Kea leases and RADIUS
  - Survives network partitions
  - No single point of failure

⚠️ **Important:**
- **RADIUS clients:** Only manage through Kea API Admin web UI (synced to both VMs)
- **Kea leases:** NOT shared between servers (each manages own pool)
- **Kea configuration:** Managed separately on each VM
- **Main MySQL (172.16.6.101):** Used only for Kea API Admin management data

---

## Troubleshooting

### RADIUS clients not syncing:
```bash
# Check Kea API Admin logs
ssh access-engineering.nl "cd ~/git/kea-api-admin && docker-compose logs --tail=50 kea-api-admin | grep -i radius"
```

### FreeRADIUS errors:
```bash
# Run in debug mode
sudo systemctl stop freeradius
sudo freeradius -X
```

### MySQL connection refused:
```bash
# Check if MySQL is listening on all interfaces
netstat -tlnp | grep 3306

# Test connection from Kea API Admin
mysql -h 172.16.6.222 -u radius -p radius
```

---

**Your setup will be complete!** 🎉
