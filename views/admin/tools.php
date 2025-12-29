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
            <p class="text-gray-600 text-sm mb-4">Import configuration from kea-dhcp6.conf</p>
            <div class="space-y-2">
                <a href="/admin/import-wizard" 
                   class="w-full px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-md hover:from-blue-700 hover:to-purple-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Import Wizard
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
                <button onclick="importLeasesJSONWizard()" 
                        class="w-full px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-md hover:from-indigo-700 hover:to-purple-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Import Leases from JSON
                </button>
                <button onclick="deleteAllLeases()" 
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete All Leases
                </button>
            </div>
        </div>

        <!-- Kea Reservations -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center mb-4">
                <div class="bg-indigo-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Kea Reservations</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Import static DHCP reservations (with options) from kea-dhcp6.conf</p>
            <div class="space-y-2">
                <button onclick="importKeaReservations()" 
                        class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Import Reservations from Config
                </button>
                <button onclick="listReservations()" 
                        class="w-full px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    List All Reservations
                </button>
                <button onclick="deleteAllReservations()" 
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete All Reservations
                </button>
                <p class="text-xs text-red-600 mt-2">⚠️ Warning: Deletes all reservations from all subnets. Cannot be undone!</p>
            </div>
        </div>

        <!-- Save Configuration -->
        <div class="bg-white shadow-md rounded-lg p-6 border-2 border-green-200">
            <div class="flex items-center mb-4">
                <div class="bg-green-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Save Configuration</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Write current Kea configuration to disk on all servers</p>
            <div class="space-y-2">
                <button onclick="saveKeaConfig()" 
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Config to Disk
                </button>
                <p class="text-xs text-gray-600 mt-2">💾 Persists configuration to /etc/kea/kea-dhcp6.conf on all HA servers</p>
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

        <!-- RADIUS Cleanup -->
        <div class="bg-white shadow-md rounded-lg p-6 border-2 border-yellow-200">
            <div class="flex items-center mb-4">
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">RADIUS Cleanup</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">Check and clean orphaned RADIUS entries</p>
            <div class="space-y-2">
                <button onclick="checkRadiusOrphans()" 
                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Check for Orphans
                </button>
                <button onclick="cleanRadiusOrphans()" 
                        class="w-full px-4 py-2 border border-yellow-600 text-yellow-600 rounded-md hover:bg-yellow-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Clean Orphans
                </button>
                <p class="text-xs text-yellow-600 mt-2">ℹ️ Removes RADIUS entries for deleted BVI interfaces</p>
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

        <!-- Kea Config Viewer -->
        <div class="bg-white shadow-md rounded-lg p-6 border-2 border-cyan-200">
            <div class="flex items-center mb-4">
                <div class="bg-cyan-100 p-3 rounded-lg">
                    <svg class="h-8 w-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                </div>
                <h2 class="ml-4 text-xl font-semibold text-gray-900">Kea Config Viewer</h2>
            </div>
            <p class="text-gray-600 text-sm mb-4">View current Kea DHCPv6 configuration with syntax highlighting</p>
            <div class="space-y-2">
                <button onclick="viewKeaConfig()" 
                        class="w-full px-4 py-2 bg-cyan-600 text-white rounded-md hover:bg-cyan-700 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View Config
                </button>
                <button onclick="downloadKeaConfigJSON()" 
                        class="w-full px-4 py-2 border border-cyan-600 text-cyan-600 rounded-md hover:bg-cyan-50 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download JSON
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
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Kea Configuration Backups</h2>
        <p class="text-sm text-gray-600 mb-4">Automatic backups created before option-def changes. Last 12 backups are kept.</p>
        <div id="keaConfigBackups">
            <div class="text-center text-gray-500 py-4">
                Loading Kea config backups...
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Load recent backups on page load
$(document).ready(function() {
    loadKeaConfigBackups();
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
                            <p><strong>Reservations Added (new):</strong> ${result.stats.reservations.imported || 0}</p>
                            <p><strong>Reservations Updated (existing):</strong> ${result.stats.reservations.updated || 0}</p>
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

async function importKeaReservations() {
    const { value: formData } = await Swal.fire({
        title: 'Import Kea Reservations',
        html: `
            <p class="text-sm text-gray-600 mb-4">Upload your kea-dhcp6.conf file to import static DHCP reservations</p>
            <div class="bg-blue-50 p-3 rounded mb-4 text-sm text-left">
                <p class="font-semibold mb-1">What this does:</p>
                <ul class="list-disc list-inside text-gray-700">
                    <li>Reads all reservations from the config file</li>
                    <li>Imports them into Kea's reservation database</li>
                    <li>Syncs to all active Kea servers</li>
                    <li>Skips duplicates automatically</li>
                </ul>
            </div>
            <input type="file" id="keaReservationFile" accept=".conf,.json" class="block w-full text-sm text-gray-500 mb-3
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-semibold
                file:bg-indigo-50 file:text-indigo-700
                hover:file:bg-indigo-100"/>
            <div class="mt-3 flex items-start">
                <input type="checkbox" id="extractHostnames" class="mt-1" checked />
                <label for="extractHostnames" class="ml-2 text-sm text-gray-700">
                    Extract hostnames from comments<br>
                    <span class="text-xs text-gray-500">Comments above reservations will be used as hostnames</span>
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#4F46E5',
        confirmButtonText: 'Next: Review Hostnames',
        width: '600px',
        preConfirm: () => {
            const file = document.getElementById('keaReservationFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a file');
            }
            const extractHostnames = document.getElementById('extractHostnames').checked;
            return { file, extractHostnames };
        }
    });

    if (formData) {
        const uploadFormData = new FormData();
        uploadFormData.append('config', formData.file);
        uploadFormData.append('extract_hostnames', formData.extractHostnames ? '1' : '0');
        uploadFormData.append('preview', '1');

        try {
            // Step 1: Get preview with hostname suggestions
            Swal.fire({
                title: 'Analyzing File...',
                html: 'Reading reservations and extracting hostnames',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const previewResponse = await fetch('/api/admin/import/kea-reservations', {
                method: 'POST',
                body: uploadFormData
            });

            const previewResult = await previewResponse.json();

            if (!previewResult.success) {
                Swal.fire('Error', previewResult.message || 'Failed to analyze file', 'error');
                return;
            }

            // Step 2: Show preview with hostname editing
            if (formData.extractHostnames && previewResult.reservations) {
                let tableHTML = `
                    <div class="text-left mb-3">
                        <p class="text-sm text-gray-600">Found ${previewResult.total_reservations} reservations. Edit hostnames below:</p>
                    </div>
                    <div class="max-h-96 overflow-y-auto border rounded">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-gray-100">
                                <tr>
                                    <th class="p-2 border text-left">MAC Address</th>
                                    <th class="p-2 border text-left">IP Address</th>
                                    <th class="p-2 border text-left">Hostname</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                previewResult.reservations.forEach((res, idx) => {
                    tableHTML += `<tr>`;
                    tableHTML += `<td class="p-2 border text-xs">${res.hw_address || '-'}</td>`;
                    tableHTML += `<td class="p-2 border text-xs">${res.ip_address || '-'}</td>`;
                    tableHTML += `<td class="p-2 border">`;
                    tableHTML += `<input type="text" class="hostname-input w-full p-1 text-xs border rounded" data-idx="${idx}" value="${res.hostname || ''}" placeholder="Enter hostname..." />`;
                    tableHTML += `</td></tr>`;
                });
                
                tableHTML += `</tbody></table></div>`;
                
                const { value: confirmed } = await Swal.fire({
                    title: 'Review Hostnames',
                    html: tableHTML,
                    width: '900px',
                    showCancelButton: true,
                    confirmButtonText: 'Import with Hostnames',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4F46E5',
                    allowEnterKey: false,
                    preConfirm: () => {
                        const hostnames = [];
                        document.querySelectorAll('.hostname-input').forEach(input => {
                            hostnames.push(input.value);
                        });
                        return hostnames;
                    }
                });
                
                if (!confirmed) return;
                
                // Step 3: Import with edited hostnames
                const importFormData = new FormData();
                importFormData.append('config', formData.file);
                importFormData.append('extract_hostnames', '1');
                importFormData.append('hostnames', JSON.stringify(confirmed));
                
                Swal.fire({
                    title: 'Importing Reservations...',
                    html: 'Adding reservations with hostnames to Kea<br><small class="text-gray-600">This may take a while...</small>',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('/api/admin/import/kea-reservations', {
                    method: 'POST',
                    body: importFormData
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        title: 'Import Complete!',
                        html: `
                            <p class="mb-3">${result.message}</p>
                            <div class="text-sm text-left bg-gray-50 p-4 rounded space-y-2">
                                <div class="flex justify-between text-green-600">
                                    <span>Added (new):</span>
                                    <strong>${result.stats.reservations.imported || 0}</strong>
                                </div>
                                <div class="flex justify-between text-blue-600">
                                    <span>Updated (existing):</span>
                                    <strong>${result.stats.reservations.updated || 0}</strong>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#4F46E5',
                        width: '600px'
                    });
                } else {
                    Swal.fire('Error', result.message || 'Import failed', 'error');
                }
            } else {
                // Import without hostnames - skip review
                const importFormData = new FormData();
                importFormData.append('config', formData.file);
                
                Swal.fire({
                    title: 'Importing Reservations...',
                    html: 'Adding reservations to Kea<br><small class="text-gray-600">This may take a while...</small>',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('/api/admin/import/kea-reservations', {
                    method: 'POST',
                    body: importFormData
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        title: 'Import Complete!',
                        html: `
                            <p class="mb-3">${result.message}</p>
                            <div class="text-sm text-left bg-gray-50 p-4 rounded space-y-2">
                                <div class="flex justify-between text-green-600">
                                    <span>Added (new):</span>
                                    <strong>${result.stats.reservations.imported || 0}</strong>
                                </div>
                                <div class="flex justify-between text-blue-600">
                                    <span>Updated (existing):</span>
                                    <strong>${result.stats.reservations.updated || 0}</strong>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#4F46E5',
                        width: '600px'
                    });
                } else {
                    Swal.fire('Error', result.message || 'Import failed', 'error');
                }
            }
        } catch (error) {
            console.error('Import error:', error);
            Swal.fire('Error', 'Failed to import reservations: ' + error.message, 'error');
        }
    }
}

                        <p class="mb-2 text-red-600">${result.message}</p>
                        <div class="text-xs text-left bg-gray-50 p-3 rounded overflow-auto max-h-64">
                            <pre class="whitespace-pre-wrap">${result.error || 'Unknown error'}</pre>
                        </div>
                    `,
                    icon: 'error',
                    width: '600px',
                    confirmButtonColor: '#DC2626'
                });
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to import reservations: ' + error.message, 'error');
        }
    }
}

async function listReservations() {
    try {
        Swal.fire({
            title: 'Loading Reservations...',
            text: 'Fetching reservations from all subnets',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        console.log('Fetching subnets...');
        // Get all subnets first
        const subnetsResponse = await fetch('/api/dhcp/subnets');
        const subnetsData = await subnetsResponse.json();
        
        console.log('Subnets response:', subnetsData);
        
        // API returns array directly, not wrapped in {success, subnets}
        const subnets = Array.isArray(subnetsData) ? subnetsData : [];
        
        if (subnets.length === 0) {
            throw new Error('No subnets found');
        }

        console.log(`Found ${subnets.length} subnets`);

        // Fetch reservations for each subnet
        const allReservations = [];
        for (const subnet of subnets) {
            console.log(`Fetching reservations for subnet ${subnet.id}...`);
            try {
                const response = await fetch(`/api/dhcp/static/${subnet.id}`);
                const result = await response.json();
                
                console.log(`Subnet ${subnet.id} response:`, result);
                
                // API returns Kea response directly: {result: 0, hosts: [...]}
                // Result code 0 = success with data, result code 3 = success but empty
                if (result.result === 0 && result.hosts && result.hosts.length > 0) {
                    console.log(`Found ${result.hosts.length} reservations in subnet ${subnet.id}`);
                    result.hosts.forEach(host => {
                        host.subnet_id = subnet.id;
                        host.subnet_prefix = subnet.subnet;
                        allReservations.push(host);
                    });
                } else if (result.result === 3) {
                    // Result code 3 means no data (empty)
                    console.log(`No reservations in subnet ${subnet.id}`);
                } else {
                    console.log(`Unexpected response for subnet ${subnet.id}:`, result);
                }
            } catch (e) {
                console.error(`Error fetching reservations for subnet ${subnet.id}:`, e);
            }
        }

        console.log(`Total reservations found: ${allReservations.length}`);

        let html = `<div class="text-left">
            <p class="mb-3">Found <strong>${allReservations.length}</strong> reservation(s) across ${subnets.length} subnet(s)</p>`;
        
        if (allReservations.length > 0) {
            html += `<div class="max-h-96 overflow-y-auto text-xs">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold">Subnet</th>
                            <th class="px-2 py-2 text-left font-semibold">IP Address</th>
                            <th class="px-2 py-2 text-left font-semibold">DUID</th>
                            <th class="px-2 py-2 text-left font-semibold">MAC Address</th>
                            <th class="px-2 py-2 text-left font-semibold">Hostname</th>
                            <th class="px-2 py-2 text-left font-semibold">Options</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">`;
            
            allReservations.forEach(res => {
                const ipAddresses = res['ip-addresses'] || [];
                const duid = res['duid'] || '-';
                const hwAddress = res['hw-address'] || '-';
                const hostname = res['hostname'] || '-';
                const optionData = res['option-data'] || [];
                const optionCount = optionData.length;
                
                html += `<tr class="hover:bg-gray-50">
                    <td class="px-2 py-2 font-mono text-xs">${res.subnet_id}<br/><span class="text-gray-400">${res.subnet_prefix || ''}</span></td>
                    <td class="px-2 py-2 font-mono">${ipAddresses.join('<br/>')}</td>
                    <td class="px-2 py-2 font-mono text-xs">${duid.length > 30 ? duid.substring(0, 30) + '...' : duid}</td>
                    <td class="px-2 py-2 font-mono text-xs text-green-700">${hwAddress}</td>
                    <td class="px-2 py-2">${hostname}</td>
                    <td class="px-2 py-2">${optionCount > 0 ? `<span class="text-green-600">${optionCount} option(s)</span>` : '<span class="text-gray-400">-</span>'}</td>
                </tr>`;
            });
            
            html += `</tbody></table></div>`;
        } else {
            html += `<div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-2 text-gray-500">No reservations found</p>
                <p class="text-xs text-gray-400 mt-1">Import reservations from a config file to see them here</p>
                <button onclick="Swal.close(); importKeaReservations();" class="mt-3 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Import Reservations Now
                </button>
            </div>`;
        }
        
        html += `</div>`;
        
        Swal.fire({
            title: 'Kea Reservations',
            html: html,
            icon: allReservations.length > 0 ? 'info' : 'warning',
            confirmButtonColor: '#4F46E5',
            width: '900px'
        });
    } catch (error) {
        console.error('Error in listReservations:', error);
        Swal.fire('Error', 'Failed to fetch reservations: ' + error.message, 'error');
    }
}

