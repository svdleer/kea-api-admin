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

$currentPage = 'admin-tools';
$title = 'Admin Tools - Backup & Import';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Admin Tools</h1>
        <p class="text-gray-600 mt-1">Backup, restore, import and export configurations</p>
    </div>

    <!-- Tools Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Kea Configuration -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-blue-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Kea Configuration</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Export current configuration or import from kea-dhcp6.conf</p>
            <div class="space-y-2">
                <button onclick="exportKeaConfig()" 
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export Config
                </button>
                <button onclick="importKeaConfig()" 
                        class="w-full px-4 py-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Import Config (Quick)
                </button>
                <a href="/admin/import-wizard" 
                   class="w-full px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-md hover:from-blue-700 hover:to-purple-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Import Wizard (Recommended)
                </a>
            </div>
        </div>

        <!-- Kea Database (Config) -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-green-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Kea Database</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Backup configuration database (subnets, options, BVI interfaces)</p>
            <div class="space-y-2">
                <button onclick="backupKeaDatabase()" 
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Backup Database
                </button>
                <button onclick="restoreKeaDatabase()" 
                        class="w-full px-4 py-2 border border-green-600 text-green-600 rounded-md hover:bg-green-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Restore Database
                </button>
            </div>
        </div>

        <!-- Kea Leases -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-purple-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Kea Leases</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Backup active DHCP leases database or import existing leases as static reservations</p>
            <div class="space-y-2">
                <button onclick="backupKeaLeases()" 
                        class="w-full px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Backup Leases
                </button>
                <button onclick="exportKeaLeases()" 
                        class="w-full px-4 py-2 border border-purple-600 text-purple-600 rounded-md hover:bg-purple-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export to CSV
                </button>
                <button onclick="importLeasesWizard()" 
                        class="w-full px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-md hover:from-purple-700 hover:to-pink-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Import Leases from CSV
                </button>
            </div>
        </div>

        <!-- Clear CIN Data -->
        <div class="bg-white shadow-md rounded-lg p-6 border-2 border-red-200">
            <div class="flex items-center mb-4">
                <div class="bg-red-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Clear CIN Data</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Delete all CIN switches and BVI data. Kea subnets remain untouched.</p>
            <div class="space-y-2">
                <button onclick="clearCinData()" 
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Clear All CIN Data
                </button>
                <p class="text-xs text-red-600 mt-2">⚠️ Warning: This deletes all CIN switches, BVI interfaces, and links. Cannot be undone!</p>
            </div>
        </div>

        <!-- FreeRADIUS Configuration -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-indigo-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">RADIUS Config</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Export RADIUS clients for FreeRADIUS</p>
            <div class="space-y-2">
                <button onclick="exportRadiusClients()" 
                        class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export Clients
                </button>
                <button onclick="syncRadiusToServers()" 
                        class="w-full px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sync to Servers
                </button>
            </div>
        </div>

        <!-- FreeRADIUS Database -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">RADIUS Database</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Backup FreeRADIUS databases (Primary & Secondary)</p>
            <div class="space-y-2">
                <button onclick="backupRadiusDatabase('primary')" 
                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Backup Primary
                </button>
                <button onclick="backupRadiusDatabase('secondary')" 
                        class="w-full px-4 py-2 border border-yellow-600 text-yellow-600 rounded-md hover:bg-yellow-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Backup Secondary
                </button>
            </div>
        </div>

        <!-- Full System Backup -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-red-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Full Backup</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Backup everything at once</p>
            <div class="space-y-2">
                <button onclick="fullSystemBackup()" 
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                    Full Backup
                </button>
                <div class="text-xs text-gray-500 text-center">
                    Includes: Kea DB, Leases, RADIUS DBs
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Backups -->
    <div class="mt-8 bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Backups</h2>
        <div id="recentBackups">
            <div class="text-center text-gray-500 py-4">
                Loading backup history...
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Load recent backups on page load
$(document).ready(function() {
    loadRecentBackups();
});

async function exportKeaConfig() {
    Swal.fire({
        title: 'Export Kea Configuration',
        text: 'This will generate a kea-dhcp6.conf compatible file',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3B82F6',
        confirmButtonText: 'Export',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/api/admin/export/kea-config';
        }
    });
}

