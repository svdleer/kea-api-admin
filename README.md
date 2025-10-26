# KEA DHCP API Administration System

A comprehensive web-based administration interface and REST API for managing ISC KEA DHCPv6 server, CIN switches, BVI interfaces, and network resources.

## Features

### 🌐 Web Interface
- **Dashboard** - Real-time statistics and KEA server status monitoring
- **User Management** - Multi-user support with role-based access (admin/user)
- **CIN Switches** - Manage network switches and their configurations
- **BVI Interfaces** - Configure Bridge Virtual Interfaces
- **DHCP Management** - Complete DHCPv6 subnet, pool, and option configuration
- **Lease Management** - View active leases and manage static reservations
- **API Key Management** - Create and manage API keys for programmatic access

### 🔌 REST API
- **Comprehensive API** - Full RESTful API for all operations
- **OpenAPI 3.0 Spec** - Swagger documentation included
- **Dual Authentication** - API keys and session-based authentication
- **Read/Write Permissions** - Granular access control with read-only and read/write keys

### 🛡️ Security
- **Authentication Required** - All endpoints require authentication
- **Session Management** - Secure session handling with PHP sessions
- **API Key System** - Token-based authentication for programmatic access
- **HTTPS Ready** - Designed for secure HTTPS deployment
- **Password Hashing** - Secure bcrypt password storage

## Architecture

### Technology Stack
- **Backend**: PHP 8.x
- **Database**: MySQL/MariaDB
- **Frontend**: HTML, TailwindCSS, Alpine.js
- **DHCP Server**: ISC KEA DHCPv6
- **Web Server**: Apache/Nginx with PHP-FPM

### Project Structure
```
kea-api-admin/
├── src/
│   ├── Controllers/     # API and web controllers
│   ├── Models/          # Database and KEA models
│   ├── Auth/            # Authentication system
│   ├── Middleware/      # Auth and API key middleware
│   ├── Kea/             # KEA DHCP client
│   ├── Helpers/         # Utility functions
│   └── Router.php       # Custom routing system
├── views/               # Web UI templates
├── config/              # Configuration files
├── database/            # Database migrations
├── docs/                # Documentation
├── vendor/              # Composer dependencies
└── index.php            # Application entry point
```

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL/MariaDB 5.7+
- Composer
- ISC KEA DHCPv6 server with API enabled
- Apache/Nginx web server

### Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd kea-api-admin
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

4. **Create database**
   ```bash
   mysql -u root -p < dhcpdb_create.mysql
   ```

5. **Run migrations**
   ```bash
   php scripts/create_admin.php
   ```

6. **Configure web server**
   
   Point document root to the project directory.
   
   **Apache Example:**
   ```apache
   <VirtualHost *:443>
       ServerName kea.yourdomain.com
       DocumentRoot /path/to/kea-api-admin
       
       <Directory /path/to/kea-api-admin>
           AllowOverride All
           Require all granted
       </Directory>
       
       SSLEngine on
       SSLCertificateFile /path/to/cert.pem
       SSLCertificateKeyFile /path/to/key.pem
   </VirtualHost>
   ```

7. **Access the application**
   ```
   https://kea.yourdomain.com/
   ```

## Configuration

### Environment Variables (.env)
```ini
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dhcpdb
DB_USERNAME=dhcp_admin
DB_PASSWORD=your_secure_password

# KEA DHCP Configuration
KEA_API_ENDPOINT=http://localhost:8000
KEA_PRIMARY_URL=http://primary-kea:8000

# Application
APP_ENV=production
APP_DEBUG=false
SESSION_LIFETIME=7200
```

### KEA DHCPv6 Configuration

Ensure KEA Control Agent is enabled and accessible:

```json
{
  "Control-agent": {
    "http-host": "0.0.0.0",
    "http-port": 8000,
    "control-sockets": {
      "dhcp6": {
        "socket-type": "unix",
        "socket-name": "/tmp/kea-dhcp6-ctrl.sock"
      }
    }
  }
}
```

## Usage

### Web Interface

1. **Login**: Navigate to `https://your-domain.com/`
2. **Dashboard**: View system statistics and KEA status
3. **Manage Resources**: Use the sidebar navigation to access different modules

### API Usage

#### Authentication
Include your API key in all requests:
```bash
curl -H "X-API-Key: your-api-key" https://your-domain.com/api/switches
```

#### Create API Key
1. Log in as administrator
2. Navigate to API Keys page
3. Click "Create API Key"
4. Choose read-only or read/write access
5. Copy the generated key (shown only once!)

#### Example API Calls

