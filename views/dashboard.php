<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        <?php if (!empty($keaServers)): ?>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <?php foreach ($keaServers as $server): ?>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 <?php echo ($server['online'] ?? false) ? 'border-green-500' : 'border-red-500'; ?>">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 capitalize">
                                    <?php echo htmlspecialchars($server['name'] ?? 'Unknown'); ?> Server
                                </h4>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo ($server['online'] ?? false) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ($server['online'] ?? false) ? '● Online' : '● Offline'; ?>
                                </span>
                            </div>
                            
                            <?php if ($server['online'] ?? false): ?>
                                <dl class="grid grid-cols-1 gap-2 text-sm">
                                    <?php if (!empty($server['version'])): ?>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Version:</dt>
                                            <dd class="text-gray-900 font-medium"><?php echo htmlspecialchars($server['version']); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($server['uptime'])): ?>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Uptime:</dt>
                                            <dd class="text-gray-900 font-medium"><?php echo htmlspecialchars($server['uptime']); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($server['response_time'])): ?>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Response Time:</dt>
                                            <dd class="text-gray-900 font-medium"><?php echo $server['response_time']; ?> ms</dd>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($server['subnets']) && $server['subnets'] !== null): ?>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Configured Subnets:</dt>
                                            <dd class="text-gray-900 font-medium"><?php echo $server['subnets']; ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($server['leases'])): ?>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500">Active Leases:</dt>
                                            <dd class="text-gray-900 font-medium">
                                                <?php echo $server['leases']['assigned'] ?? 0; ?> / <?php echo $server['leases']['total'] ?? 0; ?>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            <?php else: ?>
                                <div class="text-sm text-red-600">
                                    <?php echo htmlspecialchars($server['error'] ?? 'Unable to connect to server'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    Unable to retrieve Kea server status. Please check your configuration.
                </p>
            </div>
        <?php endif; ?>
        
        <!-- HA Status -->
        <?php if (!empty($haStatus['configured'])): ?>
            <div class="mt-4 bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-lg font-semibold text-gray-900">High Availability Status</h4>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo ($haStatus['working'] ?? false) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ($haStatus['working'] ?? false) ? '● Active' : '● Not Active'; ?>
                        </span>
                    </div>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <?php if (!empty($haStatus['mode'])): ?>
                            <div>
                                <dt class="text-gray-500">HA Mode:</dt>
                                <dd class="text-gray-900 font-medium capitalize"><?php echo htmlspecialchars($haStatus['mode']); ?></dd>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($haStatus['primary_state'])): ?>
                            <div>
                                <dt class="text-gray-500">Primary State:</dt>
                                <dd class="text-gray-900 font-medium"><?php echo htmlspecialchars($haStatus['primary_state']); ?></dd>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($haStatus['secondary_state'])): ?>
                            <div>
                                <dt class="text-gray-500">Secondary State:</dt>
                                <dd class="text-gray-900 font-medium"><?php echo htmlspecialchars($haStatus['secondary_state']); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    
                    <?php if (!empty($haStatus['error'])): ?>
                        <div class="mt-2 text-sm text-yellow-600">
                            <?php echo htmlspecialchars($haStatus['error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 px-4 sm:px-0">
        <!-- Total Switches Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">
                    Total Switches
                </dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    <?php echo $totalSwitches; ?>
                </dd>
            </div>
        </div>

        <!-- Total BVI Interfaces Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">
                    Total BVI Interfaces
                </dt>
                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    <?php echo $totalBVI; ?>
                </dd>
            </div>
        </div>

        <!-- Latest Switch Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">
                    Latest Switch Added
                </dt>
                <dd class="mt-1 text-xl font-semibold text-gray-900">
                    <?php echo $latestSwitch ? htmlspecialchars($latestSwitch['hostname']) : 'No switches yet'; ?>
                </dd>
            </div>
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
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($switches)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No switches found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $recentSwitches = array_slice($switches, -5);
                            foreach ($recentSwitches as $switch): 
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($switch['hostname']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($switch['bvi_count']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($switch['created_at']))); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="/switches/edit/<?php echo $switch['id']; ?>" 
                                           class="text-indigo-600 hover:text-indigo-900">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$auth = $GLOBALS['auth'];
require_once __DIR__ . '/layout.php';
?>
