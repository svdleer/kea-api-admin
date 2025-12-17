-- Create table for dedicated subnets (subnets without BVI association)
CREATE TABLE IF NOT EXISTS `dedicated_subnets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'User-friendly name for the subnet',
  `kea_subnet_id` int(11) NOT NULL COMMENT 'Subnet ID in Kea DHCP',
  `subnet` varchar(43) NOT NULL COMMENT 'IPv6 subnet in CIDR notation',
  `pool_start` varchar(39) DEFAULT NULL COMMENT 'Pool start address',
  `pool_end` varchar(39) DEFAULT NULL COMMENT 'Pool end address',
  `ccap_core` varchar(255) DEFAULT NULL COMMENT 'CCAP core servers',
  `description` text DEFAULT NULL COMMENT 'Optional description',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_kea_subnet` (`kea_subnet_id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dedicated subnets without BVI association';
