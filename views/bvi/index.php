<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    exit;
}

$currentPage = 'bvi';
$title = 'BVI Interfaces Overview';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">BVI Interfaces Overview</h1>
            <p class="text-gray-600 mt-1">Manage all BVI interfaces across all switches</p>
        </div>
        <button onclick="showCreateModal()" 
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create BVI Interface
        </button>
    </div>

    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <input type="text" 
                   id="searchInput"
                   placeholder="Search by switch name, BVI interface, or IPv6 address..." 
                   onkeyup="performSearch(this.value)"
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- BVI Interfaces Table -->
    <div id="bviTable" class="hidden bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-auto divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-auto">
                        Switch Name
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                        BVI Interface
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-auto">
                        IPv6 Address
                    </th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-auto">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="bviTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Data will be loaded via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- No data message -->
    <div id="noDataMessage" class="hidden text-center py-12 bg-white shadow-md rounded-lg">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No BVI interfaces found</h3>
        <p class="mt-1 text-sm text-gray-500">Create one to get started.</p>
        <div class="mt-6">
            <button onclick="openCreateModal()" 
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create BVI Interface
            </button>
        </div>
    </div>
</div>

<!-- Create BVI Modal -->
<div id="createBviModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Create New BVI Interface</h3>
            <form id="createBviForm">
                <div class="mb-4">
                    <label for="switch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Switch (CIN)
                    </label>
                    <select id="switch_id" name="switch_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select a Switch --</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="ipv6_address" class="block text-sm font-medium text-gray-700 mb-2">
                        IPv6 Address
                    </label>
                    <input type="text" id="ipv6_address" name="ipv6_address" required
                           placeholder="2001:db8::1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p id="ipv6_error" class="text-red-600 text-sm mt-1 hidden"></p>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeCreateModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="createBtn"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load all data on page load
$(document).ready(function() {
    loadBviData();
    loadSwitches();
});

async function loadBviData() {
    try {
        console.log('Loading BVI data...');
        // Get all switches
        const switchesResponse = await fetch('/api/switches');
        const switchesData = await switchesResponse.json();
        
        console.log('Switches loaded:', switchesData.data ? switchesData.data.length : 0);
        
        if (!switchesData.success || !switchesData.data) {
            throw new Error('Failed to load switches');
        }
        
        const switches = switchesData.data;
        const allBviInterfaces = [];
        
        // Get BVI interfaces for each switch
        for (const switchItem of switches) {
            try {
                const bviResponse = await fetch(`/api/switches/${switchItem.id}/bvi`);
                const bviData = await bviResponse.json();
                
                if (bviData.success && bviData.data && bviData.data.length > 0) {
                    console.log(`Switch ${switchItem.hostname} has ${bviData.data.length} BVI interfaces`);
                    bviData.data.forEach(bvi => {
                        allBviInterfaces.push({
                            switch_id: switchItem.id,
                            switch_hostname: switchItem.hostname,
                            bvi_id: bvi.id,
                            interface_number: bvi.interface_number,
                            ipv6_address: bvi.ipv6_address
                        });
                    });
                }
            } catch (error) {
                console.error(`Error loading BVI for switch ${switchItem.id}:`, error);
            }
        }
        
        console.log('Total BVI interfaces:', allBviInterfaces.length);
        displayBviData(allBviInterfaces);
        
    } catch (error) {
        console.error('Error loading BVI data:', error);
        $('#loadingIndicator').hide();
        alert('Error loading BVI interfaces. Please try again.');
    }
}

