<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in and is admin
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: /');
    exit;
}

$currentPage = 'radius-settings';
$title = 'RADIUS Server Settings';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">RADIUS Server Settings</h1>
                <p class="text-gray-600 mt-1">Configure FreeRADIUS database connections for automatic synchronization</p>
            </div>
            <a href="/radius" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to RADIUS Clients
            </a>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Setup:</strong> Configure the MySQL database connections for your FreeRADIUS servers. 
                    RADIUS clients will be automatically synced to both databases when you make changes.
                </p>
            </div>
        </div>
    </div>

    <!-- Server Configuration Forms -->
    <div class="space-y-6">
        <!-- Primary Server -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">FreeRADIUS Primary Server</h2>
                    <p class="text-sm text-gray-600 mt-1">First RADIUS server database connection</p>
                </div>
                <div class="flex items-center space-x-2">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="primary_enabled" class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                        <span class="ml-2 text-sm text-gray-700">Enabled</span>
                    </label>
                </div>
            </div>

            <form id="primaryServerForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="primary_host" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Host
                        </label>
                        <input type="text" id="primary_host" name="host" required
                               placeholder="localhost or IP address"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="primary_port" class="block text-sm font-medium text-gray-700 mb-2">
                            Port
                        </label>
                        <input type="number" id="primary_port" name="port" required
                               placeholder="3306"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="primary_database" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Name
                        </label>
                        <input type="text" id="primary_database" name="database" required
                               placeholder="radius"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="primary_username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input type="text" id="primary_username" name="username" required
                               placeholder="radius"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="primary_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <input type="password" id="primary_password" name="password"
                                   placeholder="••••••••"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('primary_password')"
                                    class="absolute right-2 top-2 text-gray-500 hover:text-gray-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="testConnection('primary')"
                            class="px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50">
                        Test Connection
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Save Primary Server
                    </button>
                </div>
            </form>
        </div>

        <!-- Secondary Server -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">FreeRADIUS Secondary Server</h2>
                    <p class="text-sm text-gray-600 mt-1">Second RADIUS server database connection</p>
                </div>
                <div class="flex items-center space-x-2">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="secondary_enabled" class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                        <span class="ml-2 text-sm text-gray-700">Enabled</span>
                    </label>
                </div>
            </div>

            <form id="secondaryServerForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="secondary_host" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Host
                        </label>
                        <input type="text" id="secondary_host" name="host" required
                               placeholder="localhost or IP address"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="secondary_port" class="block text-sm font-medium text-gray-700 mb-2">
                            Port
                        </label>
                        <input type="number" id="secondary_port" name="port" required
                               placeholder="3306"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="secondary_database" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Name
                        </label>
                        <input type="text" id="secondary_database" name="database" required
                               placeholder="radius"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="secondary_username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input type="text" id="secondary_username" name="username" required
                               placeholder="radius"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="secondary_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <input type="password" id="secondary_password" name="password"
                                   placeholder="••••••••"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('secondary_password')"
                                    class="absolute right-2 top-2 text-gray-500 hover:text-gray-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="testConnection('secondary')"
                            class="px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50">
                        Test Connection
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Save Secondary Server
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Load current configuration on page load
$(document).ready(function() {
    loadConfiguration();
});

async function loadConfiguration() {
    try {
        const response = await fetch('/api/radius/servers/config');
        const data = await response.json();
        
        if (data.success && data.servers) {
            // Load primary server config
            if (data.servers[0]) {
                const primary = data.servers[0];
                $('#primary_enabled').prop('checked', primary.enabled);
                $('#primary_host').val(primary.host);
                $('#primary_port').val(primary.port);
                $('#primary_database').val(primary.database);
                $('#primary_username').val(primary.username);
                $('#primary_password').val(primary.password);
            }
            
            // Load secondary server config
            if (data.servers[1]) {
                const secondary = data.servers[1];
                $('#secondary_enabled').prop('checked', secondary.enabled);
                $('#secondary_host').val(secondary.host);
                $('#secondary_port').val(secondary.port);
                $('#secondary_database').val(secondary.database);
                $('#secondary_username').val(secondary.username);
                $('#secondary_password').val(secondary.password);
            }
        }
    } catch (error) {
        console.error('Error loading configuration:', error);
    }
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

async function testConnection(serverType) {
    const prefix = serverType;
    const config = {
        enabled: $(`#${prefix}_enabled`).is(':checked'),
        host: $(`#${prefix}_host`).val(),
        port: parseInt($(`#${prefix}_port`).val()),
        database: $(`#${prefix}_database`).val(),
        username: $(`#${prefix}_username`).val(),
        password: $(`#${prefix}_password`).val()
    };

    if (!config.host || !config.database || !config.username) {
        Swal.fire({
            title: 'Missing Information',
            text: 'Please fill in all required fields',
            icon: 'warning',
            confirmButtonColor: '#6366f1'
        });
        return;
    }

    Swal.fire({
        title: 'Testing Connection...',
        text: `Connecting to ${config.host}:${config.port}`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('/api/radius/servers/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server: config })
        });

        const result = await response.json();

        if (result.success) {
            Swal.fire({
                title: 'Connection Successful!',
                html: `
                    <p>Successfully connected to ${config.host}</p>
                    <p class="text-sm text-gray-600 mt-2">Database: ${config.database}</p>
                    ${result.client_count !== undefined ? `<p class="text-sm text-gray-600">RADIUS clients: ${result.client_count}</p>` : ''}
                    ${result.response_time ? `<p class="text-sm text-gray-600">Response time: ${result.response_time}ms</p>` : ''}
                `,
                icon: 'success',
                confirmButtonColor: '#6366f1'
            });
        } else {
            Swal.fire({
                title: 'Connection Failed',
                text: result.message || 'Could not connect to database',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    } catch (error) {
        console.error('Error testing connection:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while testing the connection',
            icon: 'error',
            confirmButtonColor: '#6366f1'
        });
    }
}

// Primary server form submit
$('#primaryServerForm').on('submit', async function(e) {
    e.preventDefault();
    await saveServer('primary', 0);
});

// Secondary server form submit
$('#secondaryServerForm').on('submit', async function(e) {
    e.preventDefault();
    await saveServer('secondary', 1);
});

async function saveServer(serverType, index) {
    const prefix = serverType;
    const config = {
        name: serverType === 'primary' ? 'FreeRADIUS Primary' : 'FreeRADIUS Secondary',
        enabled: $(`#${prefix}_enabled`).is(':checked'),
        host: $(`#${prefix}_host`).val(),
        port: parseInt($(`#${prefix}_port`).val()),
        database: $(`#${prefix}_database`).val(),
        username: $(`#${prefix}_username`).val(),
        password: $(`#${prefix}_password`).val(),
        charset: 'utf8mb4'
    };

    try {
        const response = await fetch('/api/radius/servers/config', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                index: index,
                server: config
            })
        });

        const result = await response.json();

        if (result.success) {
            await Swal.fire({
                title: 'Saved!',
                text: `${config.name} configuration saved successfully`,
                icon: 'success',
                confirmButtonColor: '#6366f1'
            });
            loadConfiguration();
        } else {
            Swal.fire({
                title: 'Error',
                text: result.message || 'Failed to save configuration',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    } catch (error) {
        console.error('Error saving configuration:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while saving',
            icon: 'error',
            confirmButtonColor: '#6366f1'
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
