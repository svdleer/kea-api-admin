# FreeRADIUS Integration

This KEA API Admin system now includes automatic FreeRADIUS client management based on BVI interface IPv6 addresses.

## Overview

The system automatically:
- Creates RADIUS clients when BVI interfaces are added
- Updates RADIUS client IP addresses when BVI interfaces are modified
- Deletes RADIUS clients when BVI interfaces are removed (CASCADE delete)

## Database Setup

### 1. Run the Migration

```bash
mysql -u your_user -p dhcpdb < database/migrations/create_radius_clients_table.sql
```

This creates the `nas` table compatible with FreeRADIUS SQL schema.

### 2. Sync Existing BVI Interfaces

If you have existing BVI interfaces, sync them to RADIUS clients:

```bash
# Via API
curl -X POST https://your-domain.com/api/radius/sync \
  -H "X-API-Key: your-api-key"
```

Or add the API route (see below) and visit `/radius` page to click "Sync All BVI Interfaces".

## FreeRADIUS Configuration

### 1. Configure FreeRADIUS to Use MySQL

Edit `/etc/freeradius/3.0/mods-available/sql` (or `/etc/raddb/mods-available/sql`):

```conf
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"

    # Connection info
    server = "localhost"
    port = 3306
    login = "dhcp_admin"
    password = "your_password"
    radius_db = "dhcpdb"

    # Read clients from database
    read_clients = yes
    client_table = "nas"

    # Table/column definitions
    nas_table = "nas"
    nas_identifier = "nasname"
    nas_shortname = "shortname"
    nas_type = "type"
    nas_ports = "ports"
    nas_secret = "secret"
    nas_server = "server"
    nas_community = "community"
}
```

### 2. Enable SQL Module

```bash
cd /etc/freeradius/3.0/mods-enabled
ln -s ../mods-available/sql sql

# Or for older versions
cd /etc/raddb/mods-enabled
ln -s ../mods-available/sql sql
```

### 3. Configure Site to Read Clients from SQL

Edit `/etc/freeradius/3.0/sites-available/default`:

```conf
authorize {
    ...
    sql
    ...
}

# Make sure client loading is enabled in radiusd.conf
```

### 4. Test Configuration

```bash
# Check config
freeradius -C

# Run in debug mode
freeradius -X
```

### 5. Restart FreeRADIUS

```bash
systemctl restart freeradius
# or
service freeradius restart
```

## API Endpoints

Add these routes to your `index.php`:

```php
// RADIUS Client Management
$router->get('/api/radius/clients', function() use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->getAllClients();
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));

$router->get('/api/radius/clients/{id}', function($id) use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->getClientById($id);
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));

$router->post('/api/radius/clients', function() use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->createClient();
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));

$router->put('/api/radius/clients/{id}', function($id) use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->updateClient($id);
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));

$router->delete('/api/radius/clients/{id}', function($id) use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->deleteClient($id);
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));

$router->post('/api/radius/sync', function() use ($db, $auth) {
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    
    $controller = new \App\Controllers\Api\RadiusController(
        new \App\Models\RadiusClient($db),
        $auth
    );
    $controller->syncBviInterfaces();
})->middleware(new \App\Middleware\ApiKeyMiddleware($auth));
```

## Database Schema

The `nas` table includes:

| Column | Type | Description |
|--------|------|-------------|
| id | int(10) | Primary key |
| nasname | varchar(128) | Client IP address (IPv6 from BVI) |
| shortname | varchar(32) | Short name for the client |
| type | varchar(30) | Client type (default: 'other') |
| ports | int(5) | Port number (optional) |
| secret | varchar(60) | RADIUS shared secret |
| server | varchar(64) | Server (optional) |
| community | varchar(50) | SNMP community (optional) |
| description | varchar(200) | Description |
| bvi_interface_id | int(11) | Foreign key to BVI interface |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

## Automatic Synchronization

The system automatically handles RADIUS client synchronization:

1. **On BVI Create**: A new RADIUS client is created with a random secret
2. **On BVI Update**: If the IPv6 address changes, the RADIUS client's nasname is updated
3. **On BVI Delete**: The RADIUS client is automatically deleted (CASCADE)

## Manual Management

### View All RADIUS Clients

```bash
curl -X GET https://your-domain.com/api/radius/clients \
  -H "X-API-Key: your-api-key"
```

### Update RADIUS Client Secret

```bash
curl -X PUT https://your-domain.com/api/radius/clients/1 \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "secret": "new-strong-secret-here",
    "shortname": "BVI100-Custom",
    "description": "Custom description"
  }'
```

### Force Sync All BVI Interfaces

```bash
curl -X POST https://your-domain.com/api/radius/sync \
  -H "X-API-Key: your-api-key"
```

## Security Considerations

1. **Strong Secrets**: The system generates random 32-character hex secrets by default
2. **Unique IPs**: Each BVI IPv6 address creates a unique RADIUS client
3. **Access Control**: API endpoints require authentication (API key or session)
4. **Audit Trail**: All changes are logged with timestamps

## Troubleshooting

### Check if RADIUS clients are created

```sql
SELECT n.*, b.ipv6_address, s.hostname 
FROM nas n
LEFT JOIN cin_switch_bvi_interfaces b ON n.bvi_interface_id = b.id
LEFT JOIN cin_switches s ON b.switch_id = s.id;
```

### Verify FreeRADIUS can read clients

```bash
freeradius -X
# Look for "rlm_sql: Loading clients from database"
```

### Test RADIUS authentication

```bash
radtest username password localhost:1812 0 testing123
```

## Next Steps

1. Create a web UI page at `/radius` to manage RADIUS clients
2. Add bulk secret regeneration feature
3. Add RADIUS statistics/monitoring
4. Configure FreeRADIUS realms and proxying if needed

## Support

For issues or questions:
- Check FreeRADIUS logs: `/var/log/freeradius/radius.log`
- Check application logs: `/logs/error_log`
- Verify database connectivity and permissions
