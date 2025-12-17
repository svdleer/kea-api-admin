# Database Installation

## Fresh Installation

To install the complete database schema on a fresh installation:

```bash
mysql -h <host> -u <user> -p<password> <database> < database/schema.sql
```

Or using Docker:

```bash
docker exec -i <container> mysql -h <host> -u <user> -p<password> <database> < database/schema.sql
```

## What's Included

The schema includes all tables required for the application:

- **users** - User accounts
- **api_keys** - API authentication keys
- **app_config** - Application configuration
- **cin_switches** - CIN switch infrastructure
- **cin_switch_bvi_interfaces** - BVI interfaces for switches
- **cin_bvi_dhcp_core** - DHCP subnet to BVI mappings
- **dedicated_subnets** - Standalone subnets without BVI association
- **kea_servers** - Kea DHCP server configurations
- **kea_config_backups** - Kea configuration backups
- **ipv6_subnets** - IPv6 subnet management
- **nas** - RADIUS clients
- **radius_server_config** - RADIUS server configurations

## Migration Files

Individual migration files are available in `database/migrations/` for incremental updates or specific table modifications.
