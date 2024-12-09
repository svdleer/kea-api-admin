# API Documentation

## Authentication

All API endpoints require authentication using an API key. The API key should be included in the request headers as `X-API-Key`.

```
X-API-Key: your-api-key-here
```

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