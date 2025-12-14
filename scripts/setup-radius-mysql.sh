#!/bin/bash
# Setup FreeRADIUS with MySQL on Kea VM
# Run this script on EACH Kea VM (172.16.6.222 and 172.17.7.222)

set -e

echo "========================================="
echo "FreeRADIUS + MySQL Setup for Kea Server"
echo "========================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)" 
   exit 1
fi

# Prompt for RADIUS password
read -sp "Enter password for RADIUS MySQL user: " RADIUS_PASSWORD
echo ""
read -sp "Confirm password: " RADIUS_PASSWORD_CONFIRM
echo ""

if [ "$RADIUS_PASSWORD" != "$RADIUS_PASSWORD_CONFIRM" ]; then
    echo "Passwords don't match!"
    exit 1
fi

# Get Kea API Admin server IP
KEA_API_IP=${KEA_API_IP:-172.16.6.101}
echo "Kea API Admin server IP: $KEA_API_IP"

echo ""
echo "Step 1: Installing packages..."
apt update
apt install -y mysql-server freeradius freeradius-mysql freeradius-utils

echo ""
echo "Step 2: Creating RADIUS database..."
mysql <<EOF
-- Create RADIUS database
CREATE DATABASE IF NOT EXISTS radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create RADIUS user
CREATE USER IF NOT EXISTS 'radius'@'localhost' IDENTIFIED BY '$RADIUS_PASSWORD';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';

-- Allow remote access from Kea API Admin server
CREATE USER IF NOT EXISTS 'radius'@'$KEA_API_IP' IDENTIFIED BY '$RADIUS_PASSWORD';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'$KEA_API_IP';

-- For Docker network access
CREATE USER IF NOT EXISTS 'radius'@'172.18.%' IDENTIFIED BY '$RADIUS_PASSWORD';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'172.18.%';

FLUSH PRIVILEGES;
EOF

echo ""
echo "Step 3: Creating RADIUS tables..."
mysql radius <<'EOF'
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

SHOW TABLES;
EOF

echo ""
echo "Step 4: Configuring MySQL to allow remote connections..."
# Backup original config
cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf.backup

# Update bind-address
sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf

# Restart MySQL
systemctl restart mysql

echo ""
echo "Step 5: Configuring FreeRADIUS to use MySQL..."

# Enable SQL module
ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# Configure SQL connection
cat > /etc/freeradius/3.0/mods-enabled/sql <<EOF
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"
    
    server = "localhost"
    port = 3306
    login = "radius"
    password = "$RADIUS_PASSWORD"
    radius_db = "radius"
    
    # Read clients from database
    read_clients = yes
    client_table = "nas"
}
EOF

echo ""
echo "Step 6: Testing FreeRADIUS configuration..."
if freeradius -C; then
    echo "✓ FreeRADIUS configuration is valid"
else
    echo "✗ FreeRADIUS configuration has errors"
    exit 1
fi

echo ""
echo "Step 7: Starting FreeRADIUS..."
systemctl restart freeradius
systemctl enable freeradius

echo ""
echo "========================================="
echo "✓ Setup Complete!"
echo "========================================="
echo ""
echo "RADIUS database created: radius"
echo "RADIUS user: radius"
echo "RADIUS password: (the one you entered)"
echo ""
echo "Services started:"
echo "  - MySQL (listening on 0.0.0.0:3306)"
echo "  - FreeRADIUS (listening on UDP 1812/1813)"
echo ""
echo "Next steps:"
echo "1. Configure Kea API Admin to sync to this server"
echo "2. Add NAS clients via Kea API Admin web UI"
echo ""
echo "Test RADIUS:"
echo "  radtest testing PAP123 localhost 0 testing123"
echo ""
