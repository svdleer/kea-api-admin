<?php
error_reporting(E_ALL);
// amazonq-ignore-next-line
// Remove or comment out the line to prevent displaying errors in production
// ini_set('display_errors', 1);

require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$currentPage = 'switches';
$title = 'BVI Interfaces';

// Get switch ID from URL parameter
$switchId = isset($_GET['id']) ? $_GET['id'] : null;

if ($switchId === null || $switchId === '') {
    header('Location: /switches');
    throw new Exception('Invalid switch ID');
}

// Verify the switch exists
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM switches WHERE id = ?");
    $stmt->execute([$switchId]);
    if (!$stmt->fetch()) {
        throw new \Exception('Switch not found');
    }
} catch (\Exception $e) {
    // Log the exception
    error_log($e->getMessage());
    // Set an error message in the session
    $_SESSION['error'] = 'An error occurred while processing your request.';
    // Redirect to the switches page
    header('Location: /switches');
    return;
}

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">BVI Interfaces</h1>
            <p class="text-gray-600 mt-1">Switch: <span id="switchHostname" class="font-medium"></span></p>
        </div>
        <div class="flex space-x-4">
            <a href="/switches" class="text-blue-500 hover:text-blue-700">
                ← Back to Switches
            </a>
            <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi/add" 
               class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                Add BVI Interface
            </a>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- Error message -->
    <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
    </div>

    <!-- BVI Interfaces table -->
    <div id="bviTable" class="hidden">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Interface Number
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            IPv6 Address
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="bviTableBody">
                    <!-- Table rows will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- No BVI interfaces message -->
    <div id="noDataMessage" class="hidden text-center py-8">
        <p class="text-gray-500 text-lg">No BVI interfaces found for this switch.</p>
        <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi/add" 
           class="inline-block mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            Add BVI Interface
        </a>
    </div>
</div>

<script>
// Define switchId in global scope
const switchId = <?php echo json_encode(htmlspecialchars($switchId, ENT_QUOTES, 'UTF-8')); ?>;

$(document).ready(function() {
    const loadingIndicator = $('#loadingIndicator');
    const errorMessage = $('#errorMessage');
    const bviTable = $('#bviTable');
    const noDataMessage = $('#noDataMessage');
    const bviTableBody = $('#bviTableBody');

    function loadBviInterfaces() {
        loadingIndicator.show();
        bviTable.hide();
        noDataMessage.hide();
        errorMessage.hide();

        // First fetch switch details
        fetch(`/api/switches/${switchId}`)
            .then(response => response.json())
            .then(switchData => {
                if (switchData.success && switchData.data) {
                    $('#switchHostname').text(switchData.data.hostname);
                }
            })
            .catch(error => console.error('Error loading switch details:', error));

        // Then fetch BVI interfaces
        fetch(`/api/switches/${switchId}/bvi`)
            .then(response => response.json())
            .then(data => {
                loadingIndicator.hide();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load BVI interfaces');
                }

                if (!data.data || data.data.length === 0) {
                    noDataMessage.show();
                    return;
                }

                bviTableBody.empty();
                data.data.forEach(bvi => {
                    bviTableBody.append(`
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                BVI${bvi.interface_number}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${bvi.ipv6_address}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="/switches/${switchId}/bvi/${bvi.id}/edit" 
                                   class="text-blue-600 hover:text-blue-900 mr-4">
                                    Edit
                                </a>
                                <button onclick="deleteBvi(${bvi.id})" 
                                        class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `);
                });

                bviTable.show();
            })
            .catch(error => {
                loadingIndicator.hide();
                errorMessage.text(error.message || 'An error occurred while loading BVI interfaces')
                           .show();
                console.error('Error:', error);
            });
    }

    // Initial load
    loadBviInterfaces();
});

async function deleteBvi(bviId) {
    if (confirm('Are you sure you want to delete this BVI interface?')) {
        try {
            const response = await fetch(`/api/switches/${switchId}/bvi/${bviId}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert(result.message || 'An error occurred while deleting the BVI interface.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while deleting the BVI interface.');
        }
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
