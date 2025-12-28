-- Add reload flag to radius_server_config table
-- This flag is set when NAS clients change and needs FreeRADIUS reload

-- Add column (ignore error if it already exists)
ALTER TABLE radius_server_config 
ADD COLUMN needs_reload BOOLEAN DEFAULT FALSE COMMENT 'Set to TRUE when FreeRADIUS needs to reload NAS clients';

-- Create index for quick polling (ignore error if it already exists)
CREATE INDEX idx_needs_reload ON radius_server_config(needs_reload);
