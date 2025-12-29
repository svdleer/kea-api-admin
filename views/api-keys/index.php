<?php 
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}
$error = null;
$success = null;

$currentPage = 'API Keys';
$title = 'Edit API Keys';

ob_start();


$title = 'API Keys ';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">API Keys</h1>
        <div class="flex space-x-2"> <!-- Added flex container with spacing -->
            <button onclick="showSwaggerDocs()" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                API Documentation
            </button>
            <?php if ($auth->isAdmin()): ?>
            <button onclick="showCreateApiKeyModal()" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded">
                Create API Key
            </button>
            <?php endif; ?>
        </div>
    </div>


    <div class="bg-white shadow-md rounded-lg overflow-hidden w-fit">
        <table class="w-auto leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Access Level
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Created
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Last Used
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"></th>
                </tr>
            </thead>
            <tbody id="apiKeysList">
                <!-- API keys will be loaded here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Create API Key Modal -->
<div id="createApiKeyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Create New API Key</h3>
            <form id="createApiKeyForm" class="mt-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keyName">
                        Key Name
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="keyName" type="text" placeholder="Enter key name" required>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="readOnly" class="form-checkbox h-4 w-4 text-blue-600">
                        <span class="ml-2 text-gray-700">Read-only access</span>
                    </label>
                </div>
                <div class="flex items-center justify-between mt-6">
                    <button type="button" onclick="hideCreateApiKeyModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancel
                    </button>
                    <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- API Key Created Modal -->
<div id="apiKeyCreatedModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900">API Key Created</h3>
            <div class="mt-4">
                <p class="text-sm text-gray-500">Please copy your API key now. You won't be able to see it again!</p>
                <div class="mt-4 bg-gray-100 p-3 rounded">
                    <code id="newApiKey" class="break-all"></code>
                </div>
            </div>
            <div class="mt-6">
                <button onclick="hideApiKeyCreatedModal()" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Documentation -->
<div id="swaggerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-hidden h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 shadow-lg rounded-md bg-white" style="height: 80vh; max-height: 80vh;">
        <div class="flex flex-col h-full">
            <div class="flex justify-between items-center mb-4 flex-shrink-0">
                <h3 class="text-lg font-medium">API Documentation</h3>
                <button onclick="hideSwaggerDocs()" class="text-gray-600 hover:text-gray-900">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto">
                <div id="swagger-ui" class="h-full"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showSwaggerDocs() {
    document.getElementById('swaggerModal').classList.remove('hidden');
    
    if (!window.swaggerUiInitialized) {
        SwaggerUIBundle({
            url: "/api/docs/spec",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
            layout: "BaseLayout",
            defaultModelsExpandDepth: -1,
            tryItOutEnabled: true,
            persistAuthorization: true,
            displayOperationId: false,
            filter: true,
            withCredentials: true,
            onComplete: function() {
                const authBanner = document.createElement('div');
                authBanner.className = 'auth-banner bg-blue-50 p-4 mb-4 rounded-md';
                authBanner.innerHTML = `
                    <p class="text-sm text-blue-800">
                        <strong>Authentication:</strong> Use your API key in the format <code>X-API-Key: your-api-key</code>.<br>
                        - For read-only operations, use a read-only API key<br>
                        - For write operations, use a read-write API key
                    </p>
                `;
                document.querySelector('.swagger-ui .scheme-container').appendChild(authBanner);
            }
        });
        window.swaggerUiInitialized = true;
    }
}

function hideSwaggerDocs() {
    document.getElementById('swaggerModal').classList.add('hidden');
}

function showCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').classList.remove('hidden');
}

function hideCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').classList.add('hidden');
    document.getElementById('createApiKeyForm').reset();
}

function showApiKeyCreatedModal(apiKey) {
    document.getElementById('newApiKey').textContent = apiKey;
    document.getElementById('apiKeyCreatedModal').classList.remove('hidden');
}

function hideApiKeyCreatedModal() {
    document.getElementById('apiKeyCreatedModal').classList.add('hidden');
    loadApiKeys(); // Refresh the list
}

