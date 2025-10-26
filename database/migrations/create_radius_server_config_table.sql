-- Create table for storing RADIUS server configurations
-- This allows managing FreeRADIUS database connections through the web UI

CREATE TABLE IF NOT EXISTS `radius_server_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Server name (e.g., FreeRADIUS Primary)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this server is enabled',
  `host` varchar(255) NOT NULL COMMENT 'Database host (IP or hostname)',
  `port` int(11) NOT NULL DEFAULT 3306 COMMENT 'Database port',
  `database` varchar(100) NOT NULL COMMENT 'Database name',
  `username` varchar(100) NOT NULL COMMENT 'Database username',
  `password` varchar(255) NOT NULL COMMENT 'Database password (encrypted)',
  `charset` varchar(20) NOT NULL DEFAULT 'utf8mb4' COMMENT 'Database charset',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order (0=primary, 1=secondary, etc)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='FreeRADIUS server database configurations';

-- Insert default configuration for primary and secondary servers
INSERT INTO `radius_server_config` (`name`, `enabled`, `host`, `port`, `database`, `username`, `password`, `display_order`) 
VALUES 
  ('FreeRADIUS Primary', 1, 'localhost', 3306, 'radius', 'radius', '', 0),
  ('FreeRADIUS Secondary', 1, 'localhost', 3306, 'radius', 'radius', '', 1)
ON DUPLICATE KEY UPDATE display_order=display_order;
