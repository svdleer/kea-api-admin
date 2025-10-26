# API Documentation

## Authentication

All API endpoints require authentication using an API key. The API key should be included in the request headers as `X-API-Key`.

# KEA DHCP API Administration - API Documentation

## Overview

The KEA DHCP API Administration system provides a comprehensive REST API for managing:
- KEA DHCPv6 server configuration
- CIN switches and BVI interfaces
- Network subnets and prefixes
- DHCPv6 leases and reservations
- System users and API keys

## Authentication

All API endpoints require authentication using one of two methods:

### 1. API Key Authentication (Recommended for programmatic access)

Include your API key in the request headers:

```
X-API-Key: your-api-key-here
```

API keys come in two types:
- **Read-Only**: Can access GET endpoints only
- **Read/Write**: Can access all endpoints (GET, POST, PUT, DELETE)

### 2. Session Authentication (For web UI)

Session-based authentication using cookies. Used automatically when logged in through the web interface.

## Creating an API Key

1. Log in to the web interface as an administrator
2. Navigate to **API Keys** page (`/api-keys`)
3. Click **Create API Key**
4. Enter a name and select access level (read-only or read/write)
5. Copy the generated key - **it will only be shown once**

## API Endpoints

> **Note**: The generic `/api/subnets` endpoints have been deprecated. Use `/api/dhcp/subnets` for DHCPv6 subnet management or `/api/ipv6/subnets` for IPv6 subnet management instead.

### API Key Management

- `GET /api/keys` - List all API keys
- `POST /api/keys` - Create a new API key (admin only)
- `POST /api/keys/{id}/deactivate` - Deactivate an API key
- `DELETE /api/keys/{id}` - Delete an API key

### User Management

- `GET /api/users` - List all users (admin only)
- `POST /api/users` - Create a new user (admin only)
- `GET /api/users/{id}` - Get user details
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user
- `GET /api/users/check-username/{username}` - Check if username is available
- `GET /api/users/check-email/{email}` - Check if email is available

### Dashboard & Statistics

- `GET /api/dashboard/stats` - Get system statistics (switches, BVIs, subnets, etc.)
- `GET /api/dashboard/kea-status` - Get KEA DHCP server status

### CIN Switches

- `GET /api/switches` - List all CIN switches (read-only)
- `GET /api/switches/{id}` - Get a specific switch
- `POST /api/switches` - Create a new switch (requires write access)
- `PUT /api/switches/{id}` - Update a switch (requires write access)
- `DELETE /api/switches/{id}` - Delete a switch (requires write access)
- `GET /api/switches/check-exists` - Check if hostname exists
- `GET /api/switches/check-bvi` - Validate BVI interface number
- `GET /api/switches/check-ipv6` - Validate IPv6 address

### BVI Interfaces

- `GET /api/switches/{switchId}/bvi` - List BVI interfaces for a switch
- `GET /api/switches/{switchId}/bvi/{bviId}` - Get specific BVI interface
- `POST /api/switches/{switchId}/bvi` - Create BVI interface (requires write access)
- `PUT /api/switches/{switchId}/bvi/{bviId}` - Update BVI interface (requires write access)
- `DELETE /api/switches/{switchId}/bvi/{bviId}` - Delete BVI interface (requires write access)
- `GET /api/switches/{switchId}/bvi/check-exists` - Check if BVI exists
- `GET /api/switches/bvi/check-ipv6` - Check if IPv6 address is used

### DHCP Subnets (KEA Configuration)

- `GET /api/dhcp/subnets` - List all DHCP subnets
- `POST /api/dhcp/subnets` - Create DHCP subnet (requires write access)
- `POST /api/dhcp/subnets/check-duplicate` - Check for duplicate subnets
- `PUT /api/dhcp/subnets/{id}` - Update DHCP subnet (requires write access)
- `DELETE /api/dhcp/subnets/{id}` - Delete DHCP subnet (requires write access)
- `DELETE /api/dhcp/orphaned-subnets/{keaId}` - Delete orphaned subnet from Kea

### DHCPv6 Option Definitions

- `GET /api/dhcp/optionsdef` - List all option definitions
- `POST /api/dhcp/optionsdef` - Create option definition (requires write access)
- `PUT /api/dhcp/optionsdef/{code}` - Update option definition (requires write access)
- `DELETE /api/dhcp/optionsdef/{code}` - Delete option definition (requires write access)

### DHCPv6 Options

- `GET /api/dhcp/options` - List all configured options
- `POST /api/dhcp/options` - Create option (requires write access)
- `PUT /api/dhcp/options/{code}` - Update option (requires write access)
- `DELETE /api/dhcp/options/{code}` - Delete option (requires write access)

### DHCPv6 Leases & Reservations

- `GET /api/dhcp/leases/{switchId}/{bviId}/{from}/{limit}` - Get leases (paginated)
  - `switchId`: Switch ID or `0` for all
  - `bviId`: BVI interface ID
  - `from`: `start` for first page, or IPv6 address to continue from
  - `limit`: Number of leases to return (e.g., `10`, `50`, `100`)
- `DELETE /api/dhcp/leases` - Delete a lease (requires write access)
  - Body: `{"ip-address": "2001:db8::1", "subnet-id": 1}`
- `POST /api/dhcp/static` - Add static lease/reservation (requires write access)
- `GET /api/dhcp/static/{subnetId}` - Get static leases for subnet

### IPv6 Subnets (Kea Management)

- `GET /api/ipv6/subnets` - List IPv6 subnets
- `GET /api/ipv6/subnets/{subnetId}` - Get specific IPv6 subnet
- `POST /api/ipv6/subnets` - Create IPv6 subnet (requires write access)
- `PUT /api/ipv6/subnets/{subnetId}` - Update IPv6 subnet (requires write access)
- `DELETE /api/ipv6/subnets/{subnetId}` - Delete IPv6 subnet (requires write access)
- `GET /api/ipv6/bvi/{bviId}/subnets` - Get subnets by BVI interface

