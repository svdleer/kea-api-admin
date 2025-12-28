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
    <!-- Welcome Section with Overall Health -->
    <div class="px-4 py-2 sm:py-3 sm:px-0">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 rounded-lg shadow-lg p-3 sm:p-4 mb-3 sm:mb-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold mb-0.5 sm:mb-1">
                        Welcome, <?php echo htmlspecialchars($username); ?>!
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-100">
                        RPD Infrastructure
                    </p>
                </div>
                <div id="overall-health" class="text-center">
                    <div class="text-2xl sm:text-3xl font-bold" id="health-score">--</div>
                    <div class="text-xs text-indigo-100" id="health-text">Loading...</div>
                </div>
            </div>
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
        <!-- Config Sync Status (Compact inline badge) -->
        <div id="config-sync-section" class="hidden px-4 sm:px-0 mb-2">
            <div id="config-sync-status" class="text-xs">
                <!-- Sync status will be inserted here as compact badge -->
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="px-4 sm:px-0 mb-3 sm:mb-4">
            <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                <div class="flex flex-wrap gap-1.5">
                    <a href="/dhcp/search" class="inline-flex items-center px-2 py-1 sm:px-3 sm:py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Leases
                    </a>
                    <a href="/switches" class="inline-flex items-center px-2 py-1 sm:px-3 sm:py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                        Switches
                    </a>
                    <a href="/dhcp" class="inline-flex items-center px-2 py-1 sm:px-3 sm:py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                        Subnets
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="/admin/tools" class="inline-flex items-center px-2 py-1 sm:px-3 sm:py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Tools
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kea DHCP Status Section -->
        <div class="px-4 sm:px-0 mb-3 sm:mb-4">
            <h3 class="text-base font-medium text-gray-900 mb-2">Kea DHCPv6 Servers</h3>
            <div id="kea-status" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <!-- Kea servers will be inserted here -->
            </div>
        </div>

        <!-- Infrastructure Statistics -->
        <div class="px-4 sm:px-0 mb-3 sm:mb-4">
            <h3 class="text-base font-medium text-gray-900 mb-2">Infrastructure Overview</h3>
            <div id="stats-cards" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Stats will be inserted here -->
            </div>
        </div>
        
        <!-- DHCP Statistics -->
        <div class="px-4 sm:px-0 mb-3 sm:mb-4">
            <h3 class="text-base font-medium text-gray-900 mb-2">DHCP Statistics</h3>
            <div id="dhcp-stats" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- DHCP stats will be inserted here -->
            </div>
        </div>
        
        <!-- RADIUS Statistics -->
        <div class="px-4 sm:px-0 mb-3 sm:mb-4">
            <h3 class="text-base font-medium text-gray-900 mb-2">RADIUS Statistics</h3>
            <div id="radius-stats" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
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
        // Load dashboard stats, Kea status, and config sync in parallel
        const [statsResponse, keaResponse, syncResponse] = await Promise.all([
            fetch('/api/dashboard/stats'),
            fetch('/api/dashboard/kea-status'),
            fetch('/api/dashboard/config-sync')
        ]);

        if (!statsResponse.ok || !keaResponse.ok || !syncResponse.ok) {
            throw new Error('Failed to load dashboard data');
        }

        const statsData = await statsResponse.json();
        const keaData = await keaResponse.json();
        const syncData = await syncResponse.json();

        if (statsData.success && keaData.success && syncData.success) {
            renderStats(statsData.data);
            renderKeaStatus(keaData.data);
            renderDhcpStats(statsData.data.dhcp);
            renderRadiusStats(statsData.data.radius);
            renderConfigSync(syncData.data);
            
            // Hide loading, show content
            document.getElementById('loading-spinner').classList.add('hidden');
            document.getElementById('dashboard-content').classList.remove('hidden');
        } else {
            throw new Error(statsData.message || keaData.message || syncData.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Dashboard error:', error);
        document.getElementById('loading-spinner').classList.add('hidden');
        // Don't show the error div, only the toast
        
        // Show a helpful toast notification
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: true,
            confirmButtonText: 'Configure Kea',
            timer: 8000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'warning',
            title: 'Dashboard Data Unavailable',
            html: 'Could not load dashboard data.<br>You may need to configure Kea servers.',
            confirmButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/admin/kea-servers';
            }
        });
    }
}

