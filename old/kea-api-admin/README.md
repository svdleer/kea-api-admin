# KEA API Admin: Network Management System

The KEA API Admin is a comprehensive network management system designed to streamline the administration of network switches, BVI interfaces, and IP address allocation. This PHP-based application provides a robust API and web interface for managing network infrastructure efficiently.

The system offers powerful features for network administrators, including switch management, BVI (Bridge-Group Virtual Interface) configuration, subnet allocation, and prefix management. It leverages modern PHP practices with PSR-4 autoloading and integrates environment variable management for secure configuration.

## Repository Structure

```
kea-api-admin/
├── composer.json
├── config/
│   └── database.php
├── index.php
├── info.php
├── routes/
│   └── api.php
├── scripts/
│   └── create_admin.php
├── src/
│   ├── Auth/
│   ├── Controllers/
│   ├── Database/
│   ├── Exception/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Models/
│   └── Router.php
├── templates/
├── test_auth.php
└── views/
    ├── dashboard.php
    ├── errors/
    ├── layout.php
    ├── login.php
    ├── logout.php
    ├── prefixes/
    ├── subnets/
    └── switches/
```

Key Files:
- `index.php`: Main entry point for the application
- `config/database.php`: Database configuration file
- `routes/api.php`: API route definitions
- `src/Router.php`: Custom routing implementation
- `composer.json`: Dependency management and autoloading configuration

Important integration points:
- `src/Database/Database.php`: Database connection interface
- `src/Controllers/Api/`: API controllers for various network management functions
- `src/Auth/Authentication.php`: Authentication mechanism for API and web interface

## Usage Instructions

### Installation

Prerequisites:
- PHP 7.4 or higher
- Composer
- MySQL or compatible database

Steps:
1. Clone the repository:
   ```
   git clone <repository_url>
   cd kea-api-admin
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Set up environment variables:
   - Copy `.env.example` to `.env`
   - Update `.env` with your database credentials and other configuration options

4. Set up the database:
   - Create a new MySQL database
   - Import the provided SQL schema (if available)

5. Create an admin user:
   ```
   php scripts/create_admin.php
   ```

### Configuration

The application uses environment variables for configuration. Key options include:

- `DB_HOST`: Database host
- `DB_NAME`: Database name
- `DB_USER`: Database username
- `DB_PASS`: Database password
- `API_KEY`: Secret key for API authentication

### API Usage

The KEA API Admin provides RESTful endpoints for managing network resources. Here's a basic example of how to use the API:

1. Authenticate:
   ```
   POST /api/auth
   Content-Type: application/json
   
   {
     "username": "admin",
     "password": "your_password"
   }
   ```

   Response:
   ```json
   {
     "token": "your_api_token"
   }
   ```

2. List switches:
   ```
   GET /api/switches
   Authorization: Bearer your_api_token
   ```

   Response:
   ```json
   {
     "switches": [
       {
         "id": 1,
         "name": "Switch-01",
         "ip_address": "192.168.1.1"
       },
       // ...
     ]
   }
   ```

3. Add a new BVI interface:
   ```
   POST /api/bvi
   Authorization: Bearer your_api_token
   Content-Type: application/json
   
   {
     "switch_id": 1,
     "interface_name": "BVI100",
     "ip_address": "10.0.0.1",
     "subnet_mask": "255.255.255.0"
   }
   ```

   Response:
   ```json
   {
     "message": "BVI interface created successfully",
     "bvi_id": 5
   }
   ```

### Web Interface

The web interface provides a user-friendly way to manage network resources:

1. Access the login page at `http://your-domain.com/login.php`
2. Use your admin credentials to log in
3. Navigate through the dashboard to manage switches, BVI interfaces, subnets, and prefixes

### Troubleshooting

Common issues and solutions:

1. Database Connection Error
   - Problem: Unable to connect to the database
   - Error message: "SQLSTATE[HY000] [1045] Access denied for user..."
   - Solution:
     1. Check your database credentials in the `.env` file
     2. Ensure the MySQL server is running
     3. Verify that the user has the necessary permissions

2. API Authentication Failure
   - Problem: Unable to authenticate API requests
   - Error message: "Invalid API key" or "Authentication failed"
   - Solution:
     1. Double-check the API key in your request headers
     2. Ensure the API key in the `.env` file matches the one you're using
     3. Verify that the authentication middleware is properly configured in `src/Middleware/AuthMiddleware.php`

3. Class Not Found Errors
   - Problem: PHP throws a "Class not found" exception
   - Error message: "Fatal error: Uncaught Error: Class 'App\SomeClass' not found"
   - Solution:
     1. Ensure that Composer's autoloader is properly included in `index.php`
     2. Check that the class namespace matches the directory structure in `src/`
     3. Run `composer dump-autoload` to regenerate the autoloader files

### Debugging

To enable debug mode:
1. Set the `DEBUG` environment variable to `true` in your `.env` file
2. Restart the PHP server or web server (if using Apache/Nginx)

Debug output will be logged to `storage/logs/app.log`. Ensure this directory exists and is writable by the web server.

To interpret debug output:
- Look for lines starting with `[DEBUG]` for detailed information about application flow
- Error stack traces will provide file names and line numbers for pinpointing issues

## Data Flow

The KEA API Admin follows a typical MVC (Model-View-Controller) architecture for handling requests. Here's an overview of the data flow:

1. Client sends a request (API or web interface)
2. `index.php` bootstraps the application and initializes the Router
3. Router (`src/Router.php`) determines the appropriate Controller based on the URL
4. AuthMiddleware (`src/Middleware/AuthMiddleware.php`) validates the request authentication
5. Controller (`src/Controllers/Api/*`) processes the request, interacting with Models as needed
6. Models (`src/Models/*`) handle data logic and database interactions via the Database class
7. Controller prepares the response, often using the ApiResponse helper
8. Response is sent back to the client

```
[Client] <-> [index.php] <-> [Router] <-> [AuthMiddleware] <-> [Controller]
                                                                  ^
                                                                  |
                                                                  v
                                                               [Model] <-> [Database]
```

Note: The web interface follows a similar flow but may render views (`views/*`) instead of returning JSON responses.