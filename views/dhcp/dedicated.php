<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Models\DHCP;

// Initialize required objects
$db = Database::getInstance();
$dhcpModel = new DHCP($db);

$pageTitle = 'Dedicated DHCP Subnets';
$currentPage = 'dhcp';
$subPage = 'dedicated';

require_once BASE_PATH . '/templates/header.php';
require_once BASE_PATH . '/views/dhcp-menu.php';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Dedicated DHCPv6 Subnets</h1>
        <button onclick="showCreateModal()" 
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Create Dedicated Subnet
        </button>
    </div>

    <p class="text-gray-600 mb-4">IPv6 subnets without BVI interface association</p>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subnet ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subnet</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pool Range</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relay Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CCAP Core</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="subnetsTable" class="bg-white divide-y divide-gray-200">
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Placeholder - will implement full functionality
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('subnetsTable');
    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No dedicated subnets configured yet. API endpoints coming soon.</td></tr>';
});

function showCreateModal() {
    Swal.fire('Coming Soon', 'Dedicated subnet creation will be implemented next', 'info');
}
</script>

<?php require_once BASE_PATH . '/templates/footer.php'; ?>
