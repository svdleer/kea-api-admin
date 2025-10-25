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
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            + Create BVI Interface
        </button>
    </div>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>

    <!-- BVI Interfaces Table -->
    <div id="bviTable" class="hidden bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Switch Name
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        BVI Interface
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        IPv6 Address
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
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
    <div id="noDataMessage" class="hidden text-center py-8 bg-white shadow-md rounded-lg">
        <p class="text-gray-500 text-lg">No BVI interfaces found. Create one to get started.</p>
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
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
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
        // Get all switches
        const switchesResponse = await fetch('/api/switches');
        const switchesData = await switchesResponse.json();
        
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
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${escapeHtml(bvi.switch_hostname)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    BVI${100 + parseInt(bvi.interface_number)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${escapeHtml(bvi.ipv6_address)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="/switches/${bvi.switch_id}/bvi/${bvi.bvi_id}/edit" 
                       class="text-blue-600 hover:text-blue-900">
                        Edit
                    </a>
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
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
