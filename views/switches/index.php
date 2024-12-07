<?php
error_reporting(E_ALL);
// No direct fix is applicable. Remove or comment out the line:
// ini_set('display_errors', 1);

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

$currentPage = 'switches';
$title = 'Switches - VFZ RPD Infrastructure Management';

$error = null;
$switches = [];
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
unset($_SESSION['message']);

// Get search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $switches = $cinSwitch->getAllSwitches();
    
    // Filter switches if search query exists
    if (!empty($searchQuery)) {
        $switches = array_filter($switches, function($switch) use ($searchQuery) {
            return stripos($switch['hostname'] ?? '', $searchQuery) !== false;
        });
    }
} catch (\Exception $e) {
    $error = $e->getMessage();
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="h-full flex flex-col bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-800">VFZ RPD Infrastructure Management</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="/dashboard" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="/switches" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Switches
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="/logout" class="bg-red-500 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="px-4 py-6 sm:px-0 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-800">CIN Switches</h2>
                <a href="/switches/add" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Add New Switch
                </a>
            </div>

            <?php if ($message): ?>
            <div class="mb-4 px-4 py-3 rounded relative bg-green-100 border border-green-400 text-green-700">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 rounded relative bg-red-100 border border-red-400 text-red-700">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Search Box -->
            <div class="mb-4 px-4 sm:px-0">
                <div class="max-w-md">
                    <form action="" method="GET" class="flex gap-2">
                        <input type="text" 
                               name="search" 
                               placeholder="Search by hostname..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>"
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Search
                        </button>
                        <?php if (!empty($searchQuery)): ?>
                        <a href="/switches" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Switches Table -->
            <div class="mt-4">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hostname</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BVI Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($switches)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?php echo $searchQuery ? 'No switches found matching your search.' : 'No switches found'; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($switches as $switch): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($switch['hostname'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        try {
                                            $bviCount = $cinSwitch->getBviCount($switch['id']);
                                            echo htmlspecialchars($bviCount);
                                        } catch (\Exception $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-4">
                                            <a href="/switches/edit/<?php echo htmlspecialchars($switch['id']); ?>"     
                                               class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1 rounded-md">
                                                Edit Switch
                                            </a>
                                            <a href="/switches/bvi/list?switchId=<?php echo htmlspecialchars($switch['id']); ?>" 
                                            class="text-green-600 hover:text-green-900 bg-green-50 px-3 py-1 rounded-md">
                                                Edit BVI Interfaces
                                            </a>
                                            <button onclick="deleteSwitch(<?php echo htmlspecialchars($switch['id']); ?>, '<?php echo htmlspecialchars($switch['hostname']); ?>')" 
                                            class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1 rounded-md cursor-pointer">
                                                Delete Switch
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> VFZ RPD Infrastructure Management
                </div>
                <div class="text-sm text-gray-500">
                    Version 0.1
                </div>
            </div>
        </div>
    </footer>

    <script>
    function deleteSwitch(switchId, hostname) {
        if (confirm(`Are you sure you want to delete switch "${hostname}"?`)) {
            $.ajax({
                url: `/api/switches/${switchId}`,
                type: 'DELETE',
                success: function(response) {
                    // Refresh the page to show updated list
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    const errorMessage = xhr.responseJSON?.message || 'An error occurred while deleting the switch';
                    alert(errorMessage);
                }
            });
            }
        }
    </script>
</body>
</html>
