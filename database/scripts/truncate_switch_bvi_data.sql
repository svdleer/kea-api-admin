-- Truncate switch and BVI data tables
-- WARNING: This will delete ALL data from these tables!
-- Run this only if you're sure you want to clear all switch and BVI data

SET FOREIGN_KEY_CHECKS = 0;

-- Truncate BVI-related tables first (due to foreign key constraints)
TRUNCATE TABLE cin_bvi_dhcp_core;
TRUNCATE TABLE cin_switch_bvi_interfaces;

-- Truncate switches table
TRUNCATE TABLE switches;

SET FOREIGN_KEY_CHECKS = 1;

-- Show tables to confirm
SELECT 'Truncated cin_bvi_dhcp_core' as status;
SELECT 'Truncated cin_switch_bvi_interfaces' as status;
SELECT 'Truncated switches' as status;
SELECT 'Kea tables left untouched' as status;