function loadApiKeys() {
    fetch('/api/keys')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('apiKeysList');
                tbody.innerHTML = '';
                
                data.keys.forEach(key => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.name}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.read_only ? 'Read-only' : 'Read/Write'}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${new Date(key.created_at).toLocaleDateString()}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.last_used ? new Date(key.last_used).toLocaleDateString() : 'Never'}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <span class="relative inline-block px-3 py-1 font-semibold ${key.active ? 'text-green-900' : 'text-red-900'} leading-tight">
                                <span aria-hidden class="absolute inset-0 ${key.active ? 'bg-green-200' : 'bg-red-200'} opacity-50 rounded-full"></span>
                                <span class="relative">${key.active ? 'Active' : 'Inactive'}</span>
                            </span>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                            ${key.active ? `
                            <button onclick="deactivateApiKey(${key.id})" class="text-red-600 hover:text-red-900">
                                Deactivate
                            </button>
                            ` : ''}
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(error => console.error('Error loading API keys:', error));
}

function deactivateApiKey(keyId) {
    if (!confirm('Are you sure you want to deactivate this API key? This action cannot be undone.')) {
        return;
    }

    fetch(`/api/keys/${keyId}/deactivate`, {
        method: 'POST',
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadApiKeys();
            } else {
                alert('Failed to deactivate API key');
            }
        })
        .catch(error => console.error('Error deactivating API key:', error));
}



function showCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').classList.remove('hidden');
}

function hideCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').classList.add('hidden');
    document.getElementById('createApiKeyForm').reset();
}

function hideApiKeyCreatedModal() {
    document.getElementById('apiKeyCreatedModal').classList.add('hidden');
    loadApiKeys(); // Refresh the list
}

document.getElementById('createApiKeyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('keyName').value;
    const readOnly = document.getElementById('readOnly').checked;

    fetch('/api/keys', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, read_only: readOnly })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            hideCreateApiKeyModal();
            // Show the API key in a SweetAlert2 popup
            Swal.fire({
                title: 'API Key Created',
                html: `
                    <p class="mb-4">Please copy your API key now. You won't be able to see it again!</p>
                    <div class="bg-gray-100 p-3 rounded">
                        <code class="break-all">${data.apiKey}</code>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'I have copied the key',
                confirmButtonColor: '#6366f1',
            }).then((result) => {
                loadApiKeys(); // Refresh the list after closing
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error || 'Failed to create API key',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: 'Failed to create API key',
            icon: 'error'
        });
    });
});


function loadApiKeys() {
    fetch('/api/keys')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {  // Changed from data.success to data.status
                const tbody = document.getElementById('apiKeysList');
                tbody.innerHTML = '';
                
                data.keys.forEach(key => {  // This part is correct as it matches the response
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.name}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.read_only ? 'Read-only' : 'Read/Write'}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${new Date(key.created_at).toLocaleDateString()}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <p class="text-gray-900 whitespace-no-wrap">${key.last_used ? new Date(key.last_used).toLocaleDateString() : 'Never'}</p>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <span class="relative inline-block px-3 py-1 font-semibold ${key.active ? 'text-green-900' : 'text-red-900'} leading-tight">
                                <span aria-hidden class="absolute inset-0 ${key.active ? 'bg-green-200' : 'bg-red-200'} opacity-50 rounded-full"></span>
                                <span class="relative">${key.active ? 'Active' : 'Inactive'}</span>
                            </span>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                            ${key.active ? `
                                <button onclick="deactivateApiKey(${key.id})" class="text-yellow-600 hover:text-yellow-900 mr-2">
                                    Deactivate
                                </button>
                            ` : ''}
                            <button onclick="deleteApiKey(${key.id})" class="text-red-600 hover:text-red-900">
                                Delete
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Error',
                text: 'Failed to load API keys',
                icon: 'error'
            });
        });
}

function deactivateApiKey(keyId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This API key will be deactivated and cannot be reactivated!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, deactivate it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/keys/${keyId}/deactivate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                Swal.fire(
                    'Deactivated!',
                    'The API key has been deactivated.',
                    'success'
                );
                loadApiKeys(); // Refresh the list
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to deactivate API key',
                    icon: 'error'
                });
            });
        }
    });
}

function deleteApiKey(keyId) {
    Swal.fire({
        title: 'Delete API Key',
        text: "Are you sure you want to delete this API key? This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/keys/${keyId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                Swal.fire(
                    'Deleted!',
                    'The API key has been deleted.',
                    'success'
                );
                loadApiKeys(); // Refresh the list
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to delete API key',
                    icon: 'error'
                });
            });
        }
    });
}


// Load API keys when the page loads
document.addEventListener('DOMContentLoaded', function() {
    loadApiKeys();
});
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>