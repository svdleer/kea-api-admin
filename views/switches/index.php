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
$title = 'Switches - VFZ DAA Infrastructure Management';

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
               class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
    <div class="bg-white shadow overflow-hidden w-fit mx-auto sm:rounded-lg">
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
                       class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Switch
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="w-auto divide-y divide-gray-200 table-fixed">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Hostname</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">BVI Count</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($switches as $switch): ?>
                                <tr>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($switch['hostname'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        try {
                                            $bviCount = $cinSwitch->getBviCount($switch['id']);
                                            echo htmlspecialchars($bviCount);
                                        } catch (\Exception $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right">
                                        <div class="flex space-x-2 justify-end">
                                            <a href="/switches/edit/<?php echo htmlspecialchars($switch['id']); ?>"     
                                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            <a href="/switches/bvi/list/<?php echo htmlspecialchars($switch['id']); ?>" 
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                                </svg>
                                                BVI Interfaces
                                            </a>
                                            <button onclick="deleteSwitch(<?php echo htmlspecialchars($switch['id']); ?>, '<?php echo htmlspecialchars($switch['hostname']); ?>')" 
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 cursor-pointer">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Delete
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
async function deleteSwitch(switchId, hostname) {
    const result = await Swal.fire({
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
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/switches/${switchId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Deleted!',
                    text: `Switch ${hostname} and all associated data have been deleted successfully.`,
                    icon: 'success',
                    confirmButtonColor: '#3B82F6'
                });
                // Reload the page to refresh the list
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.error || 'Error deleting switch',
                    icon: 'error',
                    confirmButtonColor: '#3B82F6'
                });
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Error deleting switch',
                icon: 'error',
                confirmButtonColor: '#3B82F6'
            });
        }
    }
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
