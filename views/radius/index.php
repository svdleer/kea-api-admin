<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    exit;
}

$currentPage = 'radius';
$title = 'RADIUS Clients (802.1X)';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">RADIUS Clients for 802.1X</h1>
                <p class="text-gray-600 mt-1">Manage RADIUS clients for network access control using BVI interface addresses</p>
            </div>
            <div class="flex space-x-3">
                <a href="/radius/logs" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Authentication Logs
                </a>
                <a href="/radius/import" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Import clients.conf
                </a>
                <?php if ($auth->isAdmin()): ?>
                <a href="/radius/settings" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Server Settings
                </a>
                <?php endif; ?>
                <button onclick="syncAllBvi()" 
                        class="inline-flex items-center px-4 py-2 border border-indigo-600 rounded-md shadow-sm text-sm font-medium text-indigo-600 bg-white hover:bg-indigo-50">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sync BVI Interfaces
                </button>
            </div>
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
                    <strong>802.1X Integration:</strong> RADIUS clients are automatically created from BVI interface IPv6 addresses. 
                    Each BVI interface acts as a NAS (Network Access Server) for 802.1X authentication on switches.
                </p>
            </div>
        </div>
    </div>

    <!-- Global Secret Configuration -->
    <div class="mb-6 bg-white shadow-md rounded-lg p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Global Shared Secret</h2>
                <p class="text-sm text-gray-600 mt-1">Configure one secret for all RADIUS clients (recommended for easier management)</p>
            </div>
            <button onclick="editGlobalSecret()" 
                    class="inline-flex items-center px-3 py-2 border border-indigo-600 rounded-md text-sm font-medium text-indigo-600 bg-white hover:bg-indigo-50">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Configure
            </button>
        </div>
        <div id="globalSecretStatus" class="flex items-center">
            <div class="animate-pulse flex space-x-2">
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
            </div>
        </div>
    </div>

    <!-- RADIUS Servers Status -->
    <div class="mb-6 bg-white shadow-md rounded-lg p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">FreeRADIUS Servers Status</h2>
                <p class="text-sm text-gray-600 mt-1">Monitor database sync status for both RADIUS servers</p>
            </div>
            <button onclick="forceSync()" 
                    class="inline-flex items-center px-3 py-2 border border-indigo-600 rounded-md text-sm font-medium text-indigo-600 bg-white hover:bg-indigo-50">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Force Sync All
            </button>
        </div>
        <div id="serversStatus" class="space-y-3">
            <div class="animate-pulse flex space-x-2">
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
                <div class="h-2 w-2 bg-gray-400 rounded-full"></div>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <input type="text" 
                   id="searchInput"
                   placeholder="Search by switch, BVI, or IP address..." 
                   onkeyup="performSearch(this.value)"
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- RADIUS Clients Table -->
    <div id="radiusTable" class="hidden bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Switch / BVI
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        NAS IP (IPv6)
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Short Name
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Shared Secret
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                    </th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="radiusTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Data will be loaded via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- No data message -->
    <div id="noDataMessage" class="hidden text-center py-8 bg-white shadow-md rounded-lg">
        <p class="text-gray-500 text-lg">No RADIUS clients found. Click "Sync BVI Interfaces" to create them automatically.</p>
    </div>
</div>

