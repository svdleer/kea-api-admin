-- FreeRADIUS Client Table
-- This table stores RADIUS clients derived from BVI interface IPv6 addresses
-- Compatible with FreeRADIUS SQL schema

CREATE TABLE IF NOT EXISTS `nas` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `nasname` varchar(128) NOT NULL,
  `shortname` varchar(32) DEFAULT NULL,
  `type` varchar(30) DEFAULT 'other',
  `ports` int(5) DEFAULT NULL,
  `secret` varchar(60) NOT NULL DEFAULT 'testing123',
  `server` varchar(64) DEFAULT NULL,
  `community` varchar(50) DEFAULT NULL,
  `description` varchar(200) DEFAULT 'Auto-generated from BVI Interface',
  `bvi_interface_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nasname` (`nasname`),
  KEY `bvi_interface_id` (`bvi_interface_id`),
  CONSTRAINT `fk_nas_bvi_interface` 
    FOREIGN KEY (`bvi_interface_id`) 
    REFERENCES `cin_switch_bvi_interfaces` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for faster lookups
CREATE INDEX idx_nas_nasname ON nas(nasname);
CREATE INDEX idx_nas_bvi_interface ON nas(bvi_interface_id);
