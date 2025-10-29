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

$currentPage = 'dashboard';
$title = 'Dashboard - RPD Infrastructure Management';
$username = $_SESSION['user_name'] ?? 'User';
$isAdmin = $_SESSION['is_admin'] ?? false;

ob_start();
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Welcome Section -->
    <div class="px-4 py-6 sm:px-0">
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                Welcome to RPD Infrastructure Management
            </h2>
            <p class="text-gray-600">
                Monitor and manage your network infrastructure from this central dashboard.
            </p>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loading-spinner" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-2 text-gray-600">Loading dashboard...</p>
    </div>

    <!-- Error Message -->
    <div id="error-message" class="hidden mb-4 px-4 py-3 rounded relative bg-red-100 border border-red-400 text-red-700">
        <span id="error-text"></span>
    </div>

    <!-- Dashboard Content (Hidden until loaded) -->
    <div id="dashboard-content" class="hidden">
        <!-- Kea DHCP Status Section -->
        <div class="px-4 sm:px-0 mb-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Kea DHCPv6 Server Status</h3>
            <div id="kea-status" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <!-- Kea servers will be inserted here -->
            </div>
        </div>

        <!-- Infrastructure Statistics -->
        <div class="px-4 sm:px-0 mb-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Infrastructure Overview</h3>
            <div id="stats-cards" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Stats will be inserted here -->
            </div>
        </div>
        
        <!-- DHCP Statistics -->
        <div class="px-4 sm:px-0 mb-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">DHCP Statistics</h3>
            <div id="dhcp-stats" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- DHCP stats will be inserted here -->
            </div>
        </div>
        
        <!-- RADIUS Statistics -->
        <div class="px-4 sm:px-0 mb-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">RADIUS Statistics</h3>
            <div id="radius-stats" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- RADIUS stats will be inserted here -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
});

