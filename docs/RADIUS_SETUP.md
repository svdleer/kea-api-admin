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

SSH to **Kea VM 1 (172.16.6.222):**
```bash
ssh root@172.16.6.222
```

Run these commands:
```bash
# Update package lists
apt update

# Install MySQL server
apt install -y mysql-server

# Install FreeRADIUS with MySQL support
apt install -y freeradius freeradius-mysql freeradius-utils

# Secure MySQL (set root password, remove test databases)
mysql_secure_installation
```

Repeat the same steps on **Kea VM 2 (172.17.7.222)**:
```bash
ssh root@172.17.7.222
# Run the same apt commands above
```

---

## Step 2: Configure MySQL to Allow Remote Connections

On **EACH** Kea VM, edit the MySQL configuration file:

```bash
vim /etc/mysql/mysql.conf.d/mysqld.cnf
```

Find the line with `bind-address` and change it to:
```ini
[mysqld]
bind-address = 0.0.0.0
```

**Save and exit** (`:wq` in vim), then restart MySQL:
```bash
tor```

Verify MySQL is listening on all interfaces:
```bash
netstat -tlnp | grep 3306
# Should show: 0.0.0.0:3306
```

---

## Step 3: Create RADIUS Database and Users

## Step 3: Create RADIUS Database and Users

On **EACH** Kea VM, log into MySQL:

```bash
mysql -u root -p
```

Copy and paste these SQL commands:

```sql
-- Create RADIUS database (Kea database should already exist)
CREATE DATABASE IF NOT EXISTS radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create RADIUS users (MySQL 8.0+ requires separate CREATE and GRANT)
CREATE USER IF NOT EXISTS 'radius'@'localhost' IDENTIFIED BY 'your_secure_radius_password';
CREATE USER IF NOT EXISTS 'radius'@'172.16.6.101' IDENTIFIED BY 'your_secure_radius_password';
CREATE USER IF NOT EXISTS 'radius'@'172.18.%' IDENTIFIED BY 'your_secure_radius_password';

-- Grant privileges to all RADIUS users
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'172.16.6.101';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'172.18.%';

-- Apply privileges
FLUSH PRIVILEGES;

-- Verify databases exist
SHOW DATABASES;
-- You should see: kea, radius

-- Verify users
SELECT User, Host FROM mysql.user WHERE User = 'radius';
-- You should see: radius@localhost, radius@172.16.6.101, radius@172.18.%

EXIT;
```

**Important:** Replace `'your_secure_radius_password'` with a strong password! Use the same password on both VMs.

---

## Step 4: Create RADIUS Database Tables

On **EACH** Kea VM, create the required tables. Log into MySQL:

```bash
mysql -u radius -p radius
# Enter the RADIUS password you created in Step 3
```

Copy and paste these SQL commands:

```sql
-- NAS (Network Access Server) clients table
-- This stores your switches/access points
CREATE TABLE IF NOT EXISTS nas (
  id int(10) NOT NULL auto_increment,
  nasname varchar(128) NOT NULL COMMENT 'IP address of switch/AP',
  shortname varchar(32) COMMENT 'Friendly name',
  type varchar(30) DEFAULT 'other' COMMENT 'Device type',
  ports int(5) COMMENT 'Number of ports',
  secret varchar(60) NOT NULL COMMENT 'RADIUS shared secret',
  server varchar(64),
  community varchar(50),
  description varchar(200) DEFAULT 'RADIUS Client',
  PRIMARY KEY (id),
  KEY nasname (nasname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RADIUS NAS Clients';

-- RADIUS accounting table  
-- This stores authentication/accounting records
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
  acctinputoctets bigint(20) default NULL,
  acctoutputoctets bigint(20) default NULL,
  PRIMARY KEY (radacctid),
  UNIQUE KEY acctuniqueid (acctuniqueid),
  KEY username (username),
  KEY acctstarttime (acctstarttime),
  KEY acctstoptime (acctstoptime),
  KEY nasipaddress (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RADIUS Accounting';

-- Verify tables were created
SHOW TABLES;
-- You should see: nas, radacct

-- Check table structure
DESCRIBE nas;
DESCRIBE radacct;

EXIT;
```

---

## Step 5: Configure FreeRADIUS to Use MySQL

On **EACH** Kea VM, enable the SQL module:

```bash
# Create symlink to enable SQL module
ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
```

Edit the SQL configuration:

```bash
vim /etc/freeradius/3.0/mods-enabled/sql
```

Find and update these settings (around line 15-40):

```conf
sql {
    # Use MySQL driver
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    # Connection info
    server = "localhost"
    port = 3306
    login = "radius"
    password = "your_secure_radius_password"  # Use the password from Step 3
    radius_db = "radius"
    
    # Read NAS clients from database (IMPORTANT!)
    read_clients = yes
    client_table = "nas"
}
```

