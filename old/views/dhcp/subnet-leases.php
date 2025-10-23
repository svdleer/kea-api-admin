<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

// Set active navigation item
$currentPage = 'DHCP';
$subPage = 'leases';

// Page title
$pageTitle = 'DHCPv6 Leases';

ob_start();
require BASE_PATH . '/views/dhcp-menu.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded my-6">
        <table id="leasesTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DUID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <!-- Table content will be dynamically inserted here -->
            </tbody>
        </table>
        <div id="pagination" class="px-6 py-3 flex items-center justify-between border-t border-gray-200">
            <!-- Pagination will be dynamically inserted here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 is not loaded');
        return;
    }

    let lastFromAddress = 'start';
    const itemsPerPage = 10;

    // Initial load
    loadLeases();

    function loadLeases() {
        const tableBody = document.querySelector('#leasesTable tbody');
        showLoading(tableBody);

        fetch(`/api/dhcp/leases?from=${lastFromAddress}&per_page=${itemsPerPage}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    renderLeases(data.data);
                    updatePagination(data.data.next_from !== null);
                } else {
                    showError(tableBody, data.message || 'Failed to load leases');
                }
            })
            .catch(error => {
                showError(tableBody, error.message);
                console.error('Error:', error);
            });
    }

    function showLoading(tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-4 text-center">
                    <div class="flex justify-center items-center">
                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading leases...
                    </div>
                </td>
            </tr>
        `;
    }

    function showError(tableBody, message) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-4 text-center">
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"> ${message}</span>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderLeases(data) {
        const tableBody = document.querySelector('#leasesTable tbody');
        
        if (!data.leases || data.leases.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No leases found
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = data.leases.map(lease => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${escapeHtml(lease.ip_address)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${escapeHtml(lease.duid)}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStateClass(lease.state)}">
                        ${escapeHtml(lease.state)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDateTime(lease.start_time)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDateTime(lease.end_time)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick="deleteLease('${escapeHtml(lease.ip_address)}')" 
                            class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                </td>
            </tr>
        `).join('');

        lastFromAddress = data.next_from || 'start';
    }

    function deleteLease(ipAddress) {
        Swal.fire({
            title: 'Delete Lease?',
            text: `Are you sure you want to delete the lease for ${ipAddress}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/api/dhcp/leases/${ipAddress}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Deleted!',
                            'The lease has been deleted.',
                            'success'
                        );
                        loadLeases();
                    } else {
                        throw new Error(data.message || 'Failed to delete lease');
                    }
                })
                .catch(error => {
                    Swal.fire(
                        'Error!',
                        error.message,
                        'error'
                    );
                });
            }
        });
    }

    function updatePagination(hasNextPage) {
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = `
            <div class="flex-1 flex justify-between items-center">
                <button onclick="loadPreviousPage()" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${lastFromAddress === 'start' ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${lastFromAddress === 'start' ? 'disabled' : ''}>
                    Previous
                </button>
                <button onclick="loadNextPage()" 
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${!hasNextPage ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasNextPage ? 'disabled' : ''}>
                    Next
                </button>
            </div>
        `;
    }

    function loadPreviousPage() {
        if (lastFromAddress !== 'start') {
            lastFromAddress = 'start';
            loadLeases();
        }
    }

    function loadNextPage() {
        if (lastFromAddress && lastFromAddress !== 'start') {
            loadLeases();
        }
    }

    function getStateClass(state) {
        switch (state.toLowerCase()) {
            case 'active':
                return 'bg-green-100 text-green-800';
            case 'expired':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

    function formatDateTime(timestamp) {
        return new Date(timestamp * 1000).toLocaleString();
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>
