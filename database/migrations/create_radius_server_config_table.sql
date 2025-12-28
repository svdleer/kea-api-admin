-- Create radius_server_config table
-- This table stores RADIUS server connection details for synchronization

CREATE TABLE IF NOT EXISTS `radius_server_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_name` varchar(255) NOT NULL,
  `server_host` varchar(255) NOT NULL,
  `server_port` int(11) NOT NULL DEFAULT 22,
  `server_user` varchar(255) NOT NULL,
  `nas_config_path` varchar(512) NOT NULL DEFAULT '/etc/freeradius/3.0/clients.conf',
  `restart_command` varchar(512) NOT NULL DEFAULT 'sudo systemctl restart freeradius',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
