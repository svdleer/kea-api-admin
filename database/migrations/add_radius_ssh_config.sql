-- Add SSH configuration fields to radius_server_config table
-- This allows automatic FreeRADIUS reload after NAS client sync

ALTER TABLE radius_server_config 
ADD COLUMN IF NOT EXISTS ssh_host VARCHAR(255) DEFAULT NULL COMMENT 'SSH hostname/IP for FreeRADIUS reload',
ADD COLUMN IF NOT EXISTS ssh_user VARCHAR(50) DEFAULT 'root' COMMENT 'SSH username',
ADD COLUMN IF NOT EXISTS ssh_port INT DEFAULT 22 COMMENT 'SSH port',
ADD COLUMN IF NOT EXISTS auto_reload BOOLEAN DEFAULT TRUE COMMENT 'Auto-reload FreeRADIUS after sync';

-- Example update to configure SSH for existing servers
-- UPDATE radius_server_config SET 
--   ssh_host = 'radius1.gt.local',
--   ssh_user = 'root',
--   ssh_port = 22,
--   auto_reload = TRUE
-- WHERE name = 'FreeRADIUS Primary';