**Save and exit** (`:wq` in vim).

Test the configuration:

```bash
# Stop FreeRADIUS
systemctl stop freeradius

# Test configuration in debug mode
freeradius -X
```

**Look for these messages:**
- `rlm_sql (sql): Driver rlm_sql_mysql (module rlm_sql_mysql) loaded and linked`
- `rlm_sql (sql): Opening additional spawn of rlm_sql (sql)`
- No error messages about database connection

**Press Ctrl+C** to stop debug mode.

If configuration is OK, start FreeRADIUS:

```bash
# Start and enable FreeRADIUS
systemctl start freeradius
systemctl enable freeradius

# Check status
systemctl status freeradius
```

---

## Step 6: Configure Kea API Admin to Sync RADIUS Clients

On your **Kea API Admin server (172.16.6.101)**, configure the RADIUS server connections.

SSH to the server:
```bash
ssh access-engineering.nl
```

Update the database with your Kea VM details:

```bash
mysql -u kea_user -p kea_db
```

Run these SQL commands:

```sql
-- Configure connection to Kea VM 1 (Sylvester - 172.16.6.222)
UPDATE radius_server_config 
SET name = 'Sylvester RADIUS',
    host = '172.16.6.222', 
    password = 'your_secure_radius_password',
    enabled = 1
WHERE display_order = 0;

-- Configure connection to Kea VM 2 (Speedy - 172.17.7.222)
UPDATE radius_server_config 
SET name = 'Speedy RADIUS',
    host = '172.17.7.222', 
    password = 'your_secure_radius_password',
    enabled = 1
WHERE display_order = 1;

-- Verify the configuration
SELECT id, name, enabled, host, port, database, username 
FROM radius_server_config 
ORDER BY display_order;

EXIT;
```

You should see:
```
+----+------------------+---------+----------------+------+----------+----------+
| id | name             | enabled | host           | port | database | username |
+----+------------------+---------+----------------+------+----------+----------+
|  1 | Sylvester RADIUS |       1 | 172.16.6.222   | 3306 | radius   | radius   |
|  2 | Speedy RADIUS    |       1 | 172.17.7.222   | 3306 | radius   | radius   |
+----+------------------+---------+----------------+------+----------+----------+
```

**Alternative:** Configure via web UI at http://172.16.6.101:8080/admin/radius

---

## Step 7: Test RADIUS Client Sync

Now test that RADIUS clients sync from Kea API Admin to both VMs.

### 7.1: Add a Test NAS Client via Web UI

1. Open browser: **http://172.16.6.101:8080/admin/radius**
2. Click **"Add RADIUS Client"**
3. Fill in:
   - **Name**: Test Switch
   - **IP Address**: 192.168.1.100
   - **Secret**: testing123
   - **Type**: other
   - **Description**: Test RADIUS client
4. Click **"Save"**

### 7.2: Verify Sync on Kea VM 1

SSH to Kea VM 1:
```bash
ssh root@172.16.6.222
```

Check if the client was synced:
```bash
mysql -u radius -p radius -e "SELECT id, nasname, shortname, secret, description FROM nas;"
```

You should see:
```
+----+---------------+-------------+------------+--------------------+
| id | nasname       | shortname   | secret     | description        |
+----+---------------+-------------+------------+--------------------+
|  1 | 192.168.1.100 | Test Switch | testing123 | Test RADIUS client |
+----+---------------+-------------+------------+--------------------+
```

### 7.3: Verify Sync on Kea VM 2

SSH to Kea VM 2:
```bash
ssh root@172.17.7.222
```

Check if the client was synced:
```bash
mysql -u radius -p radius -e "SELECT id, nasname, shortname, secret, description FROM nas;"
```

Should show the same client!

✅ **If you see the client on both VMs, sync is working!**

---

## Step 8: Test RADIUS Authentication

On **EACH** Kea VM, test that RADIUS is working with the NAS client.

```bash
# Install radtest if not already installed
apt install -y freeradius-utils

# Test RADIUS authentication
radtest testing PAP123 localhost 0 testing123
```

**Expected output:**
```
Sent Access-Request Id 123 from 0.0.0.0:12345 to 127.0.0.1:1812 length 73
Received Access-Accept Id 123 from 127.0.0.1:1812 to 0.0.0.0:12345 length 20
```

If you get `Access-Reject` or connection errors, check:
- FreeRADIUS is running: `systemctl status freeradius`
- Database connection: `mysql -u radius -p radius`
- Debug mode: `systemctl stop freeradius && freeradius -X`

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
