-- Add columns to store FreeRADIUS server-specific IDs in the main nas table
-- This allows us to track the correct ID for each client on each RADIUS server

ALTER TABLE nas 
ADD COLUMN radius_primary_id INT NULL COMMENT 'ID of this client on FreeRADIUS Primary server',
ADD COLUMN radius_secondary_id INT NULL COMMENT 'ID of this client on FreeRADIUS Secondary server';

-- Add indexes for better lookup performance
CREATE INDEX idx_radius_primary_id ON nas(radius_primary_id);
CREATE INDEX idx_radius_secondary_id ON nas(radius_secondary_id);