<!-- Global Secret Modal -->
<div id="globalSecretModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Configure Global Shared Secret</h3>
            <form id="globalSecretForm">
                <div class="mb-4">
                    <label for="global_secret" class="block text-sm font-medium text-gray-700 mb-2">
                        Global Shared Secret
                        <span class="text-xs text-gray-500">(used for all 802.1X clients)</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="global_secret" name="secret" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm pr-20">
                        <button type="button" onclick="toggleSecretVisibility('global_secret')"
                                class="absolute right-12 top-2 text-gray-500 hover:text-gray-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                        <button type="button" onclick="generateRandomSecret('global_secret')"
                                class="absolute right-2 top-2 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            Generate
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">This secret will be used for all RADIUS clients</p>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="apply_to_all" checked
                               class="form-checkbox h-4 w-4 text-indigo-600 rounded border-gray-300">
                        <span class="ml-2 text-sm text-gray-700">Apply to all existing RADIUS clients</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-6">Update the secret for all currently configured clients</p>
                </div>

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Important:</strong> After changing the global secret, you must update all your network switches with the new secret for 802.1X to work.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeGlobalSecretModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="saveGlobalSecretBtn"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Save Global Secret
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Secret Modal -->
<div id="editSecretModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit RADIUS Client</h3>
            <form id="editSecretForm">
                <input type="hidden" id="edit_client_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Switch / BVI Interface
                    </label>
                    <input type="text" id="edit_switch_info" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 cursor-not-allowed">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        NAS IP Address (IPv6)
                    </label>
                    <input type="text" id="edit_nasname" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 cursor-not-allowed font-mono text-sm">
                </div>
                
                <div class="mb-4">
                    <label for="edit_shortname" class="block text-sm font-medium text-gray-700 mb-2">
                        Short Name
                    </label>
                    <input type="text" id="edit_shortname" name="shortname" required
                           maxlength="32"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="mb-4">
                    <label for="edit_secret" class="block text-sm font-medium text-gray-700 mb-2">
                        Shared Secret
                        <span class="text-xs text-gray-500">(used for 802.1X authentication)</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="edit_secret" name="secret" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm pr-20">
                        <button type="button" onclick="toggleSecretVisibility('edit_secret')"
                                class="absolute right-12 top-2 text-gray-500 hover:text-gray-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                        <button type="button" onclick="generateRandomSecret('edit_secret')"
                                class="absolute right-2 top-2 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            Generate
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="edit_description" name="description" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="saveBtn"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global variables
let hasGlobalSecret = false;

// Load all data on page load
$(document).ready(function() {
    loadRadiusClients();
    loadGlobalSecretStatus();
    loadServersStatus();
    
    // Refresh server status every 30 seconds
    setInterval(loadServersStatus, 30000);
});

async function loadServersStatus() {
    try {
        const response = await fetch('/api/radius/servers/status');
        const data = await response.json();
        
        if (data.success) {
            displayServersStatus(data.servers);
        }
    } catch (error) {
        console.error('Error loading servers status:', error);
    }
}

function displayServersStatus(servers) {
    const statusDiv = $('#serversStatus');
    statusDiv.empty();
    
    for (const [serverName, status] of Object.entries(servers)) {
        let statusIcon, statusColor, statusText;
        
        if (status.status === 'online') {
            statusIcon = `<svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>`;
            statusColor = 'bg-green-50 border-green-200';
            statusText = 'Online';
        } else if (status.status === 'disabled') {
            statusIcon = `<svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
            </svg>`;
            statusColor = 'bg-gray-50 border-gray-200';
            statusText = 'Disabled';
        } else {
            statusIcon = `<svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>`;
            statusColor = 'bg-red-50 border-red-200';
            statusText = 'Error';
        }
        
        const serverCard = `
            <div class="border ${statusColor} rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        ${statusIcon}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">${escapeHtml(serverName)}</h3>
                            <p class="text-xs text-gray-600">${escapeHtml(status.message)}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">${statusText}</div>
                        ${status.client_count !== undefined ? `<div class="text-xs text-gray-500">${status.client_count} clients</div>` : ''}
                        ${status.response_time !== null ? `<div class="text-xs text-gray-500">${status.response_time}ms</div>` : ''}
                    </div>
                </div>
            </div>
        `;
        statusDiv.append(serverCard);
    }
}

