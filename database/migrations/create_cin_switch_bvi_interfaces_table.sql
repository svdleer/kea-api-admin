CREATE TABLE IF NOT EXISTS `cin_switch_bvi_interfaces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `switch_id` INT NOT NULL,
    `interface_number` INT NOT NULL,
    `ipv6_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`switch_id`) REFERENCES `cin_switches`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `switch_interface` (`switch_id`, `interface_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