async function loadDashboard() {
    try {
        // Load dashboard stats and Kea status in parallel
        const [statsResponse, keaResponse] = await Promise.all([
            fetch('/api/dashboard/stats'),
            fetch('/api/dashboard/kea-status')
        ]);

        if (!statsResponse.ok || !keaResponse.ok) {
            throw new Error('Failed to load dashboard data');
        }

        const statsData = await statsResponse.json();
        const keaData = await keaResponse.json();

        if (statsData.success && keaData.success) {
            renderStats(statsData.data);
            renderKeaStatus(keaData.data);
            renderDhcpStats(statsData.data.dhcp);
            renderRadiusStats(statsData.data.radius);
            
            // Hide loading, show content
            document.getElementById('loading-spinner').classList.add('hidden');
            document.getElementById('dashboard-content').classList.remove('hidden');
        } else {
            throw new Error(statsData.message || keaData.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Dashboard error:', error);
        document.getElementById('loading-spinner').classList.add('hidden');
        document.getElementById('error-message').classList.remove('hidden');
        document.getElementById('error-text').textContent = 'Failed to load dashboard: ' + error.message;
    }
}

function renderKeaStatus(data) {
    const keaStatusEl = document.getElementById('kea-status');
    const servers = data.servers || [];
    
    let html = '';
    
    servers.forEach(server => {
        const statusClass = server.online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusText = server.online ? '● Online' : '● Offline';
        const borderClass = server.online ? 'border-green-500' : 'border-red-500';
        
        html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 ' + borderClass + '">';
        html += '<div class="px-4 py-5 sm:p-6">';
        html += '<div class="flex items-center justify-between mb-4">';
        html += '<h4 class="text-lg font-semibold text-gray-900 capitalize">';
        html += escapeHtml(server.name || 'Unknown') + ' Server</h4>';
        html += '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ' + statusClass + '">';
        html += statusText + '</span></div>';
        
        if (server.online) {
            html += '<dl class="grid grid-cols-1 gap-2 text-sm">';
            
            if (server.version) {
                html += '<div class="flex justify-between"><dt class="text-gray-500">Version:</dt>';
                html += '<dd class="text-gray-900 font-medium">' + escapeHtml(server.version) + '</dd></div>';
            }
            
            if (server.uptime) {
                html += '<div class="flex justify-between"><dt class="text-gray-500">Uptime:</dt>';
                html += '<dd class="text-gray-900 font-medium">' + escapeHtml(server.uptime) + '</dd></div>';
            }
            
            if (server.response_time) {
                html += '<div class="flex justify-between"><dt class="text-gray-500">Response Time:</dt>';
                html += '<dd class="text-gray-900 font-medium">' + server.response_time + ' ms</dd></div>';
            }
            
            if (server.subnets !== null && server.subnets !== undefined) {
                html += '<div class="flex justify-between"><dt class="text-gray-500">Configured Subnets:</dt>';
                html += '<dd class="text-gray-900 font-medium">' + server.subnets + '</dd></div>';
            }
            
            if (server.leases) {
                html += '<div class="flex justify-between"><dt class="text-gray-500">Active Leases:</dt>';
                html += '<dd class="text-gray-900 font-medium">';
                html += (server.leases.assigned || 0) + ' / ' + (server.leases.total || 0);
                html += '</dd></div>';
            }
            
            html += '</dl>';
        } else {
            html += '<div class="text-sm text-red-600">';
            html += escapeHtml(server.error || 'Unable to connect to server');
            html += '</div>';
        }
        
        html += '</div></div>';
    });
    
    keaStatusEl.innerHTML = html;
}

function renderStats(data) {
    const statsCardsEl = document.getElementById('stats-cards');
    
    // Render stats cards
    let statsHtml = '';
    
    statsHtml += '<div class="bg-white overflow-hidden shadow-sm rounded-lg">';
    statsHtml += '<div class="px-4 py-5 sm:p-6">';
    statsHtml += '<dt class="text-sm font-medium text-gray-500 truncate">Total Switches</dt>';
    statsHtml += '<dd class="mt-1 text-3xl font-semibold text-gray-900">' + (data.total_switches || 0) + '</dd>';
    statsHtml += '</div></div>';
    
    statsHtml += '<div class="bg-white overflow-hidden shadow-sm rounded-lg">';
    statsHtml += '<div class="px-4 py-5 sm:p-6">';
    statsHtml += '<dt class="text-sm font-medium text-gray-500 truncate">Total BVI Interfaces</dt>';
    statsHtml += '<dd class="mt-1 text-3xl font-semibold text-gray-900">' + (data.total_bvi || 0) + '</dd>';
    statsHtml += '</div></div>';
    
    statsHtml += '<div class="bg-white overflow-hidden shadow-sm rounded-lg">';
    statsHtml += '<div class="px-4 py-5 sm:p-6">';
    statsHtml += '<dt class="text-sm font-medium text-gray-500 truncate">Configured Subnets</dt>';
    statsHtml += '<dd class="mt-1 text-3xl font-semibold text-gray-900">' + (data.dhcp?.total_subnets || 0) + '</dd>';
    statsHtml += '</div></div>';
    
    statsHtml += '<div class="bg-white overflow-hidden shadow-sm rounded-lg">';
    statsHtml += '<div class="px-4 py-5 sm:p-6">';
    statsHtml += '<dt class="text-sm font-medium text-gray-500 truncate">Latest Switch Added</dt>';
    statsHtml += '<dd class="mt-1 text-xl font-semibold text-gray-900">';
    statsHtml += data.latest_switch ? escapeHtml(data.latest_switch.hostname) : 'No switches yet';
    statsHtml += '</dd></div></div>';
    
    statsCardsEl.innerHTML = statsHtml;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderDhcpStats(data) {
    if (!data) return;
    
    const dhcpStatsEl = document.getElementById('dhcp-stats');
    let html = '';
    
    // Configured Subnets
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-indigo-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Configured Subnets</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-indigo-600">' + (data.total_subnets || 0) + '</dd>';
    html += '</div></div>';
    
    // Active Leases
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-green-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Active Leases</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-green-600">';
    html += (data.assigned_leases || 0) + ' / ' + (data.total_leases || 0);
    html += '</dd></div></div>';
    
    // Lease Utilization
    const utilization = data.utilization_percent || 0;
    const utilizationColor = utilization > 80 ? 'red' : utilization > 60 ? 'amber' : 'green';
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-' + utilizationColor + '-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Lease Utilization</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-' + utilizationColor + '-600">' + utilization + '%</dd>';
    html += '</div></div>';
    
    // Static Reservations
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-purple-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Static Reservations</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-purple-600">' + (data.total_reservations || 0) + '</dd>';
    html += '</div></div>';
    
    dhcpStatsEl.innerHTML = html;
}

function renderRadiusStats(data) {
    if (!data) return;
    
    const radiusStatsEl = document.getElementById('radius-stats');
    let html = '';
    
    // NAS Devices
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-blue-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">NAS Devices</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-blue-600">' + (data.total_nas || 0) + '</dd>';
    html += '</div></div>';
    
    // RADIUS Users
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-cyan-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-cyan-600">' + (data.total_users || 0) + '</dd>';
    html += '</div></div>';
    
    // Active Sessions
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-teal-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Active Sessions</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-teal-600">' + (data.active_sessions || 0) + '</dd>';
    html += '</div></div>';
    
    // Auth Last 24h
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-orange-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Auth (24h)</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-orange-600">' + (data.auth_last_24h || 0) + '</dd>';
    html += '</div></div>';
    
    radiusStatsEl.innerHTML = html;
}
</script>

<?php
$content = ob_get_clean();
$auth = $GLOBALS['auth'];
require_once __DIR__ . '/layout.php';
?>