async function forceSync() {
    const result = await Swal.fire({
        title: 'Force Sync to RADIUS Servers?',
        html: 'This will push all RADIUS clients from the Kea database to both FreeRADIUS server databases.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, sync now',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Syncing...',
                text: 'Pushing clients to RADIUS servers',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/radius/servers/sync', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                let html = `<p class="mb-3">${data.message}</p>`;
                
                if (data.errors && data.errors.length > 0) {
                    html += `<div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mt-3">
                        <p class="text-sm text-yellow-700 font-semibold mb-2">Warnings:</p>
                        <ul class="text-xs text-yellow-700 list-disc list-inside">
                            ${data.errors.map(err => `<li>${escapeHtml(err)}</li>`).join('')}
                        </ul>
                    </div>`;
                }
                
                await Swal.fire({
                    title: 'Sync Complete!',
                    html: html,
                    icon: data.errors.length > 0 ? 'warning' : 'success',
                    confirmButtonColor: '#6366f1'
                });
                loadServersStatus();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to sync to RADIUS servers',
                    icon: 'error',
                    confirmButtonColor: '#6366f1'
                });
            }
        } catch (error) {
            console.error('Error syncing:', error);
            Swal.fire({
                title: 'Error',
                text: 'An error occurred during sync',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    }
}

async function loadGlobalSecretStatus() {
    try {
        const response = await fetch('/api/radius/global-secret');
        const data = await response.json();
        
        const statusDiv = $('#globalSecretStatus');
        if (data.success) {
            hasGlobalSecret = data.has_global_secret;
            
            if (data.has_global_secret) {
                statusDiv.html(`
                    <div class="flex items-center space-x-2">
                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-green-700 font-medium">Global secret configured</span>
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded font-mono">${'•'.repeat(32)}</code>
                        <span class="text-xs text-gray-500">(Individual secrets are disabled)</span>
                    </div>
                `);
            } else {
                statusDiv.html(`
                    <div class="flex items-center space-x-2">
                        <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-yellow-700">No global secret configured - each client has individual secrets</span>
                    </div>
                `);
            }
            
            // Reload clients to update button states
            if (document.getElementById('radiusTableBody').children.length > 0) {
                loadRadiusClients();
            }
        }
    } catch (error) {
        console.error('Error loading global secret status:', error);
    }
}

async function loadRadiusClients() {
    try {
        console.log('Loading RADIUS clients...');
        const response = await fetch('/api/radius/clients');
        const data = await response.json();
        
        console.log('RADIUS clients loaded:', data);
        
        if (!data.success || !data.clients) {
            throw new Error('Failed to load RADIUS clients');
        }
        
        displayRadiusClients(data.clients);
        
    } catch (error) {
        console.error('Error loading RADIUS clients:', error);
        $('#loadingIndicator').hide();
        Swal.fire({
            title: 'Error',
            text: 'Failed to load RADIUS clients. Please try again.',
            icon: 'error',
            confirmButtonColor: '#6366f1'
        });
    }
}

function displayRadiusClients(clients) {
    $('#loadingIndicator').hide();
    
    if (clients.length === 0) {
        $('#noDataMessage').show();
        return;
    }
    
    const tbody = $('#radiusTableBody');
    tbody.empty();
    
    clients.forEach(client => {
        const switchInfo = client.switch_hostname 
            ? `${escapeHtml(client.switch_hostname)} / BVI${100 + parseInt(client.interface_number || 0)}`
            : 'Standalone Client';
        
        const maskedSecret = '•'.repeat(16);
        
        const row = `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                    ${switchInfo}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-mono">
                    ${escapeHtml(client.nasname)}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                    ${escapeHtml(client.shortname || 'N/A')}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-mono">
                    ${maskedSecret}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                        ${escapeHtml(client.type || 'other')}
                    </span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right text-sm">
                    ${hasGlobalSecret ? `
                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded text-gray-400 bg-gray-100 cursor-not-allowed" 
                              title="Individual secrets are disabled when global secret is configured">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Locked
                        </span>
                    ` : `
                        <button onclick='editClient(${JSON.stringify(client)})' 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            Edit
                        </button>
                    `}
                    ${!client.bvi_interface_id ? `
                        <button onclick="deleteClient(${client.id}, '${escapeHtml(client.shortname)}')"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    ` : `<span class="text-gray-400 text-xs italic">(Auto-managed)</span>`}
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    $('#radiusTable').show();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function editClient(client) {
    document.getElementById('edit_client_id').value = client.id;
    
    const switchInfo = client.switch_hostname 
        ? `${client.switch_hostname} / BVI${100 + parseInt(client.interface_number || 0)}`
        : 'Standalone Client';
    
    document.getElementById('edit_switch_info').value = switchInfo;
    document.getElementById('edit_nasname').value = client.nasname;
    document.getElementById('edit_shortname').value = client.shortname || '';
    document.getElementById('edit_secret').value = client.secret || '';
    document.getElementById('edit_description').value = client.description || '';
    
    document.getElementById('editSecretModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editSecretModal').classList.add('hidden');
    document.getElementById('editSecretForm').reset();
}

function toggleSecretVisibility(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function generateRandomSecret(inputId) {
    const secret = Array.from(crypto.getRandomValues(new Uint8Array(16)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    document.getElementById(inputId).value = secret;
}

document.getElementById('editSecretForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const clientId = document.getElementById('edit_client_id').value;
    const saveBtn = document.getElementById('saveBtn');
    
    const data = {
        shortname: document.getElementById('edit_shortname').value,
        secret: document.getElementById('edit_secret').value,
        description: document.getElementById('edit_description').value
    };
    
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    try {
        const response = await fetch(`/api/radius/clients/${clientId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            await Swal.fire({
                title: 'Success!',
                text: 'RADIUS client updated successfully. Make sure to update your network device configuration with the new secret.',
                icon: 'success',
                confirmButtonColor: '#6366f1'
            });
            closeEditModal();
            loadRadiusClients();
        } else {
            Swal.fire({
                title: 'Error',
                text: result.message || 'Failed to update RADIUS client',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while updating',
            icon: 'error',
            confirmButtonColor: '#6366f1'
        });
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
    }
});

async function deleteClient(clientId, shortname) {
    const result = await Swal.fire({
        title: 'Delete RADIUS Client?',
        html: `
            <div class="text-left">
                <p class="mb-3">You are about to delete RADIUS client <strong>${shortname}</strong></p>
                <p class="mb-3 text-red-600">⚠️ Warning:</p>
                <ul class="list-disc list-inside mb-3 text-sm">
                    <li>802.1X authentication will fail for this network device</li>
                    <li>Network access will be denied until reconfigured</li>
                </ul>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/radius/clients/${clientId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Deleted!',
                    text: 'RADIUS client has been deleted.',
                    icon: 'success',
                    confirmButtonColor: '#6366f1'
                });
                loadRadiusClients();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Error deleting RADIUS client',
                    icon: 'error',
                    confirmButtonColor: '#6366f1'
                });
            }
        } catch (error) {
            console.error('Error deleting client:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while deleting',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    }
}

async function syncAllBvi() {
    const result = await Swal.fire({
        title: 'Sync BVI Interfaces?',
        text: 'This will create RADIUS clients for any BVI interfaces that don\'t have one yet.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, sync now',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Syncing...',
                text: 'Creating RADIUS clients from BVI interfaces',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/radius/sync', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Sync Complete!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#6366f1'
                });
                loadRadiusClients();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to sync BVI interfaces',
                    icon: 'error',
                    confirmButtonColor: '#6366f1'
                });
            }
        } catch (error) {
            console.error('Error syncing:', error);
            Swal.fire({
                title: 'Error',
                text: 'An error occurred during sync',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    }
}

// Search functionality
function performSearch(searchTerm) {
    const rows = document.querySelectorAll("#radiusTableBody tr");
    const searchTermLower = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTermLower) ? "" : "none";
    });
}

// Close modal when clicking outside
document.getElementById('editSecretModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Global Secret Management
function editGlobalSecret() {
    document.getElementById('globalSecretModal').classList.remove('hidden');
    // Load current secret if exists
    fetch('/api/radius/global-secret')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_global_secret) {
                document.getElementById('global_secret').value = data.secret || '';
            }
        });
}

function closeGlobalSecretModal() {
    document.getElementById('globalSecretModal').classList.add('hidden');
    document.getElementById('globalSecretForm').reset();
}

document.getElementById('globalSecretForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const secret = document.getElementById('global_secret').value;
    const applyToAll = document.getElementById('apply_to_all').checked;
    const saveBtn = document.getElementById('saveGlobalSecretBtn');
    
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    try {
        const response = await fetch('/api/radius/global-secret', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                secret: secret,
                apply_to_all: applyToAll
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await Swal.fire({
                title: 'Success!',
                html: `
                    <div class="text-left">
                        <p class="mb-3">${result.message}</p>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Next steps:</strong><br>
                                1. Copy the secret to your clipboard<br>
                                2. Update all your network switches with this secret<br>
                                3. Restart 802.1X authentication on affected switches
                            </p>
                        </div>
                    </div>
                `,
                icon: 'success',
                confirmButtonColor: '#6366f1'
            });
            closeGlobalSecretModal();
            loadGlobalSecretStatus();
            loadRadiusClients();
        } else {
            Swal.fire({
                title: 'Error',
                text: result.message || 'Failed to update global secret',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while updating',
            icon: 'error',
            confirmButtonColor: '#6366f1'
        });
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Global Secret';
    }
});

document.getElementById('globalSecretModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGlobalSecretModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