async function deleteAllReservations() {
    const result = await Swal.fire({
        title: 'Delete All Reservations?',
        html: `
            <div class="text-left">
                <p class="text-red-600 font-semibold mb-3">⚠️ This will permanently delete ALL reservations from ALL subnets!</p>
                <p class="text-gray-600 mb-2">This action will:</p>
                <ul class="list-disc list-inside text-gray-600 mb-3">
                    <li>Delete all static DHCP reservations</li>
                    <li>Remove all reservation options</li>
                    <li>Cannot be undone</li>
                </ul>
                <p class="text-sm text-gray-500">Type <strong>DELETE ALL</strong> below to confirm:</p>
                <input type="text" id="confirmDelete" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Type DELETE ALL">
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete all!',
        preConfirm: () => {
            const confirmText = document.getElementById('confirmDelete').value;
            if (confirmText !== 'DELETE ALL') {
                Swal.showValidationMessage('Please type "DELETE ALL" to confirm');
            }
        }
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Deleting Reservations...',
                text: 'Please wait while all reservations are being deleted',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Get all subnets first
            const subnetsResponse = await fetch('/api/dhcp/subnets');
            const subnets = await subnetsResponse.json();
            
            let totalDeleted = 0;
            let errors = [];

            // Delete reservations from each subnet
            for (const subnet of subnets) {
                try {
                    // Get reservations for this subnet
                    const response = await fetch(`/api/dhcp/static/${subnet.id}`);
                    const result = await response.json();
                    
                    if (result.result === 0 && result.hosts && result.hosts.length > 0) {
                        // Delete each reservation
                        for (const host of result.hosts) {
                            try {
                                const deleteResponse = await fetch('/api/dhcp/reservations', {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        'ip-address': host['ip-addresses'][0],
                                        'subnet-id': parseInt(subnet.id)
                                    })
                                });

                                const deleteResult = await deleteResponse.json();
                                if (deleteResult.success) {
                                    totalDeleted++;
                                } else {
                                    errors.push(`Failed to delete ${host['ip-addresses'][0]}: ${deleteResult.message}`);
                                }
                            } catch (e) {
                                errors.push(`Error deleting reservation: ${e.message}`);
                            }
                        }
                    }
                } catch (e) {
                    errors.push(`Error processing subnet ${subnet.id}: ${e.message}`);
                }
            }

            if (errors.length === 0) {
                Swal.fire({
                    title: 'Success!',
                    html: `<p>Successfully deleted <strong>${totalDeleted}</strong> reservation(s)</p>`,
                    icon: 'success',
                    confirmButtonColor: '#10B981'
                });
            } else {
                Swal.fire({
                    title: 'Partially Completed',
                    html: `
                        <p class="mb-2">Deleted <strong>${totalDeleted}</strong> reservation(s)</p>
                        <p class="text-red-600">Encountered ${errors.length} error(s):</p>
                        <div class="text-xs text-left bg-gray-50 p-3 rounded mt-2 max-h-32 overflow-y-auto">
                            ${errors.map(e => `<p class="text-red-600">• ${e}</p>`).join('')}
                        </div>
                    `,
                    icon: 'warning',
                    confirmButtonColor: '#F59E0B'
                });
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to delete reservations: ' + error.message, 'error');
        }
    }
}

async function saveKeaConfig() {
    const result = await Swal.fire({
        title: 'Save Configuration?',
        html: `
            <div class="text-left">
                <p class="text-gray-700 mb-3">This will save the current Kea configuration to disk on all HA servers.</p>
                <p class="text-sm text-gray-600 mb-2">✓ Configuration will persist across server restarts</p>
                <p class="text-sm text-gray-600 mb-2">✓ Saved to /etc/kea/kea-dhcp6.conf</p>
                <p class="text-sm text-gray-600 mb-3">✓ All configured servers will be updated</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Save Config!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Saving Configuration...',
                text: 'Writing config to disk on all servers',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/admin/save-config', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Configuration Saved!',
                    html: `
                        <div class="text-left">
                            <p class="mb-3">${data.message}</p>
                            ${data.details ? `
                                <div class="text-sm bg-gray-50 p-3 rounded">
                                    ${data.details.map(detail => `<p class="mb-1">${detail}</p>`).join('')}
                                </div>
                            ` : ''}
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#10B981'
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to save configuration', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to save configuration: ' + error.message, 'error');
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
        const response = await fetch('/api/admin/backup/kea-leases');
        
        if (!response.ok) {
            const data = await response.json();
            Swal.fire({
                title: 'Backup Failed',
                text: data.message || 'Failed to backup leases',
                icon: 'error'
            });
            return;
        }
        
        // Check if it's a JSON response (success) or error
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const blob = await response.blob();
            
            // Check if the blob is empty or has no leases
            const text = await blob.text();
            const leases = JSON.parse(text);
            
            if (!leases || leases.length === 0) {
                Swal.fire({
                    title: 'No Leases Found',
                    text: 'There are currently no active DHCP leases to backup',
                    icon: 'info'
                });
                return;
            }
            
            // Download the file
            const url = window.URL.createObjectURL(new Blob([text]));
            const link = document.createElement('a');
            link.href = url;
            link.download = 'kea-leases-' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            Swal.fire({
                title: 'Backup Complete!',
                text: `${leases.length} lease(s) backed up successfully`,
                icon: 'success'
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'Failed to backup leases: ' + error.message,
            icon: 'error'
        });
    }
}

async function exportKeaLeases() {
    try {
        const response = await fetch('/api/admin/export/kea-leases-csv');
        
        if (!response.ok) {
            // Try to parse as JSON, fall back to text
            const contentType = response.headers.get('content-type');
            let message = 'No leases found to export';
            
            if (contentType && contentType.includes('application/json')) {
                try {
                    const data = await response.json();
                    message = data.message || message;
                } catch (e) {
                    // JSON parse failed, use default message
                }
            }
            
            Swal.fire({
                title: 'Export Failed',
                text: message,
                icon: 'info'
            });
            return;
        }
        
        // Get lease count from header
        const leaseCount = response.headers.get('X-Lease-Count') || '0';
        
        // Download the CSV file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'kea-leases-' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        Swal.fire({
            title: 'Export Complete!',
            text: `${leaseCount} lease(s) exported to CSV`,
            icon: 'success'
        });
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'Failed to export leases: ' + error.message,
            icon: 'error'
        });
    }
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
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Creating Backup...',
                text: 'Download will start shortly',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Trigger download
            window.location.href = `/api/admin/backup/radius-database/${type}`;
        }
    });
}

async function fullSystemBackup() {
    const result = await Swal.fire({
        title: 'Full System Backup',
        html: `
            <p class="text-sm text-gray-600 mb-2">This will backup:</p>
            <ul class="text-sm text-left text-gray-700 mb-4">
                <li>✓ Kea Database (complete)</li>
                <li>✓ Primary RADIUS Database</li>
                <li>✓ Secondary RADIUS Database</li>
            </ul>
            <p class="text-sm text-gray-500">Backups will be combined into a tar.gz archive</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        confirmButtonText: 'Create Full Backup'
    });

    if (result.isConfirmed) {
        // Show loading modal
        Swal.fire({
            title: 'Creating Backup...',
            html: 'Please wait while we backup all databases',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('/api/admin/backup/full-system');
            const data = await response.json();

            if (data.success) {
                // Build component status HTML
                let componentsHtml = '<div class="text-left mb-4">';
                componentsHtml += '<p class="text-sm font-medium text-gray-700 mb-2">Backup Components:</p>';
                componentsHtml += '<ul class="text-sm space-y-1">';
                
                data.components.forEach(comp => {
                    const icon = comp.status === 'success' ? '✓' : '✗';
                    const color = comp.status === 'success' ? 'text-green-600' : 'text-red-600';
                    const sizeInfo = comp.size ? ` (${comp.size})` : '';
                    componentsHtml += `<li class="${color}">${icon} ${comp.component}${sizeInfo}</li>`;
                });
                
                componentsHtml += '</ul></div>';

                // Build summary
                let summaryHtml = '<div class="bg-gray-50 rounded p-3 text-sm mb-4">';
                summaryHtml += `<p><strong>Archive:</strong> ${data.filename}</p>`;
                summaryHtml += `<p><strong>Total Size:</strong> ${data.size}</p>`;
                summaryHtml += `<p><strong>Status:</strong> ${data.summary.success}/${data.summary.total} successful</p>`;
                summaryHtml += '</div>';

                // Add download link
                let downloadHtml = `<a href="${data.path}" class="inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium" download>
                    <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Backup
                </a>`;

                Swal.fire({
                    icon: 'success',
                    title: 'Backup Complete!',
                    html: componentsHtml + summaryHtml + downloadHtml,
                    width: '600px',
                    confirmButtonText: 'Done'
                });

                // Refresh backup list
                loadRecentBackups();
            } else {
                // Show errors
                let errorHtml = '<div class="text-left mb-4">';
                
                if (data.components) {
                    errorHtml += '<p class="text-sm font-medium text-gray-700 mb-2">Backup Status:</p>';
                    errorHtml += '<ul class="text-sm space-y-1 mb-3">';
                    data.components.forEach(comp => {
                        const icon = comp.status === 'success' ? '✓' : '✗';
                        const color = comp.status === 'success' ? 'text-green-600' : 'text-red-600';
                        errorHtml += `<li class="${color}">${icon} ${comp.component}`;
                        if (comp.error) {
                            errorHtml += `<br><span class="text-xs text-gray-500 ml-4">${comp.error}</span>`;
                        }
                        errorHtml += '</li>';
                    });
                    errorHtml += '</ul>';
                }

                if (data.errors && data.errors.length > 0) {
                    errorHtml += '<p class="text-sm font-medium text-red-700 mb-2">Errors:</p>';
                    errorHtml += '<ul class="text-sm text-red-600 space-y-1">';
                    data.errors.forEach(err => {
                        errorHtml += `<li>• ${err}</li>`;
                    });
                    errorHtml += '</ul>';
                }
                
                errorHtml += '</div>';

                Swal.fire({
                    icon: 'error',
                    title: 'Backup Failed',
                    html: errorHtml,
                    width: '600px'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to create backup: ' + error.message
            });
        }
    }
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

async function loadKeaConfigBackups() {
    try {
        const response = await fetch('/api/admin/kea-config-backups/list');
        const data = await response.json();

        if (data.success && data.backups && data.backups.length > 0) {
            displayKeaConfigBackups(data.backups);
        } else {
            $('#keaConfigBackups').html('<div class="text-center text-gray-500 py-4">No Kea config backups found</div>');
        }
    } catch (error) {
        console.error('Error loading Kea config backups:', error);
        $('#keaConfigBackups').html('<div class="text-center text-red-500 py-4">Error loading Kea config backups</div>');
    }
}

function displayKeaConfigBackups(backups) {
    let html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">';
    html += '<thead class="bg-gray-50">';
    html += '<tr>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Server</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operation</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>';
    html += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>';
    html += '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>';
    html += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

    backups.forEach(backup => {
        html += '<tr>';
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${backup.id}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.server_name || 'Unknown'}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.operation}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.created_by}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${backup.created_at}</td>`;
        html += `<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">`;
        html += `<button onclick="viewKeaConfigBackup(${backup.id}, '${backup.operation}')" class="text-blue-600 hover:text-blue-900 mr-3">View</button>`;
        html += `<button onclick="restoreKeaConfigBackup(${backup.id}, '${backup.operation}')" class="text-green-600 hover:text-green-900">Restore</button>`;
        html += `</td></tr>`;
    });

    html += '</tbody></table></div>';
    $('#keaConfigBackups').html(html);
}

