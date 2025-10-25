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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>



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
            .then(async (result) => {
                if (result.success) {
                    await Swal.fire({
                        title: 'Deleted!',
                        text: `Switch "${hostname}" and all associated data have been deleted.`,
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

    </script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>

