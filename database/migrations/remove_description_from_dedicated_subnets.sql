-- Remove description column from dedicated_subnets table
ALTER TABLE `dedicated_subnets` DROP COLUMN IF EXISTS `description`;