**List Switches:**
```bash
curl -X GET https://your-domain.com/api/switches \
  -H "X-API-Key: your-key"
```

**Create Switch:**
```bash
curl -X POST https://your-domain.com/api/switches \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{"hostname":"switch01","management_ip":"192.168.1.10"}'
```

**Get Leases:**
```bash
curl -X GET https://your-domain.com/api/dhcp/leases/0/13/start/50 \
  -H "X-API-Key: your-key"
```

## API Endpoints

### Core Resources
- **API Keys**: `/api/keys`
- **Users**: `/api/users`
- **Dashboard**: `/api/dashboard/stats`, `/api/dashboard/kea-status`

### Network Management
- **CIN Switches**: `/api/switches`
- **BVI Interfaces**: `/api/switches/{id}/bvi`
- **Subnets**: `/api/subnets`
- **Prefixes**: `/api/subnets/{id}/prefixes`

### DHCP Management
- **DHCP Subnets**: `/api/dhcp/subnets`
- **Options Definitions**: `/api/dhcp/optionsdef`
- **Options**: `/api/dhcp/options`
- **Leases**: `/api/dhcp/leases/{switchId}/{bviId}/{from}/{limit}`
- **Static Leases**: `/api/dhcp/static`

### IPv6 Management
- **IPv6 Subnets**: `/api/ipv6/subnets`
- **BVI Subnets**: `/api/ipv6/bvi/{bviId}/subnets`

For complete API documentation, see [docs/api.md](docs/api.md) or view the Swagger specification at `/swagger.json`.

## Database Schema

### Key Tables
- `users` - System users with authentication
- `api_keys` - API key management
- `cin_switches` - CIN network switches
- `cin_switch_bvi_interfaces` - BVI interfaces
- `subnets` - Network subnets
- `prefixes` - IPv6 prefixes

## Development

### Running Locally
```bash
# Install dependencies
composer install

# Start PHP development server
php -S localhost:8000

# Or use PHP-FPM with Nginx/Apache
```

### Code Style
- PSR-4 autoloading
- PSR-12 coding standards
- Namespace: `App\`

### Project Components

**Controllers** (`src/Controllers/Api/`)
- Handle HTTP requests
- Validate input
- Return JSON responses

**Models** (`src/Models/`)
- Database operations
- KEA API integration
- Business logic

**Middleware** (`src/Middleware/`)
- Authentication checks
- API key validation
- Request filtering

**Views** (`views/`)
- TailwindCSS for styling
- Alpine.js for interactivity
- PHP templating

## Troubleshooting

### Common Issues

**Can't connect to KEA:**
- Verify KEA Control Agent is running
- Check `KEA_API_ENDPOINT` in `.env`
- Test connectivity: `curl http://localhost:8000`

**Database connection failed:**
- Verify MySQL is running
- Check credentials in `.env`
- Ensure database exists

**API returns 401:**
- Check API key is valid and active
- Verify `X-API-Key` header is set
- Ensure key has write permissions for write operations

**Empty lease list:**
- This is normal if no leases exist
- Check KEA server is running and assigning leases
- Verify subnet configuration

### Logs
- **Application Logs**: `logs/error_log`
- **KEA Logs**: `/var/log/kea/`
- **PHP Errors**: Check PHP-FPM logs

## Security Considerations

1. **Use HTTPS** - Always run in production with SSL/TLS
2. **Secure .env** - Keep environment file secure (chmod 600)
3. **Strong Passwords** - Use strong passwords for admin users
4. **API Key Rotation** - Regularly rotate API keys
5. **Firewall Rules** - Restrict access to KEA Control Agent
6. **Regular Updates** - Keep dependencies up to date

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

[Add your license information here]

## Support

For issues, questions, or contributions:
- **Issues**: GitHub Issues
- **Documentation**: `/docs` directory
- **API Docs**: `/swagger.json`

## Changelog

### Version 2.0.0
- ✅ Complete REST API implementation
- ✅ API key management system
- ✅ User management with roles
- ✅ DHCPv6 lease management
- ✅ Static reservation support
- ✅ Option definitions management
- ✅ Comprehensive error handling
- ✅ OpenAPI 3.0 specification
- ✅ Dashboard with statistics
- ✅ Multi-user support

## Acknowledgments

Built with:
- [ISC KEA](https://www.isc.org/kea/) - DHCP Server
- [TailwindCSS](https://tailwindcss.com/) - CSS Framework
- [Alpine.js](https://alpinejs.dev/) - JavaScript Framework
- [SweetAlert2](https://sweetalert2.github.io/) - Beautiful alerts