async function importKeaConfig() {
    const { value: file } = await Swal.fire({
        title: 'Import Kea Configuration',
        html: `
            <p class="text-sm text-gray-600 mb-4">Upload your kea-dhcp6.conf file</p>
            <input type="file" id="keaConfigFile" accept=".conf,.json" class="block w-full text-sm text-gray-500
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-semibold
                file:bg-blue-50 file:text-blue-700
                hover:file:bg-blue-100"/>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3B82F6',
        confirmButtonText: 'Import',
        preConfirm: () => {
            const file = document.getElementById('keaConfigFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a file');
            }
            return file;
        }
    });

    if (file) {
        const formData = new FormData();
        formData.append('config', file);

        try {
            Swal.fire({
                title: 'Importing...',
                text: 'Processing configuration file',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/admin/import/kea-config', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'Import Complete!',
                    html: `
                        <p class="mb-2">${result.message}</p>
                        <div class="text-sm text-left bg-gray-50 p-3 rounded">
                            <p><strong>Subnets:</strong> ${result.stats.subnets.imported} imported, ${result.stats.subnets.skipped} skipped</p>
                            <p><strong>Reservations:</strong> ${result.stats.reservations.imported} imported, ${result.stats.reservations.skipped} skipped</p>
                            <p><strong>Options:</strong> ${result.stats.options.imported} imported, ${result.stats.options.skipped} skipped</p>
                        </div>
                        ${result.debug_output ? `
                            <details class="mt-3 text-left">
                                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800">Show Debug Output</summary>
                                <pre class="text-xs bg-gray-100 p-2 rounded mt-2 overflow-auto max-h-64 whitespace-pre-wrap">${result.debug_output}</pre>
                            </details>
                        ` : ''}
                    `,
                    icon: 'success',
                    confirmButtonColor: '#3B82F6',
                    width: '700px'
                });
            } else {
                Swal.fire({
                    title: 'Import Failed',
                    html: `
                        <p class="mb-2 text-red-600">${result.message}</p>
                        <div class="text-xs text-left bg-gray-50 p-3 rounded overflow-auto max-h-64">
                            <pre class="whitespace-pre-wrap">${result.error || result.output || 'Unknown error'}</pre>
                        </div>
                        ${result.details ? `
                            <div class="text-xs text-left mt-2 text-gray-600">
                                <p>File: ${result.details.filename}</p>
                                <p>Size: ${result.details.size} bytes</p>
                            </div>
                        ` : ''}
                    `,
                    icon: 'error',
                    width: '600px',
                    confirmButtonColor: '#DC2626'
                });
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to import configuration', 'error');
        }
    }
}

async function backupKeaDatabase() {
    Swal.fire({
        title: 'Backup Kea Database',
        text: 'Create a backup of the configuration database (saved on server)',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        confirmButtonText: 'Create Backup'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Creating Backup...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('/api/admin/backup/kea-database');
                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Backup Created!',
                        html: `
                            <p class="mb-2">${data.message}</p>
                            <div class="text-sm text-left bg-gray-50 p-3 rounded">
                                <p><strong>File:</strong> ${data.filename}</p>
                                <p><strong>Size:</strong> ${data.size}</p>
                                <p class="mt-2 text-gray-600">Backup saved on server. Keeping last 7 backups.</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#10B981'
                    }).then(() => {
                        loadRecentBackups();
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Failed to create backup', 'error');
            }
        }
    });
}

async function restoreKeaDatabase() {
    const { value: file } = await Swal.fire({
        title: 'Restore Kea Database',
        html: `
            <p class="text-sm text-red-600 mb-4">⚠️ This will overwrite current database!</p>
            <input type="file" id="backupFile" accept=".sql" class="block w-full text-sm text-gray-500
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-semibold
                file:bg-green-50 file:text-green-700
                hover:file:bg-green-100"/>
        `,
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        confirmButtonText: 'Restore',
        preConfirm: () => {
            const file = document.getElementById('backupFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a backup file');
            }
            return file;
        }
    });

    if (file) {
        // Implementation pending
        Swal.fire('Info', 'Database restore functionality coming soon', 'info');
    }
}

