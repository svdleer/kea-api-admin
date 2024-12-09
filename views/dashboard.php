<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Models\CinSwitch;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$error = null;
$switches = [];
$totalSwitches = 0;
$totalBVI = 0;
$latestSwitch = null;

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $switches = $cinSwitch->getAllSwitches();
    $totalSwitches = count($switches);
    
    // Calculate total BVI count from all switches
    $totalBVI = 0;
    foreach ($switches as $switch) {
        $totalBVI += $cinSwitch->getBviCount($switch['id']);
    }
    
    // Get latest switch (last one in the array)
    $latestSwitch = !empty($switches) ? end($switches) : null;
} catch (\Exception $e) {
    $error = $e->getMessage();
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

    <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 rounded relative bg-red-100 border border-red-400 text-red-700">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

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
require_once __DIR__ . '/layout.php';
?>
