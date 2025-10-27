# Database Migrations

## Running Migrations

To create the required database tables, run the following SQL files in order:

### 1. Create Users Table
```bash
mysql -u [username] -p kea_db < create_users_table.sql
```

### 2. Create API Keys Table
```bash
mysql -u [username] -p kea_db < create_api_keys_table.sql
```

### 3. Create IPv6 Subnets Table
```bash
mysql -u [username] -p kea_db < create_ipv6_subnets_table.sql
```

### 4. Create CIN Switch BVI Interfaces Table
```bash
mysql -u [username] -p kea_db < create_cin_switch_bvi_interfaces_table.sql
```

### 5. Fix interface_number Datatype (October 2025)
```bash
mysql -u [username] -p kea_db < fix_interface_number_datatype.sql
``

## Run All Migrations at Once

```bash
cat create_users_table.sql create_api_keys_table.sql create_ipv6_subnets_table.sql create_cin_switch_bvi_interfaces_table.sql fix_interface_number_datatype.sql | mysql -u [username] -p kea_db
```

## Run Single Migration Fix

If you need to fix the interface_number column type from VARCHAR to INT(11):

```bash
mysql -u [username] -p kea_db < fix_interface_number_datatype.sql
```

Or run directly in MySQL:

```sql
USE kea_db;

ALTER TABLE cin_bvi_dhcp_core 
MODIFY COLUMN interface_number INT(11) NOT NULL;

ALTER TABLE cin_switch_bvi_interfaces 
MODIFY COLUMN interface_number INT(11) NOT NULL;
```

## Quick Fix for Missing ipv6_subnets Table

If you're getting the error "Table 'kea_db.ipv6_subnets' doesn't exist", run:

```bash
mysql -u [username] -p kea_db < create_ipv6_subnets_table.sql
```

Or directly in MySQL:

```sql
USE kea_db;

CREATE TABLE IF NOT EXISTS ipv6_subnets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prefix VARCHAR(43) NOT NULL UNIQUE,
    bvi_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (bvi_id) REFERENCES switches(id) ON DELETE CASCADE
);
```