async function backupKeaLeases() {
    try {
        Swal.fire({
            title: 'Creating Backup...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/api/admin/backup/kea-leases');
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Backup Created!',
                html: `
                    <p class="mb-2">${data.message}</p>
                    <div class="text-sm text-left bg-gray-50 p-3 rounded">
                        <p><strong>File:</strong> ${data.filename}</p>
                        <p><strong>Size:</strong> ${data.size}</p>
                        <p class="mt-2 text-gray-600">Backup saved on server. Keeping last 7 backups.</p>
                    </div>
                `,
                icon: 'success'
            }).then(() => {
                loadRecentBackups();
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to create backup', 'error');
    }
}

async function exportKeaLeases() {
    window.location.href = '/api/admin/export/kea-leases-csv';
}

async function exportRadiusClients() {
    window.location.href = '/api/admin/export/radius-clients';
}

async function syncRadiusToServers() {
    try {
        Swal.fire({
            title: 'Syncing...',
            text: 'Pushing RADIUS clients to servers',
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
            Swal.fire({
                title: 'Sync Complete!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#6366F1'
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to sync to servers', 'error');
    }
}

async function backupRadiusDatabase(type) {
    const serverName = type === 'primary' ? 'Primary' : 'Secondary';
    
    Swal.fire({
        title: `Backup RADIUS ${serverName}`,
        text: `Create backup of ${serverName} FreeRADIUS database (saved on server)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#EAB308',
        confirmButtonText: 'Create Backup'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Creating Backup...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch(`/api/admin/backup/radius-database/${type}`);
                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Backup Created!',
                        html: `
                            <p class="mb-2">${data.message}</p>
                            <div class="text-sm text-left bg-gray-50 p-3 rounded">
                                <p><strong>File:</strong> ${data.filename}</p>
                                <p><strong>Size:</strong> ${data.size}</p>
                                <p class="mt-2 text-gray-600">Backup saved on server. Keeping last 7 backups per type.</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#EAB308'
                    }).then(() => {
                        loadRecentBackups();
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Failed to create backup', 'error');
            }
        }
    });
}

async function fullSystemBackup() {
    Swal.fire({
        title: 'Full System Backup',
        html: `
            <p class="text-sm text-gray-600 mb-2">This will backup:</p>
            <ul class="text-sm text-left text-gray-700 mb-4">
                <li>✓ Kea configuration database</li>
                <li>✓ Kea leases database</li>
                <li>✓ Primary RADIUS database</li>
                <li>✓ Secondary RADIUS database</li>
            </ul>
            <p class="text-sm text-gray-500">Backups will be combined into a ZIP file</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        confirmButtonText: 'Create Full Backup'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/api/admin/backup/full-system';
        }
    });
}

async function loadRecentBackups() {
    try {
        const response = await fetch('/api/admin/backups/list');
        const data = await response.json();

        if (data.success && data.backups.length > 0) {
            displayBackups(data.backups);
        } else {
            $('#recentBackups').html('<div class="text-center text-gray-500 py-4">No backups found</div>');
        }
    } catch (error) {
        $('#recentBackups').html('<div class="text-center text-red-500 py-4">Error loading backups</div>');
    }
}

function displayBackups(backups) {
    let html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">';
    html += '<thead class="bg-gray-50">';
    html += '<tr>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>';
    html += '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>';
    html += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

    backups.forEach(backup => {
        html += '<tr>';
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${backup.filename}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.type}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.size}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.date}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">`;
        html += `<button onclick="restoreBackup('${backup.filename}')" class="text-green-600 hover:text-green-900 mr-3">Restore</button>`;
        html += `<a href="/api/admin/backup/download/${backup.filename}" class="text-indigo-600 hover:text-indigo-900 mr-3">Download</a>`;
        html += `<button onclick="deleteBackup('${backup.filename}')" class="text-red-600 hover:text-red-900">Delete</button>`;
        html += `</td></tr>`;
    });

    html += '</tbody></table></div>';
    $('#recentBackups').html(html);
}

async function restoreBackup(filename) {
    const result = await Swal.fire({
        title: 'Restore Backup?',
        html: `
            <p class="text-red-600 font-bold mb-2">⚠️ WARNING ⚠️</p>
            <p class="mb-2">This will OVERWRITE the current database!</p>
            <p class="text-sm text-gray-600">Restore from: <strong>${filename}</strong></p>
            <p class="text-sm text-gray-600 mt-2">It is recommended to create a backup of the current database before restoring.</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Restore!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Restoring...',
                text: 'Please wait, this may take a moment',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch(`/api/admin/restore/server-backup/${filename}`, {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Restored!',
                    text: data.message,
                    icon: 'success'
                }).then(() => {
                    // Reload page to reflect changes
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to restore backup', 'error');
        }
    }
}

async function deleteBackup(filename) {
    const result = await Swal.fire({
        title: 'Delete Backup?',
        text: `Delete ${filename}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        confirmButtonText: 'Delete'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/admin/backup/delete/${filename}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire('Deleted!', 'Backup has been deleted', 'success');
                loadRecentBackups();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to delete backup', 'error');
        }
    }
}

async function clearCinData() {
    const result = await Swal.fire({
        title: 'Clear All CIN Data?',
        html: `
            <div class="text-left">
                <p class="mb-3 text-red-600 font-semibold">⚠️ This will permanently delete:</p>
                <ul class="list-disc list-inside mb-3 text-sm">
                    <li>All CIN switches</li>
                    <li>All BVI interfaces</li>
                    <li>All subnet-BVI links</li>
                    <li>All RADIUS clients</li>
                </ul>
                <p class="text-sm text-gray-600 mb-2">✓ Kea subnets will NOT be deleted</p>
                <p class="text-sm text-gray-600 mb-2">✓ Only our database tables are cleared</p>
                <p class="mt-3 font-semibold">This action cannot be undone!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Clear All CIN Data!',
        cancelButtonText: 'Cancel',
        width: '600px'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Clearing CIN Data...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/admin/clear-cin-data', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Cleared!',
                    html: `
                        <div class="text-left">
                            <p class="mb-2">✓ Deleted ${data.switches || 0} CIN switches</p>
                            <p class="mb-2">✓ Deleted ${data.bvi_interfaces || 0} BVI interfaces</p>
                            <p class="mb-2">✓ Deleted ${data.links || 0} subnet links</p>
                            <p class="mb-2">✓ Deleted ${data.radius_clients || 0} RADIUS clients</p>
                            <p class="mt-3 text-sm text-gray-600">Ready for fresh import!</p>
                        </div>
                    `,
                    icon: 'success'
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to clear CIN data: ' + error.message, 'error');
        }
    }
}

// Import Leases Wizard
async function importLeasesWizard() {
    const { value: file } = await Swal.fire({
        title: 'Import Kea Leases from CSV',
        html: `
            <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">
                    This will import leases directly from a Kea CSV lease file (dhcp6.leases) 
                    into the Kea lease database as active leases.
                </p>
                <div class="mb-4 p-4 bg-blue-50 rounded-md">
                    <p class="text-sm font-semibold text-blue-900 mb-2">CSV Format Expected:</p>
                    <code class="text-xs text-gray-700">address,duid,valid_lifetime,expire,subnet_id,...</code>
                </div>
                <div class="mb-4 p-4 bg-yellow-50 rounded-md">
                    <p class="text-sm font-semibold text-yellow-900 mb-2">⚠️ Subnet ID Mapping:</p>
                    <p class="text-xs text-yellow-800 mb-2">If your CSV subnet IDs don't match your current Kea configuration, you'll be able to map them in the next step.</p>
                </div>
                <input type="file" id="leases-file" accept=".csv,.leases" 
                       class="block w-full text-sm text-gray-500
                       file:mr-4 file:py-2 file:px-4
                       file:rounded-md file:border-0
                       file:text-sm file:font-semibold
                       file:bg-purple-50 file:text-purple-700
                       hover:file:bg-purple-100">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Next: Map Subnets',
        confirmButtonColor: '#9333EA',
        preConfirm: () => {
            const fileInput = document.getElementById('leases-file');
            if (!fileInput.files[0]) {
                Swal.showValidationMessage('Please select a CSV file');
                return false;
            }
            return fileInput.files[0];
        }
    });

    if (file) {
        await analyzeAndMapSubnets(file);
    }
}

async function analyzeAndMapSubnets(file) {
    try {
        // First, analyze the CSV to find subnet IDs
        Swal.fire({
            title: 'Analyzing CSV...',
            text: 'Detecting subnet IDs',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const text = await file.text();
        const lines = text.split('\n');
        const header = lines[0].split(',');
        const subnetIdIndex = header.indexOf('subnet_id');
        
        // Find unique subnet IDs in CSV
        const subnetIds = new Set();
        for (let i = 1; i < lines.length; i++) {
            const row = lines[i].split(',');
            if (row[subnetIdIndex]) {
                subnetIds.add(row[subnetIdIndex]);
            }
        }
        
        const uniqueSubnets = Array.from(subnetIds).filter(id => id && id !== '0');
        
        Swal.close();
        
        if (uniqueSubnets.length === 0) {
            Swal.fire('Error', 'No valid subnet IDs found in CSV', 'error');
            return;
        }

        // Show subnet mapping dialog
        const { value: mapping } = await Swal.fire({
            title: 'Map Subnet IDs',
            html: `
                <div class="text-left">
                    <p class="mb-4 text-sm text-gray-600">
                        Map old subnet IDs from CSV to your current Kea subnet IDs:
                    </p>
                    <div class="space-y-3">
                        ${uniqueSubnets.map(oldId => `
                            <div class="flex items-center gap-2">
                                <label class="text-sm font-medium text-gray-700 w-32">Old ID: ${oldId} →</label>
                                <input type="number" 
                                       id="subnet-map-${oldId}" 
                                       placeholder="New subnet ID"
                                       value="${oldId}"
                                       min="1"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-purple-500">
                            </div>
                        `).join('')}
                    </div>
                    <p class="mt-3 text-xs text-gray-500">💡 Tip: Leave unchanged if IDs are the same</p>
                </div>
            `,
            width: '500px',
            showCancelButton: true,
            confirmButtonText: 'Import with Mapping',
            confirmButtonColor: '#9333EA',
            preConfirm: () => {
                const mapping = {};
                for (const oldId of uniqueSubnets) {
                    const newId = document.getElementById(`subnet-map-${oldId}`).value;
                    if (!newId || newId === '0') {
                        Swal.showValidationMessage(`Please provide a valid subnet ID for ${oldId}`);
                        return false;
                    }
                    mapping[oldId] = newId;
                }
                return mapping;
            }
        });

        if (mapping) {
            await processLeasesFile(file, mapping);
        }
    } catch (error) {
        Swal.close();
        Swal.fire('Error', 'Failed to analyze CSV: ' + error.message, 'error');
    }
}

async function processLeasesFile(file, subnetMapping = {}) {
    try {
        Swal.fire({
            title: 'Processing Leases...',
            text: 'Importing leases with subnet mapping',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('leases_file', file);
        formData.append('subnet_mapping', JSON.stringify(subnetMapping));

        const response = await fetch('/api/admin/import-leases', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        Swal.close();

        if (data.success) {
            await Swal.fire({
                title: 'Import Complete!',
                html: `
                    <div class="text-left">
                        <p class="mb-2">✓ Processed ${data.total || 0} leases from CSV</p>
                        <p class="mb-2">✓ Imported ${data.imported || 0} active leases</p>
                        <p class="mb-2">⊘ Skipped ${data.skipped || 0} (expired or invalid)</p>
                        ${data.errors && data.errors.length > 0 ? `
                            <div class="mt-3 p-3 bg-yellow-50 rounded">
                                <p class="text-sm font-semibold text-yellow-900 mb-1">Warnings:</p>
                                <ul class="text-xs text-yellow-800 list-disc list-inside">
                                    ${data.errors.slice(0, 5).map(e => `<li>${e}</li>`).join('')}
                                    ${data.errors.length > 5 ? `<li>... and ${data.errors.length - 5} more</li>` : ''}
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                `,
                icon: 'success',
                confirmButtonColor: '#9333EA'
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to import leases', 'error');
        }
    } catch (error) {
        Swal.close();
        Swal.fire('Error', 'Failed to process file: ' + error.message, 'error');
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>