function displayBviData(bviInterfaces) {
    $('#loadingIndicator').hide();
    
    if (bviInterfaces.length === 0) {
        $('#noDataMessage').show();
        return;
    }
    
    const tbody = $('#bviTableBody');
    tbody.empty();
    
    bviInterfaces.forEach(bvi => {
        const row = `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                    ${escapeHtml(bvi.switch_hostname)}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                    BVI${100 + parseInt(bvi.interface_number)}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-mono">
                    ${escapeHtml(bvi.ipv6_address)}
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                        <a href="/switches/${bvi.switch_id}/bvi/${bvi.bvi_id}/edit" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit
                        </a>
                        <button onclick="deleteBvi(${bvi.switch_id}, ${bvi.bvi_id}, 'BVI${100 + parseInt(bvi.interface_number)}', '${escapeHtml(bvi.switch_hostname)}')"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    $('#bviTable').show();
}

async function loadSwitches() {
    try {
        const response = await fetch('/api/switches');
        const data = await response.json();
        
        if (data.success && data.data) {
            const select = $('#switch_id');
            data.data.forEach(switchItem => {
                select.append(`<option value="${switchItem.id}">${escapeHtml(switchItem.hostname)}</option>`);
            });
        }
    } catch (error) {
        console.error('Error loading switches:', error);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function isValidIPv6(ipv6) {
    // IPv6 regex pattern
    const ipv6Pattern = /^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;
    return ipv6Pattern.test(ipv6);
}

function showCreateModal() {
    document.getElementById('createBviModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createBviModal').classList.add('hidden');
    document.getElementById('createBviForm').reset();
    document.getElementById('ipv6_error').classList.add('hidden');
}

// Real-time IPv6 validation
let ipv6CheckTimeout;
$('#ipv6_address').on('input', function() {
    const ipv6 = $(this).val().trim();
    const errorElement = $('#ipv6_error');
    
    if (ipv6 === '') {
        errorElement.addClass('hidden');
        return;
    }
    
    if (!isValidIPv6(ipv6)) {
        errorElement.text('Invalid IPv6 address format');
        errorElement.removeClass('hidden');
        return;
    } else {
        errorElement.addClass('hidden');
    }
    
    // Check if IPv6 already exists (debounced)
    clearTimeout(ipv6CheckTimeout);
    ipv6CheckTimeout = setTimeout(async () => {
        try {
            // Get all switches and check all BVI interfaces
            const switchesResponse = await fetch('/api/switches');
            const switchesData = await switchesResponse.json();
            
            if (switchesData.success && switchesData.data) {
                let addressExists = false;
                
                for (const switchItem of switchesData.data) {
                    const bviResponse = await fetch(`/api/switches/${switchItem.id}/bvi`);
                    const bviData = await bviResponse.json();
                    
                    if (bviData.success && bviData.data) {
                        const match = bviData.data.find(bvi => 
                            bvi.ipv6_address.toLowerCase() === ipv6.toLowerCase()
                        );
                        
                        if (match) {
                            addressExists = true;
                            errorElement.text(`Warning: This IPv6 address already exists on ${switchItem.hostname} BVI${100 + parseInt(match.interface_number)}`);
                            errorElement.removeClass('hidden');
                            break;
                        }
                    }
                }
                
                if (!addressExists) {
                    errorElement.addClass('hidden');
                }
            }
        } catch (error) {
            console.error('Error checking IPv6 address:', error);
        }
    }, 500);
});

document.getElementById('createBviForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const switchId = document.getElementById('switch_id').value;
    const ipv6Address = document.getElementById('ipv6_address').value.trim();
    const createBtn = document.getElementById('createBtn');
    const errorElement = document.getElementById('ipv6_error');
    
    if (!switchId) {
        alert('Please select a switch');
        return;
    }
    
    // Validate IPv6 address
    if (!isValidIPv6(ipv6Address)) {
        errorElement.textContent = 'Invalid IPv6 address format';
        errorElement.classList.remove('hidden');
        return;
    }
    
    // Check if IPv6 address already exists
    try {
        const switchesResponse = await fetch('/api/switches');
        const switchesData = await switchesResponse.json();
        
        if (switchesData.success && switchesData.data) {
            for (const switchItem of switchesData.data) {
                const bviResponse = await fetch(`/api/switches/${switchItem.id}/bvi`);
                const bviData = await bviResponse.json();
                
                if (bviData.success && bviData.data) {
                    const match = bviData.data.find(bvi => 
                        bvi.ipv6_address.toLowerCase() === ipv6Address.toLowerCase()
                    );
                    
                    if (match) {
                        errorElement.textContent = `This IPv6 address already exists on ${switchItem.hostname} BVI${100 + parseInt(match.interface_number)}`;
                        errorElement.classList.remove('hidden');
                        return;
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error checking IPv6 address:', error);
    }
    
    createBtn.disabled = true;
    createBtn.textContent = 'Creating...';
    
    try {
        const response = await fetch(`/api/switches/${switchId}/bvi`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ipv6_address: ipv6Address
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await Swal.fire({
                title: 'Success!',
                text: 'BVI interface created successfully',
                icon: 'success',
                confirmButtonColor: '#3085d6'
            });
            window.location.reload();
        } else {
            document.getElementById('ipv6_error').textContent = result.error || 'Failed to create BVI interface';
            document.getElementById('ipv6_error').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('ipv6_error').textContent = 'An error occurred';
        document.getElementById('ipv6_error').classList.remove('hidden');
    } finally {
        createBtn.disabled = false;
        createBtn.textContent = 'Create';
    }
});

// Close modal when clicking outside
document.getElementById('createBviModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateModal();
    }
});

// Search functionality
function performSearch(searchTerm) {
    const rows = document.querySelectorAll("#bviTableBody tr");
    const searchTermLower = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTermLower) ? "" : "none";
    });
}

// Delete BVI interface
async function deleteBvi(switchId, bviId, interfaceNumber, switchHostname) {
    const result = await Swal.fire({
        title: 'Delete BVI Interface?',
        html: `
            <div class="text-left">
                <p class="mb-3">You are about to delete <strong>${interfaceNumber}</strong> on <strong>${switchHostname}</strong></p>
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
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/switches/${switchId}/bvi/${bviId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Deleted!',
                    text: `BVI interface ${interfaceNumber} and all associated data have been deleted successfully.`,
                    icon: 'success',
                    confirmButtonColor: '#6366f1'
                });
                // Reload the page to refresh the list
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Error deleting BVI interface',
                    icon: 'error',
                    confirmButtonColor: '#6366f1'
                });
            }
        } catch (error) {
            console.error('Error deleting BVI:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while deleting the BVI interface',
                icon: 'error',
                confirmButtonColor: '#6366f1'
            });
        }
    }
}
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
