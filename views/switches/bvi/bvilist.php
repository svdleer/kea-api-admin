<?php
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
$success = null;
$switch = null;
$bviInterfaces = [];

try {
    $db = Database::getInstance();
    
    // Get the switch ID from URL
    $switchId = isset($_GET['switchId']) ? $_GET['switchId'] : null;
    
    
    if ($switchId === null || $switchId === '') {
        throw new Exception('Missing switch ID');
    }
    
    $cinSwitch = new CinSwitch($db);
    
    // Get switch data
    $switch = $cinSwitch->getSwitchById($switchId);
    if (!$switch) {
        throw new Exception('Switch not found');
    }

    // Get all BVI interfaces for this switch
    $bviInterfaces = $cinSwitch->getBviInterfaces($switchId);

} catch (\Exception $e) {
    error_log("Error in BVI list.php: " . $e->getMessage());
    $error = $e->getMessage();
}

$currentPage = 'BVI List';
$title = 'BVI Interfaces' . ($switch ? ' - ' . htmlspecialchars($switch['hostname']) : '');

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">BVI Interfaces<?php echo $switch ? ' for ' . htmlspecialchars($switch['hostname']) : ''; ?></h1>
                <p class="mt-1 text-sm text-gray-600">Manage BVI interface configurations for this switch</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="/switches" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Switches
                </a>
                <?php if ($switch): ?>
                <button onclick="addBvi()" 
                   class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add BVI Interface
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <!-- Alert Messages -->
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
        <div class="mb-4">
            <div class="max-w-md">
                <div class="relative">
                    <input type="text" 
                           id="searchInput"
                           placeholder="Search by interface number or IPv6 address..." 
                           onkeyup="performSearch(this.value)"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden w-fit mx-auto">
            <table class="w-auto divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Interface Number
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            IPv6 Address
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($bviInterfaces)): ?>
                        <tr>
                            <td colspan="3" class="px-8 py-5 text-sm text-center text-gray-500">
                                No BVI interfaces found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bviInterfaces as $bvi): ?>
                            <tr>
                                <td class="px-8 py-5 whitespace-nowrap text-sm text-gray-900">
                                    BVI<?php echo 100 + intval($bvi['interface_number']); ?>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($bvi['ipv6_address']); ?>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <button onclick="editBvi(<?php echo htmlspecialchars($bvi['id']); ?>)" 
                                                class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        <button onclick="deleteBvi(<?php echo htmlspecialchars($bvi['id']); ?>, 'BVI<?php echo 100 + intval($bvi['interface_number']); ?>')" 
                                                class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete
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

    <script>
        function addBvi() {
            const switchId = <?php echo json_encode($switchId); ?>;
            sessionStorage.setItem('bviReturnUrl', `/switches/bvi/list/${switchId}`);
            window.location.href = `/switches/${switchId}/bvi/add`;
        }

        function editBvi(bviId) {
            const switchId = <?php echo json_encode($switchId); ?>;
            // Store the return URL in session storage before navigating to edit page
            sessionStorage.setItem('returnUrl', `/switches/bvi/list/${switchId}`);
            window.location.href = `/switches/${switchId}/bvi/${bviId}/edit`;
        }

        function deleteBvi(bviId, interfaceNumber) {
            const switchId = <?php echo json_encode($switchId); ?>;
            
            Swal.fire({
                title: 'Delete BVI Interface?',
                html: `
                    <div class="text-left">
                        <p class="mb-3">You are about to delete <strong>${interfaceNumber}</strong></p>
                        <p class="mb-3 text-red-600 font-semibold">⚠️ This will also delete:</p>
                        <ul class="list-disc list-inside mb-3 text-sm">
                            <li>Associated DHCP subnet configuration</li>
                            <li>All DHCP leases and reservations</li>
                        </ul>
                        <p class="mb-3">Type <strong class="text-red-600">I AM SURE!</strong> to confirm:</p>
                        <input type="text" id="delete-confirmation" class="swal2-input" placeholder="Type: I AM SURE!">
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Delete BVI',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const confirmation = document.getElementById('delete-confirmation').value;
                    if (confirmation !== 'I AM SURE!') {
                        Swal.showValidationMessage('Please type "I AM SURE!" exactly to confirm');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/api/switches/${switchId}/bvi/${bviId}`,
                        type: 'DELETE',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: `BVI interface ${interfaceNumber} and all associated data have been deleted successfully.`,
                                    icon: 'success',
                                    confirmButtonColor: '#3B82F6'
                                }).then(() => {
                                    // Redirect back to BVI list page after successful delete
                                    window.location.href = `/switches/bvi/list/${switchId}`;
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Error deleting BVI interface',
                                    icon: 'error',
                                    confirmButtonColor: '#3B82F6'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            const errorMessage = xhr.responseJSON?.message || 'An error occurred while deleting the BVI interface';
                            Swal.fire({
                                title: 'Error!',
                                text: errorMessage,
                                icon: 'error',
                                confirmButtonColor: '#3B82F6'
                            });
                        }
                    });
                }
            });
        }



// Search functionality
function performSearch(searchTerm) {
    const rows = document.querySelectorAll("tbody tr");
    const searchTermLower = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTermLower) ? "" : "none";
    });
}
    </script>
</body>
</html>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>