-- Migration: Fix interface_number column datatype in cin_bvi_dhcp_core
-- Changes interface_number from VARCHAR to INT(11)
-- Date: 2025-10-27

-- Change interface_number to INT(11) in cin_bvi_dhcp_core table
ALTER TABLE cin_bvi_dhcp_core 
MODIFY COLUMN interface_number INT(11) NOT NULL;

-- Also ensure it's INT(11) in cin_switch_bvi_interfaces table
ALTER TABLE cin_switch_bvi_interfaces 
MODIFY COLUMN interface_number INT(11) NOT NULL;

SELECT 'Migration completed: interface_number changed to INT(11)' as status;
