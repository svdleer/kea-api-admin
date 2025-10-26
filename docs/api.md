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

### IPv6 Subnets (Kea)

- `GET /api/ipv6/subnets` - List IPv6 subnets
- `GET /api/ipv6/subnets/{subnetId}` - Get subnet details
- `POST /api/ipv6/subnets` - Create IPv6 subnet (requires write access)
- `PUT /api/ipv6/subnets/{subnetId}` - Update IPv6 subnet (requires write access)
- `DELETE /api/ipv6/subnets/{subnetId}` - Delete IPv6 subnet (requires write access)
- `GET /api/ipv6/bvi/{bviId}/subnets` - Get subnets for BVI interface

### Network Subnets & Prefixes

- `POST /api/subnets` - Create network subnet (requires write access)
- `PUT /api/subnets/{subnetId}` - Update network subnet (requires write access)
- `DELETE /api/subnets/{subnetId}` - Delete network subnet (requires write access)
- `POST /api/subnets/{subnetId}/prefixes` - Create prefix (requires write access)
- `PUT /api/subnets/{subnetId}/prefixes/{prefixId}` - Update prefix (requires write access)
- `DELETE /api/subnets/{subnetId}/prefixes/{prefixId}` - Delete prefix (requires write access)

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


API keys can be either read-only or read/write. Write operations (POST, PUT, DELETE) require a read/write API key.

## Managing API Keys

API keys can be managed through the web interface at `/api-keys`. Only administrators can create new API keys.

### Creating an API Key
1. Log in to the web interface as an administrator
2. Navigate to the API Keys page
3. Click "Create API Key"
4. Enter a name for the key and select whether it should be read-only
5. Copy the generated API key - it will only be shown once

### API Routes

#### CIN Switches

- `GET /api/switches` - List all CIN switches (read-only)
- `GET /api/switches/{id}` - Get a specific CIN switch (read-only)
- `POST /api/switches` - Create a new CIN switch (requires write access)
- `PUT /api/switches/{id}` - Update a CIN switch (requires write access)
- `DELETE /api/switches/{id}` - Delete a CIN switch (requires write access)

#### BVI Interfaces

- `POST /api/switches/{switchId}/bvi` - Create a new BVI interface (requires write access)
- `PUT /api/switches/{switchId}/bvi/{bviId}` - Update a BVI interface (requires write access)
- `DELETE /api/switches/{switchId}/bvi/{bviId}` - Delete a BVI interface (requires write access)

#### Validation Endpoints

- `GET /api/switches/check-exists` - Check if a switch hostname exists
- `GET /api/switches/check-bvi` - Validate BVI interface
- `GET /api/switches/check-ipv6` - Validate IPv6 address

#### Subnets and Prefixes

- `POST /api/subnets` - Create a subnet (requires write access)
- `PUT /api/subnets/{subnetId}` - Update a subnet (requires write access)
- `DELETE /api/subnets/{subnetId}` - Delete a subnet (requires write access)
- `POST /api/subnets/{subnetId}/prefixes` - Create a prefix (requires write access)
- `PUT /api/subnets/{subnetId}/prefixes/{prefixId}` - Update a prefix (requires write access)
- `DELETE /api/subnets/{subnetId}/prefixes/{prefixId}` - Delete a prefix (requires write access)

## Error Responses

The API returns standard HTTP status codes:

- 200: Success
- 400: Bad Request
- 401: Unauthorized (missing or invalid API key)
- 403: Forbidden (insufficient permissions)
- 404: Not Found
- 500: Internal Server Error

Error responses include a JSON body with an error message:

```json
{
    "error": "Error message here"
}
```

## Success Responses

Success responses include a JSON body with a success flag and any relevant data:

```json
{
    "success": true,
    "data": {
        // Response data here
    }
}
```