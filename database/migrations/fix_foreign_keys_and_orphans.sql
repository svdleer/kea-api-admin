-- Migration: Fix Foreign Keys and Clean Orphaned Records
-- Date: 2025-10-28
-- Purpose: Add proper foreign key constraints and clean up orphaned data

-- Step 1: Clean up orphaned cin_bvi_dhcp_core records (no matching BVI interface)
DELETE FROM cin_bvi_dhcp_core
WHERE NOT EXISTS (
    SELECT 1 FROM cin_switch_bvi_interfaces
    WHERE cin_switch_bvi_interfaces.switch_id = cin_bvi_dhcp_core.switch_id
    AND cin_switch_bvi_interfaces.interface_number = cin_bvi_dhcp_core.interface_number
);

-- Step 2: Add foreign key from cin_switch_bvi_interfaces to cin_switches
-- First check if constraint exists
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cin_switch_bvi_interfaces'
    AND CONSTRAINT_NAME = 'fk_bvi_switch'
);

-- Add foreign key if it doesn't exist
SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE cin_switch_bvi_interfaces 
     ADD CONSTRAINT fk_bvi_switch 
     FOREIGN KEY (switch_id) 
     REFERENCES cin_switches(id) 
     ON DELETE CASCADE',
    'SELECT "Foreign key fk_bvi_switch already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add a unique BVI interface ID column to cin_bvi_dhcp_core if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cin_bvi_dhcp_core'
    AND COLUMN_NAME = 'bvi_interface_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE cin_bvi_dhcp_core 
     ADD COLUMN bvi_interface_id INT(11) NULL AFTER id',
    'SELECT "Column bvi_interface_id already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Populate bvi_interface_id based on switch_id and interface_number
UPDATE cin_bvi_dhcp_core d
INNER JOIN cin_switch_bvi_interfaces b 
    ON d.switch_id = b.switch_id 
    AND d.interface_number = b.interface_number
SET d.bvi_interface_id = b.id
WHERE d.bvi_interface_id IS NULL;

-- Step 5: Add foreign key from cin_bvi_dhcp_core to cin_switch_bvi_interfaces
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cin_bvi_dhcp_core'
    AND CONSTRAINT_NAME = 'fk_dhcp_core_bvi'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE cin_bvi_dhcp_core 
     ADD CONSTRAINT fk_dhcp_core_bvi 
     FOREIGN KEY (bvi_interface_id) 
     REFERENCES cin_switch_bvi_interfaces(id) 
     ON DELETE CASCADE',
    'SELECT "Foreign key fk_dhcp_core_bvi already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 6: Make bvi_interface_id NOT NULL after data is populated
SET @column_nullable = (
    SELECT IS_NULLABLE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cin_bvi_dhcp_core'
    AND COLUMN_NAME = 'bvi_interface_id'
);

SET @sql = IF(@column_nullable = 'YES',
    'ALTER TABLE cin_bvi_dhcp_core 
     MODIFY COLUMN bvi_interface_id INT(11) NOT NULL',
    'SELECT "Column bvi_interface_id already NOT NULL" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 7: Add unique constraint on kea_subnet_id (one subnet = one BVI)
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cin_bvi_dhcp_core'
    AND CONSTRAINT_NAME = 'unique_kea_subnet'
);

-- Remove duplicates first (keep the most recent one)
DELETE d1 FROM cin_bvi_dhcp_core d1
INNER JOIN cin_bvi_dhcp_core d2 
WHERE d1.kea_subnet_id = d2.kea_subnet_id 
AND d1.kea_subnet_id IS NOT NULL
AND d1.id < d2.id;

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE cin_bvi_dhcp_core 
     ADD UNIQUE KEY unique_kea_subnet (kea_subnet_id)',
    'SELECT "Unique key unique_kea_subnet already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification queries
SELECT 'Migration Complete!' as status;

SELECT 'Checking for orphaned records...' as check_step;

SELECT 
    COUNT(*) as orphaned_dhcp_core_count
FROM cin_bvi_dhcp_core d
LEFT JOIN cin_switch_bvi_interfaces b ON d.bvi_interface_id = b.id
WHERE b.id IS NULL;

SELECT 
    COUNT(*) as orphaned_bvi_count
FROM cin_switch_bvi_interfaces b
LEFT JOIN cin_switches s ON b.switch_id = s.id
WHERE s.id IS NULL;

SELECT 'Foreign keys added successfully' as final_status;
