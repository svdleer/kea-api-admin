-- Create cin_switch_bvi_interfaces table
-- Note: Foreign key constraint will be added after verifying cin_switches table exists

CREATE TABLE IF NOT EXISTS `cin_switch_bvi_interfaces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `switch_id` INT NOT NULL,
    `interface_number` INT NOT NULL,
    `ipv6_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `switch_interface` (`switch_id`, `interface_number`),
    KEY `idx_switch_id` (`switch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraint if cin_switches table exists
-- Uncomment the line below after verifying cin_switches table structure
-- ALTER TABLE `cin_switch_bvi_interfaces` 
-- ADD CONSTRAINT `fk_bvi_switch` 
-- FOREIGN KEY (`switch_id`) REFERENCES `cin_switches`(`id`) ON DELETE CASCADE;
