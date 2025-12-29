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

$currentPage = 'kea-servers';
$title = 'Kea DHCP Servers';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Kea DHCP Servers</h1>
            <p class="text-gray-600 mt-1">Manage Kea DHCP server connections</p>
        </div>
        <button onclick="showAddServerModal()" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Server
        </button>
    </div>

    <!-- Servers Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="w-auto divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Description
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        API URL
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Priority
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="serversTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Servers will be loaded via JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Server Modal -->
<div id="serverModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-xl font-semibold text-gray-900">Add Kea Server</h3>
            <button onclick="closeServerModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="serverForm" class="space-y-4">
            <input type="hidden" id="serverId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Name *</label>
                <input type="text" id="serverName" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" id="serverDescription"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">API URL *</label>
                <input type="url" id="serverApiUrl" required placeholder="http://localhost:8000"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-sm text-gray-500">Example: http://kea-server:8000</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="serverUsername"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Optional</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="serverPassword"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Optional</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Priority</label>
                    <input type="number" id="serverPriority" value="1" min="1"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Lower = Higher priority</p>
                </div>

                <div class="flex items-center pt-7">
                    <input type="checkbox" id="serverIsActive" checked
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="serverIsActive" class="ml-2 block text-sm text-gray-900">
                        Active
                    </label>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="closeServerModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Save Server
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let servers = [];

// Load servers on page load
document.addEventListener('DOMContentLoaded', function() {
    loadServers();
});

async function loadServers() {
    try {
        const response = await fetch('/api/kea-servers');
        const data = await response.json();
        
        if (data.success) {
            servers = data.servers;
            renderServers();
        } else {
            showNotification('Error loading servers: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('Failed to load servers: ' + error.message, 'error');
    }
}

function renderServers() {
    const tbody = document.getElementById('serversTableBody');
    
    if (servers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    No Kea servers configured. Click "Add Server" to get started.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = servers.map(server => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                ${escapeHtml(server.name)}
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                ${escapeHtml(server.description || '-')}
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                ${escapeHtml(server.api_url)}
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                ${server.priority}
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-sm">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${server.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                    ${server.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-right text-sm">
                <button onclick="testServer(${server.id})" 
                        class="text-blue-600 hover:text-blue-900 mr-3" title="Test Connection">
                    <svg class="h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
                <button onclick="editServer(${server.id})" 
                        class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit">
                    <svg class="h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </button>
                <button onclick="deleteServer(${server.id})" 
                        class="text-red-600 hover:text-red-900" title="Delete">
                    <svg class="h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');
}

function showAddServerModal() {
    document.getElementById('modalTitle').textContent = 'Add Kea Server';
    document.getElementById('serverForm').reset();
    document.getElementById('serverId').value = '';
    document.getElementById('serverPriority').value = '1';
    document.getElementById('serverIsActive').checked = true;
    document.getElementById('serverModal').classList.remove('hidden');
}

function editServer(id) {
    const server = servers.find(s => s.id == id);
    if (!server) return;

    document.getElementById('modalTitle').textContent = 'Edit Kea Server';
    document.getElementById('serverId').value = server.id;
    document.getElementById('serverName').value = server.name;
    document.getElementById('serverDescription').value = server.description || '';
    document.getElementById('serverApiUrl').value = server.api_url;
    document.getElementById('serverUsername').value = server.username || '';
    document.getElementById('serverPassword').value = ''; // Don't populate password
    document.getElementById('serverPriority').value = server.priority;
    document.getElementById('serverIsActive').checked = server.is_active == 1;
    document.getElementById('serverModal').classList.remove('hidden');
}

function closeServerModal() {
    document.getElementById('serverModal').classList.add('hidden');
}

document.getElementById('serverForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const serverId = document.getElementById('serverId').value;
    const data = {
        name: document.getElementById('serverName').value,
        description: document.getElementById('serverDescription').value,
        api_url: document.getElementById('serverApiUrl').value,
        username: document.getElementById('serverUsername').value,
        password: document.getElementById('serverPassword').value,
        priority: parseInt(document.getElementById('serverPriority').value),
        is_active: document.getElementById('serverIsActive').checked
    };

    try {
        const url = serverId ? `/api/kea-servers/${serverId}` : '/api/kea-servers';
        const method = serverId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            closeServerModal();
            loadServers();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Failed to save server: ' + error.message, 'error');
    }
});

async function deleteServer(id) {
    if (!confirm('Are you sure you want to delete this server?')) {
        return;
    }

    try {
        const response = await fetch(`/api/kea-servers/${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            loadServers();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Failed to delete server: ' + error.message, 'error');
    }
}

async function testServer(id) {
    showNotification('Testing connection...', 'info');

    try {
        const response = await fetch(`/api/kea-servers/${id}/test`);
        const result = await response.json();

        if (result.success) {
            showNotification('✓ Connection successful (HTTP ' + result.http_code + ')', 'success');
        } else {
            showNotification('✗ ' + result.message, 'error');
        }
    } catch (error) {
        showNotification('Test failed: ' + error.message, 'error');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    // You can implement a toast notification system here
    // For now, using alert
    if (type === 'error') {
        alert('Error: ' + message);
    } else if (type === 'success') {
        alert('Success: ' + message);
    } else {
        alert(message);
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
