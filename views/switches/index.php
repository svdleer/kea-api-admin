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
$searchQuery = null; // Initialize search query variable
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
unset($_SESSION['message']);

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $switches = $cinSwitch->getAllSwitches();
} catch (\Exception $e) {
    $error = $e->getMessage();
}

ob_start();
?>

<!-- Main Content -->
<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">CIN Switches</h1>
                <p class="mt-1 text-sm text-gray-600">Manage network switches and their configurations</p>
            </div>
            <a href="/switches/add" 
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Switch
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="mb-4 rounded-md bg-green-50 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-4 rounded-md bg-red-50 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input type="text" 
                   id="searchInput"
                   placeholder="Search by hostname..." 
                   onkeyup="performSearch(this.value)"
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
    </div>

    <!-- Switches Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <?php if (empty($switches)): ?>
            <!-- Empty state - no table header -->
            <div class="px-6 py-12 text-center text-sm text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No switches found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo $searchQuery ? 'Try adjusting your search terms.' : 'Get started by adding a new switch.'; ?>
                </p>
                <?php if (!$searchQuery): ?>
                <div class="mt-6">
                    <a href="/switches/add" 
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Switch
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hostname</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BVI Count</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
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
                                            <a href="/switches/bvi/list/<?php echo htmlspecialchars($switch['id']); ?>" 
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
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>



    <script>
function deleteSwitch(switchId, hostname) {
    Swal.fire({
        title: 'Delete Switch?',
        html: `
            <div class="text-left">
                <p class="mb-3">You are about to delete switch: <strong>${hostname}</strong></p>
                <p class="mb-3 text-red-600 font-semibold">⚠️ This will also delete:</p>
                <ul class="list-disc list-inside mb-3 text-sm">
                    <li>All BVI interfaces</li>
                    <li>All DHCP subnet configurations</li>
                    <li>All DHCP leases and reservations</li>
                    <li>All associated data</li>
                </ul>
                <p class="mb-3">Type <strong class="text-red-600">I AM SURE!</strong> to confirm:</p>
                <input type="text" id="delete-switch-confirmation" class="swal2-input" placeholder="Type: I AM SURE!">
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Delete Switch',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const confirmation = document.getElementById('delete-switch-confirmation').value;
            if (confirmation !== 'I AM SURE!') {
                Swal.showValidationMessage('Please type "I AM SURE!" exactly to confirm');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/switches/${switchId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Switch deleted successfully',
                        icon: 'success',
                        confirmButtonColor: '#3B82F6'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: result.error || 'Error deleting switch',
                        icon: 'error',
                        confirmButtonColor: '#3B82F6'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Error deleting switch',
                    icon: 'error',
                    confirmButtonColor: '#3B82F6'
                });
            });
        }
    });
}

// Search functionality
function performSearch(searchTerm) {
    const rows = document.querySelectorAll('tbody tr');
    const searchTermLower = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTermLower) ? '' : 'none';
    });
}

    </script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