function renderKeaStatus(data) {
    const keaStatusEl = document.getElementById('kea-status');
    const servers = data.servers || [];
    
    // Calculate overall health
    updateOverallHealth(servers);
    
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

function updateOverallHealth(servers) {
    let healthScore = 100;
    let issues = [];
    
    // Check server status
    const onlineServers = servers.filter(s => s.online).length;
    const totalServers = servers.length;
    
    if (onlineServers < totalServers) {
        const offlineCount = totalServers - onlineServers;
        healthScore -= (offlineCount / totalServers) * 40;
        issues.push(`${offlineCount} server(s) offline`);
    }
    
    // Check lease utilization
    servers.forEach(server => {
        if (server.online && server.leases) {
            const utilization = (server.leases.assigned / server.leases.total) * 100;
            if (utilization > 90) {
                healthScore -= 15;
                issues.push('High lease utilization');
            } else if (utilization > 80) {
                healthScore -= 5;
            }
        }
    });
    
    // Determine health status
    let healthColor, healthText;
    if (healthScore >= 90) {
        healthColor = 'text-green-400';
        healthText = 'Excellent';
    } else if (healthScore >= 75) {
        healthColor = 'text-yellow-400';
        healthText = 'Good';
    } else if (healthScore >= 50) {
        healthColor = 'text-orange-400';
        healthText = 'Fair';
    } else {
        healthColor = 'text-red-400';
        healthText = 'Critical';
    }
    
    document.getElementById('health-score').textContent = Math.round(healthScore);
    document.getElementById('health-score').className = 'text-4xl font-bold mb-1 ' + healthColor;
    document.getElementById('health-text').textContent = healthText + (issues.length > 0 ? ' - ' + issues[0] : '');
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
    
    // Auth Success (24h)
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-green-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Auth Success (24h)</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-green-600">' + (data.auth_success_24h || 0) + '</dd>';
    html += '</div></div>';
    
    // Auth Failed (24h)
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-red-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Auth Failed (24h)</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-red-600">' + (data.auth_failed_24h || 0) + '</dd>';
    html += '</div></div>';
    
    // Auth Total (24h)
    html += '<div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-orange-500">';
    html += '<div class="px-4 py-5 sm:p-6">';
    html += '<dt class="text-sm font-medium text-gray-500 truncate">Total Auth (24h)</dt>';
    html += '<dd class="mt-1 text-3xl font-semibold text-orange-600">' + (data.auth_last_24h || 0) + '</dd>';
    html += '</div></div>';
    
    radiusStatsEl.innerHTML = html;
}

function renderConfigSync(data) {
    // Only show if more than 1 server
    if (data.server_count <= 1) {
        return;
    }
    
    const section = document.getElementById('config-sync-section');
    const container = document.getElementById('config-sync-status');
    
    // Compact badge-style display
    let html = '';
    
    if (data.in_sync) {
        html += '<div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">';
        html += '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
        html += 'Config Synced (' + data.server_count + ' servers)';
        html += '</div>';
    } else {
        html += '<div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 cursor-pointer" onclick="showSyncDetails()" title="Click for details">';
        html += '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
        html += 'Config Mismatch - Click for details';
        html += '</div>';
        
        // Store details for modal
        window.syncDetails = data;
    }
    
    container.innerHTML = html;
    section.classList.remove('hidden');
}

function showSyncDetails() {
    if (!window.syncDetails) return;
    
    let message = window.syncDetails.message || 'Configuration mismatch detected';
    if (window.syncDetails.checked_servers) {
        message += '\\n\\nChecked: ' + window.syncDetails.checked_servers.join(', ');
    }
    alert(message);
}

// Auto-refresh dashboard every 30 seconds
setInterval(() => {
    console.log('Auto-refreshing dashboard stats...');
    loadDashboardData();
}, 30000);

</script>

<?php
$content = ob_get_clean();
$auth = $GLOBALS['auth'];
require_once __DIR__ . '/layout.php';
?>
