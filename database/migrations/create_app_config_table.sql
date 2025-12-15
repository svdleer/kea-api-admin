-- Create app_config table for storing application configuration
CREATE TABLE IF NOT EXISTS `app_config` (
  `config_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `config_value` TEXT,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default global RADIUS secret if not exists
INSERT IGNORE INTO `app_config` (`config_key`, `config_value`, `description`) 
VALUES ('radius_global_secret', NULL, 'Global shared secret for RADIUS clients');
