-- Fix DHCP subnet to reference correct BVI interface ID
-- This updates the cin_bvi_dhcp_core table to use the correct BVI interface ID

-- First, check what needs to be fixed
SELECT 
    dhcp.id as dhcp_id,
    dhcp.kea_subnet_id,
    dhcp.switch_id,
    dhcp.interface_number,
    dhcp.ipv6_address,
    bvi.id as correct_bvi_id
FROM cin_bvi_dhcp_core dhcp
JOIN cin_switch_bvi_interfaces bvi 
    ON dhcp.switch_id = bvi.switch_id 
    AND dhcp.interface_number = bvi.interface_number
    AND dhcp.ipv6_address = bvi.ipv6_address
WHERE dhcp.id != bvi.id;

-- If records are found above, run this to fix them:
UPDATE cin_bvi_dhcp_core dhcp
JOIN cin_switch_bvi_interfaces bvi 
    ON dhcp.switch_id = bvi.switch_id 
    AND dhcp.interface_number = bvi.interface_number
    AND dhcp.ipv6_address = bvi.ipv6_address
SET dhcp.id = bvi.id
WHERE dhcp.id != bvi.id;
