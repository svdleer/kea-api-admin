<?php
$pageTitle = 'Dedicated DHCP Subnets';
$subPage = 'dedicated';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../dhcp-menu.php';
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

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subnet</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pool Range</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relay</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CCAP Core</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="subnetsTable" class="bg-white divide-y divide-gray-200">
                <!-- Populated by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="subnetModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Load subnets on page load
document.addEventListener('DOMContentLoaded', loadSubnets);

async function loadSubnets() {
    try {
        const response = await fetch('/api/dhcp/dedicated-subnets');
        const data = await response.json();
        
        const tbody = document.getElementById('subnetsTable');
        if (!data.subnets || data.subnets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No dedicated subnets found</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.subnets.map(subnet => `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${subnet.id}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${subnet.subnet}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${subnet.pool.start} - ${subnet.pool.end}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${subnet.relay || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${subnet.ccap_core || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button onclick='editSubnet(${JSON.stringify(subnet)})' class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                    <button onclick="deleteSubnet(${subnet.id})" class="text-red-600 hover:text-red-900">Delete</button>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        console.error('Error loading subnets:', error);
        Swal.fire('Error', 'Failed to load subnets', 'error');
    }
}

function showCreateModal() {
    // Will implement modal similar to regular subnets
    Swal.fire('Coming Soon', 'Create dedicated subnet functionality', 'info');
}

function editSubnet(subnet) {
    Swal.fire('Coming Soon', 'Edit functionality', 'info');
}

async function deleteSubnet(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the subnet from Kea!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/dhcp/dedicated-subnets/${id}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                Swal.fire('Deleted!', 'Subnet has been deleted.', 'success');
                loadSubnets();
            } else {
                const data = await response.json();
                Swal.fire('Error', data.error || 'Failed to delete subnet', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to delete subnet', 'error');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
