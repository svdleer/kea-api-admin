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
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                BVI Interfaces<?php echo $switch ? ' for ' . htmlspecialchars($switch['hostname']) : ''; ?>
            </h1>
            <div class="space-x-4">
                <a href="/switches" class="text-blue-500 hover:text-blue-700">
                    ← Back to Switches
                </a>
                <?php if ($switch): ?>
                <button onclick="addBvi()" 
                   class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Add BVI Interface
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Interface Number
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            IPv6 Address
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bviInterfaces)): ?>
                        <tr>
                            <td colspan="3" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                No BVI interfaces found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bviInterfaces as $bvi): ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    BVI<?php echo 100 + intval($bvi['interface_number']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <?php echo htmlspecialchars($bvi['ipv6_address']); ?>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <div class="flex space-x-4">
                                        <button onclick="editBvi(<?php echo htmlspecialchars($bvi['id']); ?>)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            Edit
                                        </button>
                                        <button onclick="deleteBvi(<?php echo htmlspecialchars($bvi['id']); ?>, 'BVI<?php echo 100 + intval($bvi['interface_number']); ?>')" 
                                                class="text-red-600 hover:text-red-900">
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


    </script>
</body>
</html>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>