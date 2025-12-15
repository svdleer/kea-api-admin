-- Create cin_bvi_dhcp_core table to link BVI interfaces with DHCP subnets
-- This table stores the relationship between CIN switch BVI interfaces and Kea DHCP subnets

CREATE TABLE IF NOT EXISTS `cin_bvi_dhcp_core` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bvi_interface_id` int(11) NOT NULL COMMENT 'Foreign key to cin_switch_bvi_interfaces.id',
  `switch_id` int(11) NOT NULL COMMENT 'Foreign key to cin_switches.id',
  `kea_subnet_id` int(11) NOT NULL COMMENT 'Kea subnet ID from dhcp6_subnet table',
  `interface_number` int(11) NOT NULL DEFAULT 0 COMMENT 'BVI interface number (0 = BVI100, 1 = BVI101, etc.)',
  `ipv6_address` varchar(45) DEFAULT NULL COMMENT 'IPv6 address of the BVI interface',
  `start_address` varchar(45) DEFAULT NULL COMMENT 'Start address of DHCP pool',
  `end_address` varchar(45) DEFAULT NULL COMMENT 'End address of DHCP pool',
  `ccap_core` varchar(255) DEFAULT NULL COMMENT 'CCAP core address',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bvi_subnet` (`bvi_interface_id`, `kea_subnet_id`),
  KEY `idx_switch_id` (`switch_id`),
  KEY `idx_kea_subnet_id` (`kea_subnet_id`),
  KEY `idx_bvi_interface_id` (`bvi_interface_id`),
  CONSTRAINT `fk_cin_bvi_dhcp_core_bvi_interface` 
    FOREIGN KEY (`bvi_interface_id`) 
    REFERENCES `cin_switch_bvi_interfaces` (`id`) 
    ON DELETE CASCADE,
  CONSTRAINT `fk_cin_bvi_dhcp_core_switch` 
    FOREIGN KEY (`switch_id`) 
    REFERENCES `cin_switches` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
  COMMENT='Links CIN switch BVI interfaces with Kea DHCP subnets and CCAP cores';