### RADIUS Clients (802.1X Authentication)

#### RADIUS Client Management
- `GET /api/radius/clients` - List all RADIUS clients
- `GET /api/radius/clients/{id}` - Get specific RADIUS client
- `POST /api/radius/clients` - Create RADIUS client (requires write access)
- `PUT /api/radius/clients/{id}` - Update RADIUS client (requires write access)
- `DELETE /api/radius/clients/{id}` - Delete RADIUS client (requires write access)

#### Global Secret Management
- `GET /api/radius/global-secret` - Get global shared secret status
- `PUT /api/radius/global-secret` - Set/update global shared secret (admin only, requires write access)
  - Body: `{"secret": "your-secret", "apply_to_all": true}`

#### BVI Interface Synchronization
- `POST /api/radius/sync` - Sync BVI interfaces to RADIUS clients (requires write access)

#### FreeRADIUS Server Management
- `GET /api/radius/servers/status` - Get status of all configured FreeRADIUS servers
- `POST /api/radius/servers/sync` - Force sync all clients to RADIUS servers (requires write access)
- `GET /api/radius/servers/config` - Get RADIUS server configurations (admin only)
- `PUT /api/radius/servers/config` - Update RADIUS server configuration (admin only, requires write access)
  - Body: `{"index": 0, "server": {...}}`
- `POST /api/radius/servers/test` - Test RADIUS server connection (admin only, requires write access)
  - Body: `{"server": {"host": "...", "port": 3306, "database": "radius", "username": "...", "password": "..."}}`

#### RADIUS Features
- **Automatic Sync**: RADIUS clients automatically created/updated/deleted when BVI interfaces change
- **Multi-Server Support**: Sync to multiple FreeRADIUS databases simultaneously
- **Global Secrets**: Configure one shared secret for all clients or individual secrets
- **Encrypted Storage**: Server passwords encrypted with AES-256-CBC
- **Health Monitoring**: Real-time status of all RADIUS database connections
- **Auto Table Creation**: Automatically creates `nas` table in RADIUS databases if missing

## Response Format

### Success Response

```json
{
    "success": true,
    "data": {
        // Response data here
    }
}
```

### Error Response

```json
{
    "success": false,
    "message": "Error description here"
}
```

## HTTP Status Codes

- `200 OK` - Success
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions (e.g., read-only key trying to write)
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Rate Limiting

Currently no rate limiting is implemented. Consider implementing rate limiting in production environments.

## OpenAPI Specification

View the complete OpenAPI 3.0 specification at:
- **Swagger JSON**: `/swagger.json`
- **API Documentation UI**: `/api-keys` page (click "View API Docs")

## Examples

### Example: Create a CIN Switch

```bash
curl -X POST https://your-domain.com/api/switches \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "hostname": "switch01",
    "management_ip": "192.168.1.10",
    "location": "Data Center 1"
  }'
```

### Example: Get Leases

```bash
curl -X GET https://your-domain.com/api/dhcp/leases/0/13/start/50 \
  -H "X-API-Key: your-api-key"
```

### Example: Add Static Lease

```bash
curl -X POST https://your-domain.com/api/dhcp/static \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "duid": "00:01:00:01:2a:3b:4c:5d:6e:7f",
    "ipAddress": "2001:db8::100",
    "subnetId": 1,
    "options": []
  }'
```

### Example: List RADIUS Clients

```bash
curl -X GET https://your-domain.com/api/radius/clients \
  -H "X-API-Key: your-api-key"
```

### Example: Set Global RADIUS Secret

```bash
curl -X PUT https://your-domain.com/api/radius/global-secret \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "secret": "my-secure-radius-secret-32chars",
    "apply_to_all": true
  }'
```

### Example: Check FreeRADIUS Server Status

```bash
curl -X GET https://your-domain.com/api/radius/servers/status \
  -H "X-API-Key: your-api-key"
```

### Example: Configure RADIUS Server

```bash
curl -X PUT https://your-domain.com/api/radius/servers/config \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "index": 0,
    "server": {
      "name": "FreeRADIUS Primary",
      "enabled": true,
      "host": "192.168.1.10",
      "port": 3306,
      "database": "radius",
      "username": "radius_user",
      "password": "secure_password",
      "charset": "utf8mb4"
    }
  }'
```

### Example: Test RADIUS Server Connection

```bash
curl -X POST https://your-domain.com/api/radius/servers/test \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "server": {
      "host": "192.168.1.10",
      "port": 3306,
      "database": "radius",
      "username": "radius_user",
      "password": "test_password"
    }
  }'
```

## Migration from Deprecated Endpoints

If you were using any of the following deprecated endpoints:
- `/api/subnets` - Use `/api/dhcp/subnets` or `/api/ipv6/subnets` instead
- `/api/subnets/{id}/prefixes` - These were never fully implemented

**Recommended Actions:**
1. Update any API clients to use the correct endpoints
2. Use `/api/dhcp/subnets` for DHCPv6 server configuration
3. Use `/api/ipv6/subnets` for IPv6 subnet management in Kea

## Security Best Practices

1. **Keep API keys secure** - Never commit keys to version control
2. **Use read-only keys** when possible
3. **Rotate keys regularly** - Especially for write-access keys
4. **Use HTTPS** - Always use encrypted connections in production
5. **Monitor API usage** - Track when and how keys are used
6. **Revoke unused keys** - Delete or deactivate keys that are no longer needed

## Support

For issues or questions:
- Check the logs: `/logs/error_log`
- Review the codebase: GitHub repository
- Contact system administrator


