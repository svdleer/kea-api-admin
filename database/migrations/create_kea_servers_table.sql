-- Create table for Kea DHCP server configuration
CREATE TABLE IF NOT EXISTS kea_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    api_url VARCHAR(255) NOT NULL,
    username VARCHAR(100),
    password VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default primary server
INSERT INTO kea_servers (name, description, api_url, is_active, priority) 
VALUES ('primary', 'Primary Kea DHCP Server', 'http://localhost:8000', TRUE, 1)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    api_url = VALUES(api_url);

-- Insert default secondary server (inactive by default)
INSERT INTO kea_servers (name, description, api_url, is_active, priority) 
VALUES ('secondary', 'Secondary Kea DHCP Server', 'http://localhost:8001', FALSE, 2)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    api_url = VALUES(api_url);
