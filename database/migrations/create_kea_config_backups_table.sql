-- Create table to store Kea configuration backups
CREATE TABLE IF NOT EXISTS kea_config_backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    config_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100),
    operation VARCHAR(100) COMMENT 'The operation that triggered this backup',
    INDEX idx_server_created (server_id, created_at),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (server_id) REFERENCES kea_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores Kea configuration backups, keeping only the last 12 per server';
