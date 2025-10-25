-- BVI Interface Display Mapping
-- The interface_number is stored as 0, 1, 2, etc. in the database
-- But displayed as BVI100, BVI101, BVI102, etc. in the UI
-- Formula: Display = BVI(100 + interface_number)

-- Examples:
-- Database: 0  -> Display: BVI100
-- Database: 1  -> Display: BVI101
-- Database: 2  -> Display: BVI102

-- Check current BVI interface numbers
SELECT 
    id, 
    switch_id, 
    interface_number as stored_value,
    CONCAT('BVI', 100 + interface_number) as display_value,
    ipv6_address 
FROM cin_switch_bvi_interfaces 
ORDER BY switch_id, interface_number;
