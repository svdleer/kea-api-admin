<?php
error_reporting(E_ALL);
// No direct fix is applicable. Remove or comment out the line:
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
$title = 'Delete BVI Interface';

// Get switch ID and BVI ID from URL parameters
$switchId = isset($_GET['switchId']) ? $_GET['switchId'] : null;
$bviId = isset($_GET['bviId']) ? $_GET['bviId'] : null;

if (!$switchId || !$bviId) {
    header('Location: /switches');
    throw new Exception('Invalid switch or BVI ID');
}

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Delete BVI Interface</h1>
        <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi" class="text-blue-500 hover:text-blue-700">
            ← Back to BVI Interfaces
        </a>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <div id="switchInfo" class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h2 class="text-sm font-medium text-gray-900 mb-2">Switch Information</h2>
            <p class="text-gray-600">Hostname: <span id="switchHostname" class="font-medium text-gray-900"></span></p>
        </div>

        <div id="bviDetails" class="mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">BVI Interface Details</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600">Interface Number</p>
                    <p id="interfaceNumber" class="font-medium"></p>
                </div>
                <div>
                    <p class="text-gray-600">IPv6 Address</p>
                    <p id="ipv6Address" class="font-medium"></p>
                </div>
                <div>
                    <p class="text-gray-600">Created At</p>
                    <p id="createdAt" class="font-medium"></p>
                </div>
                <div>
                    <p class="text-gray-600">Updated At</p>
                    <p id="updatedAt" class="font-medium"></p>
                </div>
            </div>
        </div>

        <div class="border-t pt-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-red-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <h3 class="text-red-800 font-medium">Warning</h3>
                </div>
                <p class="text-red-700 mt-2">
                    Are you sure you want to delete this BVI interface? This action cannot be undone.
                </p>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi" 
                   class="px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="button" 
                        id="deleteButton"
                        class="px-6 py-3 text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Delete BVI Interface
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const switchId = <?php echo json_encode(htmlspecialchars($switchId)); ?>;
    const bviId = <?php echo json_encode(htmlspecialchars($bviId)); ?>;
    const deleteButton = $('#deleteButton');

    function formatDateTime(dateString) {
        return new Date(dateString).toLocaleString();
    }

    // Fetch switch and BVI interface details
    Promise.all([
        fetch(`/api/switches/${switchId}`).then(res => res.json()),
        fetch(`/api/switches/${switchId}/bvi/${bviId}`).then(res => res.json())
    ])
    .then(([switchData, bviData]) => {
        if (switchData.success && switchData.data) {
            $('#switchHostname').text(switchData.data.hostname);
        }
        
        if (bviData.success && bviData.data) {
            $('#interfaceNumber').text(bviData.data.interface_number);
            $('#ipv6Address').text(bviData.data.ipv6_address);
            $('#createdAt').text(formatDateTime(bviData.data.created_at));
            $('#updatedAt').text(formatDateTime(bviData.data.updated_at));
        } else {
            alert('Error loading BVI interface data');
            window.location.href = `/switches/${switchId}/bvi`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading data');
        window.location.href = `/switches/${switchId}/bvi`;
    });

    deleteButton.on('click', async function() {
        if (!confirm('Are you absolutely sure you want to delete this BVI interface?')) {
            return;
        }

        deleteButton.prop('disabled', true);

        try {
            const response = await fetch(`/api/switches/${switchId}/bvi/${bviId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            if (result.success) {
                window.location.href = `/switches/${switchId}/bvi`;
            } else {
                alert(result.error || 'Error deleting BVI interface');
                deleteButton.prop('disabled', false);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting BVI interface');
            deleteButton.prop('disabled', false);
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
