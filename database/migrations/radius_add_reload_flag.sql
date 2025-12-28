-- Add simple reload flag table to the radius database (on FreeRADIUS servers)
-- This table is checked by a local Python script that sends HUP signal to FreeRADIUS

CREATE TABLE IF NOT EXISTS radius_reload_flag (
    id INT PRIMARY KEY DEFAULT 1,
    needs_reload BOOLEAN DEFAULT FALSE,
    last_reload TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (id = 1)  -- Ensure only one row exists
);

-- Insert the single row
INSERT INTO radius_reload_flag (id, needs_reload) VALUES (1, FALSE)
ON DUPLICATE KEY UPDATE id=id;