async function restoreKeaConfigBackup(backupId, operation) {
    const result = await Swal.fire({
        title: 'Restore Kea Configuration?',
        html: `
            <p class="text-red-600 font-bold mb-2">⚠️ WARNING ⚠️</p>
            <p class="mb-2">This will RESTORE the Kea configuration from backup!</p>
            <p class="text-sm text-gray-600">Backup ID: <strong>${backupId}</strong></p>
            <p class="text-sm text-gray-600">Operation: <strong>${operation}</strong></p>
            <p class="text-sm text-gray-600 mt-2">Current configuration will be backed up before restore.</p>
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
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch(`/api/admin/kea-config-backups/restore/${backupId}`, {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Restored!', 'Kea configuration has been restored successfully.', 'success');
                loadKeaConfigBackups();
            } else {
                Swal.fire('Error!', data.message || 'Failed to restore configuration', 'error');
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to restore configuration: ' + error.message, 'error');
        }
    }
}

async function viewKeaConfigBackup(backupId, operation) {
    try {
        Swal.fire({
            title: 'Loading...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/api/admin/kea-config-backups/view/${backupId}`);
        const data = await response.json();
        
        if (data.success) {
            const configJson = JSON.stringify(data.config, null, 2);
            Swal.fire({
                title: `Backup #${backupId} - ${operation}`,
                html: `
                    <div class="text-left">
                        <p class="text-sm text-gray-600 mb-2"><strong>Created:</strong> ${data.created_at}</p>
                        <p class="text-sm text-gray-600 mb-4"><strong>By:</strong> ${data.created_by}</p>
                        <pre class="bg-gray-100 p-4 rounded text-xs overflow-auto max-h-96 text-left">${configJson}</pre>
                    </div>
                `,
                width: '80%',
                confirmButtonText: 'Close'
            });
        } else {
            Swal.fire('Error!', data.message || 'Failed to load backup', 'error');
        }
    } catch (error) {
        Swal.fire('Error!', 'Failed to load backup: ' + error.message, 'error');
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
                <p class="text-sm text-gray-500 mt-4">Type <strong>CLEAR ALL</strong> below to confirm:</p>
                <input type="text" id="confirmClearCin" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Type CLEAR ALL">
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Clear All CIN Data!',
        cancelButtonText: 'Cancel',
        width: '600px',
        preConfirm: () => {
            const confirmText = document.getElementById('confirmClearCin').value;
            if (confirmText !== 'CLEAR ALL') {
                Swal.showValidationMessage('Please type "CLEAR ALL" to confirm');
            }
        }
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
                let keaInfo = '';
                if (data.kea_subnets_total > 0) {
                    keaInfo = `
                        <div class="mt-3 p-3 bg-purple-50 rounded">
                            <p class="text-sm font-semibold text-purple-900 mb-1">Kea Subnets:</p>
                            <p class="text-xs text-purple-800">✓ Deleted ${data.kea_subnets_deleted || 0} of ${data.kea_subnets_total} from Kea</p>
                            ${data.kea_errors && data.kea_errors.length > 0 ? `
                                <p class="text-xs text-yellow-800 mt-1">⚠️ ${data.kea_errors.length} errors occurred</p>
                            ` : ''}
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: 'Cleared!',
                    html: `
                        <div class="text-left">
                            <p class="mb-2">✓ Deleted ${data.switches || 0} CIN switches</p>
                            <p class="mb-2">✓ Deleted ${data.bvi_interfaces || 0} BVI interfaces</p>
                            <p class="mb-2">✓ Deleted ${data.links || 0} subnet links</p>
                            <p class="mb-2">✓ Deleted ${data.radius_clients || 0} RADIUS clients</p>
                            ${keaInfo}
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

// Check for orphaned RADIUS entries
async function checkRadiusOrphans() {
    try {
        Swal.fire({
            title: 'Checking RADIUS entries...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/api/admin/radius/check-orphans');
        const data = await response.json();

        if (data.success) {
            const report = data.report;
            let html = `
                <div class="text-left">
                    <p class="mb-3"><strong>Valid BVI IPs:</strong> ${report.valid_bvi_ips ? report.valid_bvi_ips.join(', ') : 'None'}</p>
                    <p class="mb-2"><strong>Total Orphans Found:</strong> ${data.total_orphans}</p>
            `;

            // Local orphans
            if (report.local_orphans.length > 0) {
                html += `
                    <div class="mt-3 p-3 bg-red-50 rounded">
                        <p class="font-semibold text-red-900 mb-2">Local Database (${report.local_orphans.length}):</p>
                        <ul class="text-xs text-red-800 list-disc list-inside">
                `;
                report.local_orphans.forEach(orphan => {
                    html += `<li>ID ${orphan.id}: ${orphan.nasname} (${orphan.shortname || 'No name'})</li>`;
                });
                html += `</ul></div>`;
            } else {
                html += `<p class="mt-2 text-green-600">✓ No orphans in local database</p>`;
            }

            // Remote orphans
            if (Object.keys(report.remote_orphans).length > 0) {
                Object.entries(report.remote_orphans).forEach(([serverName, serverData]) => {
                    if (serverData.error) {
                        html += `
                            <div class="mt-3 p-3 bg-yellow-50 rounded">
                                <p class="font-semibold text-yellow-900">${serverName}:</p>
                                <p class="text-xs text-yellow-800">Error: ${serverData.error}</p>
                            </div>
                        `;
                    } else if (serverData.count > 0) {
                        html += `
                            <div class="mt-3 p-3 bg-red-50 rounded">
                                <p class="font-semibold text-red-900 mb-2">${serverName} (${serverData.count}):</p>
                                <ul class="text-xs text-red-800 list-disc list-inside">
                        `;
                        serverData.entries.forEach(orphan => {
                            html += `<li>ID ${orphan.id}: ${orphan.nasname} (${orphan.shortname || 'No name'})</li>`;
                        });
                        html += `</ul></div>`;
                    }
                });
            }

            html += `</div>`;

            Swal.fire({
                title: 'RADIUS Orphan Check',
                html: html,
                icon: data.total_orphans > 0 ? 'warning' : 'success',
                width: '600px',
                confirmButtonText: 'OK'
            });
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to check RADIUS orphans: ' + error.message, 'error');
    }
}

// Clean orphaned RADIUS entries
async function cleanRadiusOrphans() {
    const result = await Swal.fire({
        title: 'Clean RADIUS Orphans?',
        html: `
            <div class="text-left">
                <p class="mb-3 text-yellow-600 font-semibold">⚠️ This will delete:</p>
                <ul class="list-disc list-inside mb-3 text-sm">
                    <li>RADIUS entries from local database</li>
                    <li>RADIUS entries from remote servers</li>
                    <li>Only entries referencing deleted BVI interfaces</li>
                </ul>
                <p class="text-sm text-gray-600 mb-2">✓ Valid RADIUS entries will NOT be touched</p>
                <p class="mt-3 font-semibold">This action cannot be undone!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D97706',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, Clean Orphans!',
        cancelButtonText: 'Cancel',
        width: '600px'
    });

    if (result.isConfirmed) {
        try {
            Swal.fire({
                title: 'Cleaning RADIUS Orphans...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('/api/admin/radius/clean-orphans', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                const report = data.report;
                let html = `
                    <div class="text-left">
                        <p class="mb-3 text-green-600 font-semibold">✓ Cleanup Complete!</p>
                        <p class="mb-2">Local Database: ${report.local_deleted} deleted</p>
                `;

                if (Object.keys(report.remote_deleted).length > 0) {
                    html += `<p class="mt-2 font-semibold">Remote Servers:</p><ul class="list-disc list-inside mb-2">`;
                    Object.entries(report.remote_deleted).forEach(([serverName, count]) => {
                        html += `<li>${serverName}: ${count} deleted</li>`;
                    });
                    html += `</ul>`;
                }

                if (report.errors && report.errors.length > 0) {
                    html += `<div class="mt-3 p-3 bg-yellow-50 rounded">
                        <p class="font-semibold text-yellow-900">Warnings:</p>
                        <ul class="text-xs text-yellow-800 list-disc list-inside">`;
                    report.errors.forEach(error => {
                        html += `<li>${error}</li>`;
                    });
                    html += `</ul></div>`;
                }

                html += `<p class="mt-3 text-lg font-bold text-green-600">Total Deleted: ${data.total_deleted}</p></div>`;

                Swal.fire({
                    title: 'Cleaned!',
                    html: html,
                    icon: 'success',
                    width: '600px'
                });
            } else {
                Swal.fire('Error', data.error, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to clean RADIUS orphans: ' + error.message, 'error');
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
                    <p class="text-sm font-semibold text-blue-900 mb-2">✨ Smart Mapping:</p>
                    <p class="text-xs text-blue-800">Subnet IDs will be automatically mapped by matching IP addresses to your current Kea configuration.</p>
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
        confirmButtonText: 'Import Leases',
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
        await autoMapAndImportLeases(file);
    }
}

async function autoMapAndImportLeases(file) {
    try {
        Swal.fire({
            title: 'Auto-mapping Subnets...',
            text: 'Analyzing CSV and matching with Kea configuration',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('leases_file', file);
        formData.append('auto_map', 'true');

        const response = await fetch('/api/admin/import-leases', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        Swal.close();

        if (data.success) {
            let mappingInfo = '';
            if (data.subnet_mapping && Object.keys(data.subnet_mapping).length > 0) {
                mappingInfo = '<div class="mt-3 p-3 bg-blue-50 rounded"><p class="text-sm font-semibold text-blue-900 mb-1">Subnet Mappings Applied:</p><ul class="text-xs text-blue-800 list-disc list-inside">';
                for (const [oldId, newId] of Object.entries(data.subnet_mapping)) {
                    mappingInfo += `<li>CSV Subnet ${oldId} → Kea Subnet ${newId}</li>`;
                }
                mappingInfo += '</ul></div>';
            }
            
            let unmappedInfo = '';
            if (data.unmapped && data.unmapped > 0) {
                unmappedInfo = '<div class="mt-3 p-3 bg-orange-50 rounded"><p class="text-sm font-semibold text-orange-900 mb-1">⚠️ Leases from Non-Existent Subnets (Auto-Skipped):</p>';
                if (data.unmapped_info && data.unmapped_info.length > 0) {
                    unmappedInfo += '<ul class="text-xs text-orange-800 list-disc list-inside">';
                    data.unmapped_info.forEach(info => {
                        unmappedInfo += `<li>${info}</li>`;
                    });
                    unmappedInfo += '</ul>';
                } else {
                    unmappedInfo += `<p class="text-xs text-orange-800">${data.unmapped} leases had no matching subnet in your Kea configuration</p>`;
                }
                unmappedInfo += '<p class="text-xs text-orange-700 mt-2">These IPs don\'t belong to any configured subnet and were automatically skipped.</p></div>';
            }
            
            await Swal.fire({
                title: 'Import Complete!',
                html: `
                    <div class="text-left">
                        <p class="mb-2">✓ Processed ${data.total || 0} leases from CSV</p>
                        <p class="mb-2">✓ Imported ${data.imported || 0} active leases</p>
                        <p class="mb-2">⊘ Skipped ${data.skipped || 0} (expired, invalid, or no matching subnet)</p>
                        ${mappingInfo}
                        ${unmappedInfo}
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

async function importLeasesJSONWizard() {
    const { value: file } = await Swal.fire({
        title: 'Import Kea Leases from JSON',
        html: `
            <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">
                    This will import leases from a JSON backup file (created with the Backup Leases button)
                    into the Kea lease database as active leases.
                </p>
                <div class="mb-4 p-4 bg-indigo-50 rounded-md">
                    <p class="text-sm font-semibold text-indigo-900 mb-2">📦 Direct Restore:</p>
                    <p class="text-xs text-indigo-800">Lease data will be imported with original subnet IDs and timestamps.</p>
                </div>
                <input type="file" id="json-file" accept=".json" 
                       class="block w-full text-sm text-gray-500
                       file:mr-4 file:py-2 file:px-4
                       file:rounded-md file:border-0
                       file:text-sm file:font-semibold
                       file:bg-indigo-50 file:text-indigo-700
                       hover:file:bg-indigo-100">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Import Leases',
        confirmButtonColor: '#4F46E5',
        preConfirm: () => {
            const fileInput = document.getElementById('json-file');
            if (!fileInput.files[0]) {
                Swal.showValidationMessage('Please select a JSON file');
                return false;
            }
            return fileInput.files[0];
        }
    });

    if (file) {
        await importLeasesFromJSON(file);
    }
}

async function importLeasesFromJSON(file) {
    try {
        Swal.fire({
            title: 'Importing Leases...',
            text: 'Processing JSON backup file',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('json_file', file);

        const response = await fetch('/api/admin/import-leases-json', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        Swal.close();

        if (data.success) {
            Swal.fire({
                title: 'Import Complete!',
                html: `
                    <div class="text-left">
                        <p class="mb-2">✓ Processed ${data.total || 0} leases from JSON</p>
                        <p class="mb-2">✓ Imported ${data.imported || 0} active leases</p>
                        <p class="mb-2">⊘ Skipped ${data.skipped || 0} (duplicate or invalid)</p>
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
                confirmButtonColor: '#4F46E5'
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to import leases', 'error');
        }
    } catch (error) {
        Swal.close();
        Swal.fire('Error', 'Failed to process file: ' + error.message, 'error');
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

// View Kea Configuration in modal
async function viewKeaConfig() {
    try {
        Swal.fire({
            title: 'Loading Configuration...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/api/admin/kea-config/view');
        const data = await response.json();

        if (data.success) {
            const configJson = JSON.stringify(data.config, null, 2);
            
            Swal.fire({
                title: 'Kea DHCPv6 Configuration',
                html: `
                    <div class="text-left">
                        <div class="mb-3 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                <span class="font-semibold">Subnets:</span> ${data.stats.subnets || 0} | 
                                <span class="font-semibold">Pools:</span> ${data.stats.pools || 0} | 
                                <span class="font-semibold">Options:</span> ${data.stats.options || 0}
                            </div>
                            <button onclick="copyKeaConfig()" class="px-3 py-1 bg-cyan-600 text-white text-xs rounded hover:bg-cyan-700">
                                Copy JSON
                            </button>
                        </div>
                        <div class="bg-gray-900 rounded-lg overflow-auto" style="max-height: 500px;">
                            <pre class="p-4 text-xs text-left text-gray-100"><code class="language-json text-gray-100" id="kea-config-code" style="color: #f3f4f6;">${escapeHtml(configJson)}</code></pre>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button onclick="downloadKeaConfigJSON()" class="flex-1 px-4 py-2 bg-cyan-600 text-white rounded hover:bg-cyan-700">
                                <svg class="inline h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download JSON
                            </button>
                            <button onclick="downloadKeaConfigINI()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                <svg class="inline h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download .conf
                            </button>
                        </div>
                    </div>
                `,
                width: '900px',
                showConfirmButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#06B6D4',
                didOpen: () => {
                    // Apply syntax highlighting if Prism.js is available
                    if (typeof Prism !== 'undefined') {
                        Prism.highlightAll();
                    }
                }
            });

            // Store config globally for copy/download functions
            window.currentKeaConfig = data.config;
        } else {
            Swal.fire('Error', data.error || 'Failed to load configuration', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to load Kea config: ' + error.message, 'error');
    }
}

// Copy Kea config to clipboard
function copyKeaConfig() {
    if (!window.currentKeaConfig) {
        Swal.fire('Error', 'No configuration loaded', 'error');
        return;
    }
    
    const configJson = JSON.stringify(window.currentKeaConfig, null, 2);
    navigator.clipboard.writeText(configJson).then(() => {
        Swal.fire({
            title: 'Copied!',
            text: 'Configuration copied to clipboard',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }).catch(err => {
        Swal.fire('Error', 'Failed to copy: ' + err.message, 'error');
    });
}

// Download Kea config as JSON
async function downloadKeaConfigJSON() {
    try {
        let config = window.currentKeaConfig;
        
        // If not loaded yet, fetch it
        if (!config) {
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            const response = await fetch('/api/admin/kea-config/view');
            const data = await response.json();
            Swal.close();
            
            if (!data.success) {
                Swal.fire('Error', data.error || 'Failed to load configuration', 'error');
                return;
            }
            config = data.config;
        }

        const configJson = JSON.stringify(config, null, 2);
        const blob = new Blob([configJson], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kea-dhcp6-config-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            title: 'Downloaded!',
            text: 'Configuration file downloaded',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    } catch (error) {
        Swal.fire('Error', 'Failed to download: ' + error.message, 'error');
    }
}

// Download Kea config as .conf format
async function downloadKeaConfigINI() {
    try {
        Swal.fire({
            title: 'Generating .conf file...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const response = await fetch('/api/admin/kea-config/download-conf');
        
        if (!response.ok) {
            throw new Error('Failed to generate .conf file');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kea-dhcp6-${new Date().toISOString().split('T')[0]}.conf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        Swal.fire({
            title: 'Downloaded!',
            text: 'Configuration file downloaded',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    } catch (error) {
        Swal.fire('Error', 'Failed to download .conf: ' + error.message, 'error');
    }
}

// HTML escape helper
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Delete all leases
async function deleteAllLeases() {
    const result = await Swal.fire({
        title: 'Delete All Leases?',
        html: `<p class="text-left">This will permanently delete <strong>ALL</strong> active DHCP leases from Kea.</p>
               <p class="text-left mt-2 text-red-600">⚠️ <strong>Warning:</strong> This action cannot be undone!</p>
               <p class="text-left mt-2">Devices will get new IPs on next lease renewal.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete all leases',
        cancelButtonText: 'Cancel',
        focusCancel: true
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Deleting all leases...',
            html: 'Please wait while we delete all leases from Kea',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const response = await fetch('/api/admin/leases/delete-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete leases');
        }

        let resultHtml = `<div class="text-left">`;
        resultHtml += `<p class="mb-2">✅ Successfully deleted <strong>${data.deleted}</strong> lease(s)</p>`;
        resultHtml += `<p class="text-sm text-gray-600">Processed ${data.subnets_processed} subnet(s)</p>`;
        
        if (data.errors && data.errors.length > 0) {
            resultHtml += `<div class="mt-3 p-3 bg-yellow-50 rounded border border-yellow-200">`;
            resultHtml += `<p class="text-sm font-semibold text-yellow-800 mb-1">⚠️ Some errors occurred:</p>`;
            resultHtml += `<ul class="text-xs text-yellow-700 list-disc list-inside">`;
            data.errors.slice(0, 5).forEach(err => {
                resultHtml += `<li>${escapeHtml(err)}</li>`;
            });
            if (data.errors.length > 5) {
                resultHtml += `<li>... and ${data.errors.length - 5} more</li>`;
            }
            resultHtml += `</ul></div>`;
        }
        resultHtml += `</div>`;

        Swal.fire({
            title: 'Leases Deleted',
            html: resultHtml,
            icon: data.errors && data.errors.length > 0 ? 'warning' : 'success',
            confirmButtonText: 'OK'
        });
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: error.message,
            icon: 'error'
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>