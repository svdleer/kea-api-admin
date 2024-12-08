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
$title = 'Delete CIN Switch';

// Get switch ID from URL parameter
$switchId = isset($_GET['id']) ? $_GET['id'] : null;
if (!$switchId) {
    header('Location: /switches');
    throw new Exception('Invalid switch ID');
}

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Delete CIN Switch</h1>
        <a href="/switches" class="text-blue-500 hover:text-blue-700">← Back to CIN Switches</a>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <div id="switchDetails" class="mb-6">
            <h2 class="text-xl font-semibold mb-4">Switch Details</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600">Hostname</p>
                    <p id="hostname" class="font-medium"></p>
                </div>
                <div>
                    <p class="text-gray-600">BVI Interface</p>
                    <p id="interface_number" class="font-medium"></p>
                </div>
                <div>
                    <p class="text-gray-600">IPv6 Address</p>
                    <p id="ipv6_address" class="font-medium"></p>
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
                    Are you sure you want to delete this switch? This action cannot be undone and will also delete all associated BVI interfaces.
                </p>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="/switches" 
                   class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="button" 
                        id="deleteButton"
                        class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Delete CIN Switch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const deleteButton = $('#deleteButton');
    const switchId = $('#switchId').val();

    deleteButton.on('click', async function() {
        // First confirmation with SweetAlert2
        const confirmResult = await Swal.fire({
            title: 'Are you sure?',
            text: 'Are you absolutely sure you want to delete this switch and all its BVI interfaces?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3B82F6',
            cancelButtonColor: '#EF4444',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        deleteButton.prop('disabled', true);

        try {
            const response = await fetch(`/api/switches/${switchId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                await Swal.fire({
                    title: 'Deleted!',
                    text: 'The switch and its BVI interfaces have been deleted.',
                    icon: 'success',
                    confirmButtonColor: '#3B82F6'
                });
                window.location.href = '/switches';
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.error || 'Error deleting switch',
                    icon: 'error',
                    confirmButtonColor: '#3B82F6'
                });
                deleteButton.prop('disabled', false);
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Error deleting switch',
                icon: 'error',
                confirmButtonColor: '#3B82F6'
            });
            deleteButton.prop('disabled', false);
        }
    });
});

</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
