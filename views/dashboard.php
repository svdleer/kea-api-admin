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

        <!-- Statistics Cards -->
        <div class="px-4 sm:px-0 mb-6">
            <div id="stats-cards" class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <!-- Stats will be inserted here -->
            </div>
        </div>

        <!-- Recent Switches Table -->
        <div class="mt-8 px-4 sm:px-0">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Switches</h3>
            <div class="bg-white shadow-sm overflow-hidden rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hostname
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    BVI Count
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created At
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="recent-switches" class="bg-white divide-y divide-gray-200">
                            <!-- Recent switches will be inserted here -->
                        </tbody>
                    </table>
                </div>
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
    const recentSwitchesEl = document.getElementById('recent-switches');
    
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
    statsHtml += '<dt class="text-sm font-medium text-gray-500 truncate">Latest Switch Added</dt>';
    statsHtml += '<dd class="mt-1 text-xl font-semibold text-gray-900">';
    statsHtml += data.latest_switch ? escapeHtml(data.latest_switch.hostname) : 'No switches yet';
    statsHtml += '</dd></div></div>';
    
    statsCardsEl.innerHTML = statsHtml;
    
    // Render recent switches
    if (data.recent_switches && data.recent_switches.length > 0) {
        let switchesHtml = '';
        data.recent_switches.forEach(function(sw) {
            switchesHtml += '<tr>';
            switchesHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">';
            switchesHtml += escapeHtml(sw.hostname) + '</td>';
            switchesHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">';
            switchesHtml += (sw.bvi_count || 0) + '</td>';
            switchesHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">';
            switchesHtml += formatDate(sw.created_at) + '</td>';
            switchesHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
            switchesHtml += '<a href="/switches/edit/' + sw.id + '" class="text-indigo-600 hover:text-indigo-900">View Details</a>';
            switchesHtml += '</td></tr>';
        });
        recentSwitchesEl.innerHTML = switchesHtml;
    } else {
        recentSwitchesEl.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No switches found</td></tr>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}
</script>

<?php
$content = ob_get_clean();
$auth = $GLOBALS['auth'];
require_once __DIR__ . '/layout.php';
?>
