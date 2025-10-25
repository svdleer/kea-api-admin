-- Fix BVI interface numbers to start from 100
-- This updates any BVI interface with number less than 100 to be 100 or higher

-- First, check what needs to be fixed
SELECT id, switch_id, interface_number, ipv6_address 
FROM cin_switch_bvi_interfaces 
WHERE interface_number < 100
ORDER BY switch_id, interface_number;

-- Update interface numbers that are less than 100
-- If interface_number is 0, set it to 100
-- If there are multiple interfaces with low numbers, increment from 100
UPDATE cin_switch_bvi_interfaces 
SET interface_number = 100 
WHERE interface_number = 0;

-- For any other interfaces with numbers less than 100 on the same switch,
-- set them to 101, 102, etc.
-- You may need to run this manually per switch if there are multiple

-- Verify the changes
SELECT id, switch_id, interface_number, ipv6_address 
FROM cin_switch_bvi_interfaces 
ORDER BY switch_id, interface_number;
