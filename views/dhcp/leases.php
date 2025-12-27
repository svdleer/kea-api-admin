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
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">DHCP Leases & Reservations </h1>
        <p class="mt-2 text-sm text-gray-600">Manage active DHCP leases & Reservations for switch and interface</p>
    </div>

    <!-- Switches Table Container -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Switch
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Interfaces
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Subnet
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Pool Range
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody id="switchesTableBody" class="bg-white divide-y divide-gray-200">
                    <!-- Content will be dynamically inserted here -->
                </tbody>
            </table>
        </div>
    </div>



    <!-- Leases Table Container -->
    <div id="leasesTableContainer" class="bg-white shadow-md rounded-lg overflow-hidden hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        IP Address
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        DUID
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        State
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Start Time
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        End Time
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="leasesTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Leases content will be dynamically inserted here -->
            </tbody>
        </table>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="hidden mt-4">
        <div class="flex justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        </div>
    </div>

    <!-- Error State -->
    <div id="errorState" class="hidden mt-4">
        <div class="bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700" id="errorMessage">
                        <!-- Error message will be dynamically inserted here -->
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="addStaticLeaseForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-[32rem] shadow-lg rounded-md bg-white">
        <h2 class="text-lg font-medium text-gray-900">Add DHCPv6 Static Lease</h2>
        <form id="staticLeaseForm" class="space-y-6">
            <div>
                <label for="ipAddressPrefix" class="block text-lg font-semibold mb-2">IPv6 Address</label>
                <div class="flex">
                    <input type="text" id="ipAddressPrefix" name="ipAddressPrefix" class="form__input w-2/3 p-2 border rounded-l-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" readonly>
                    <input type="text" id="ipAddressSuffix" name="ipAddressSuffix" class="form__input w-1/3 p-2 border rounded-r-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="::1" required>
                </div>
                <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
            </div>
            <div>
                <label for="hwAddress" class="block text-lg font-semibold mb-2">MAC Address</label>
                <input type="text" id="hwAddress" name="hwAddress" class="form__input w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="00:11:22:33:44:55" required>
                <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
            </div>
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-lg font-semibold mb-2">DHCP Options</label>
                    <button type="button" 
                            class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="addOptionRow()">
                        Add Option +
                    </button>
                </div>
                <div id="dhcpOptionsContainer">
                <div class="flex gap-4 mb-4 items-end option-row" id="option-row-initial">
                        <div class="flex-1">
                            <label for="dhcpOptions" class="block text-lg font-semibold mb-2">DHCP Option</label>
                            <select id="dhcpOptions" name="dhcpOptions[]" class="form__input w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Select Option</option>
                            </select>
                            <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
                        </div>
                        <div class="flex-1">
                            <label for="optionValue" class="block text-lg font-semibold mb-2">Option Value</label>
                            <input type="text" 
                                   id="optionValue" 
                                   name="optionValues[]" 
                                   list="ccapCoreList"
                                   class="form__input w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                   placeholder="For arrays: value1, value2, value3">
                            <datalist id="ccapCoreList"></datalist>
                            <div class="text-xs text-gray-500 mt-1">For array options (syslog-servers, ccap-core, etc.), separate multiple values with commas</div>
                            <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="toggleStaticLeaseForm()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="button" onclick="addStaticLease()" class="px-4 py-2 text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Add Static Lease</button>
            </div>
        </form>
    </div>
</div>




<script>

function toggleLeases(button, switchId, bviId) {
    const leasesTableContainer = document.getElementById('leasesTableContainer');

    if (leasesTableContainer.classList.contains('hidden')) {
        // Show leases
        leasesTableContainer.classList.remove('hidden');
        button.textContent = 'Hide Leases';
        loadLeases(switchId, bviId);
    } else {
        // Hide leases
        leasesTableContainer.classList.add('hidden');
        button.textContent = 'View Leases';
    }
}



async function addStaticLease() {
    const ipAddressPrefix = document.getElementById('ipAddressPrefix').value;
    const ipAddressSuffix = document.getElementById('ipAddressSuffix').value;
    const hwAddress = document.getElementById('hwAddress').value;
    
    // Collect all options
    const optionRows = document.querySelectorAll('.option-row');
    const options = [];
    
    optionRows.forEach(row => {
        const select = row.querySelector('select');
        const input = row.querySelector('input');
        if (select.value && input.value) {
            const optionData = JSON.parse(select.value);
            options.push({
                code: optionData.code,
                space: optionData.space,
                name: optionData.name,
                type: optionData.type,
                value: input.value.trim()
            });
        }
    });

    // Separate validation checks for clearer error messages
    if (!ipAddressSuffix) {
        Swal.fire('Error', 'Please fill in the IP Address', 'error');
        return;
    }

    if (!hwAddress) {
        Swal.fire('Error', 'Please fill in the MAC Address', 'error');
        return;
    }

    // Validate MAC address format
    const macPattern = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;
    if (!macPattern.test(hwAddress)) {
        Swal.fire('Error', 'Invalid MAC Address format. Use format: 00:11:22:33:44:55', 'error');
        return;
    }

    // Only validate option values if they exist
    for (const row of document.querySelectorAll('.option-row')) {
        const select = row.querySelector('select');
        const input = row.querySelector('input');
        // Only validate if both select and input have values
        if (select.value && input.value && !validateOptionValue(input)) {
            Swal.fire('Error', 'Please correct the invalid option values', 'error');
            return;
        }
    }

    const normalizedPrefix = ipAddressPrefix.endsWith('::') 
        ? ipAddressPrefix 
        : ipAddressPrefix.endsWith(':') 
            ? ipAddressPrefix + ':' 
            : ipAddressPrefix + '::';
            
    const fullIPv6 = normalizedPrefix + ipAddressSuffix;
    
    // Use the stored subnet ID from when the form was opened
    if (!currentSubnetId) {
        Swal.fire('Error', 'Please select a subnet', 'error');
        return;
    }

    try {
        const response = await fetch('/api/dhcp/static', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ipAddress: fullIPv6,
                hwAddress: hwAddress,
                subnetId: parseInt(currentSubnetId),
                options: options
            })
        });

        if (response.ok) {
            Swal.fire({
                title: 'Success',
                text: 'Static lease added successfully',
                icon: 'success',
                confirmButtonColor: '#10B981'
            }).then(() => {
                toggleStaticLeaseForm();
                fetchLeases();
            });
        } else {
            const error = await response.json();
            Swal.fire('Error', error.message || 'Failed to add static lease', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Failed to add static lease', 'error');
    }
}


async function populateSubnetOptions() {
    try {
        const response = await fetch('/api/dhcp/subnets', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Subnets data:', data); // Debug log

        if (data && Array.isArray(data)) {
            const subnet = data[0]; // Assuming only one subnet is returned
            subnetPrefix = subnet.subnet.split('::')[0];
            poolRange = {
                start: subnet.pool.start,
                end: subnet.pool.end
            };
            document.getElementById('ipAddressPrefix').value = `${subnetPrefix}::`;
        } else {
            throw new Error('Failed to load subnets');
        }
    } catch (error) {
        console.error('Error loading subnets:', error);
    }
}

async function populateDhcpOptions() {
    const dhcpOptionsSelect = document.getElementById('dhcpOptions');
    dhcpOptionsSelect.innerHTML = '<option value="">Select Option</option>';

    try {
        const response = await fetch('/api/dhcp/options', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('DHCP options data:', data); // Debug log

        if (data.success && Array.isArray(data.data)) {
            // Filter out completely invalid items, but keep items with definitions even if option is null
            const validOptions = data.data.filter(item => item && item.definition && item.definition.code);
            validOptions.sort((a, b) => a.definition.code - b.definition.code);
            
            validOptions.forEach(item => {
                const opt = document.createElement('option');
                // Use definition data if option is null
                const code = item.option?.code || item.definition.code;
                const name = item.option?.name || item.definition.name || `Option ${code}`;
                const space = item.option?.space || item.definition.space || 'dhcp6';
                const type = item.definition.type || 'string';
                
                opt.value = JSON.stringify({
                    code: code,
                    type: type,
                    name: name,
                    space: space
                });
                opt.textContent = `${code} - ${name}`;
                dhcpOptionsSelect.appendChild(opt);
            });

            // Add this line to setup validation after populating options
            const initialRow = document.getElementById('option-row-initial');
            if (initialRow) {
                console.log('Setting up validation for initial row');
                setupOptionRowValidation(initialRow);
            }
            
            // Load CCAP cores for option 61
            await loadCcapCores();
        } else {
            throw new Error('Failed to load DHCP options');
        }
    } catch (error) {
        console.error('Error loading DHCP options:', error);
    }
}

// Load known CCAP cores from cin_bvi_dhcp_core table
async function loadCcapCores() {
    try {
        const response = await fetch('/api/dhcp/subnets', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const subnets = await response.json();
        const ccapCoreList = document.getElementById('ccapCoreList');
        
        // Get unique CCAP cores
        const uniqueCcapCores = [...new Set(
            subnets
                .map(subnet => subnet.ccap_core)
                .filter(core => core && core.trim() !== '')
        )];
        
        // Clear and populate datalist
        ccapCoreList.innerHTML = '';
        uniqueCcapCores.forEach(core => {
            const option = document.createElement('option');
            option.value = core;
            ccapCoreList.appendChild(option);
        });
        
        console.log('Loaded CCAP cores:', uniqueCcapCores);
    } catch (error) {
        console.error('Error loading CCAP cores:', error);
    }
}



function isValidIPv6(ip) {
    // Regular expression for IPv6 validation
    const ipv6Regex = /^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;
    console.log('Validating IPv6:', ip);
    return ipv6Regex.test(ip);
}

function compareIPv6(a, b) {
    const aParts = a.split(':').map(part => parseInt(part, 16));
    const bParts = b.split(':').map(part => parseInt(part, 16));
    
    for (let i = 0; i < aParts.length; i++) {
        if (aParts[i] < bParts[i]) return -1;
        if (aParts[i] > bParts[i]) return 1;
    }
    return 0;
}

function isValidIPv6InRange(ip, start, end) {
    // First check if it's a valid IPv6 address
    if (!isValidIPv6(ip)) {
        console.log('Invalid IPv6 format');
        return false;
    }
    
    console.log('Valid IPv6 format, checking range');
    return compareIPv6(start, ip) <= 0 && compareIPv6(ip, end) <= 0;
}


function isValidDUID(duid) {
    const duidRegex = /^([0-9a-fA-F]{2}:){10}[0-9a-fA-F]{2}$/;
    console.log('Validating DUID:', duid);
    const isValid = duidRegex.test(duid);
    console.log('DUID validation result:', isValid);
    return isValid;
}

function addOptionRow() {
    console.log('Adding new option row');
    const optionsContainer = document.getElementById('dhcpOptionsContainer');
    
    // Show the options container if it's hidden
    if (optionsContainer.classList.contains('hidden')) {
        optionsContainer.classList.remove('hidden');
    }
    
    const newRow = document.createElement('div');
    const rowId = `option-row-${Date.now()}`;
    
    newRow.className = 'flex gap-4 mb-4 items-end option-row';
    newRow.id = rowId;
    console.log('Created row with ID:', rowId);

    newRow.innerHTML = `
        <div class="flex-1">
            <label class="block text-lg font-semibold mb-2">DHCP Option</label>
            <select name="dhcpOptions[]" class="form__input w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Select Option</option>
            </select>
            <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
        </div>
        <div class="flex-1">
            <label class="block text-lg font-semibold mb-2">Option Value</label>
            <input type="text" name="optionValues[]" class="form__input w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Option Value">
            <div class="form__input-error-message text-sm text-red-500 mt-1"></div>
        </div>
        <button type="button" class="text-red-600 hover:text-red-800 p-2 mt-8" onclick="removeOptionRow('${rowId}')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    optionsContainer.appendChild(newRow);
    console.log('Row added to container');

    const newSelect = newRow.querySelector('select');
    populateDhcpOptionsForRow(newRow);
    setupOptionRowValidation(newRow);
}


async function populateDhcpOptionsForRow(row) {
    const select = row.querySelector('select');
    select.innerHTML = '<option value="">Select Option</option>';
    
    try {
        const response = await fetch('/api/dhcp/options', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            // Filter out completely invalid items, but keep items with definitions even if option is null
            const validOptions = data.data.filter(item => item && item.definition && item.definition.code);
            validOptions.sort((a, b) => a.definition.code - b.definition.code);
            
            validOptions.forEach(item => {
                const opt = document.createElement('option');
                // Use definition data if option is null
                const code = item.option?.code || item.definition.code;
                const name = item.option?.name || item.definition.name || `Option ${code}`;
                const space = item.option?.space || item.definition.space || 'dhcp6';
                const type = item.definition.type || 'string';
                
                opt.value = JSON.stringify({
                    code: code,
                    type: type,
                    name: name,
                    space: space
                });
                opt.textContent = `${code} - ${name}`;
                select.appendChild(opt);
            });
        }
    } catch (error) {
        console.error('Error loading DHCP options:', error);
    }
}



function removeOptionRow(rowId) {
    const row = document.getElementById(rowId);
    if (row && document.querySelectorAll('.option-row').length > 1) {
        Swal.fire({
            title: 'Remove Option?',
            text: "Are you sure you want to remove this DHCP option?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                row.remove();
            }
        });
    } else {
        Swal.fire({
            title: 'Cannot Remove',
            text: 'At least one DHCP option is required',
            icon: 'error'
        });
    }
}

function showError(input, errorElement, message) {
    console.log('Showing error:', {
        input: input,
        errorElement: errorElement,
        message: message
    });
    
    input.classList.add('border-red-500');
    input.classList.remove('border-green-500');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    errorElement.classList.remove('text-green-500');
    errorElement.classList.add('text-red-500');
}

function showSuccess(input, errorElement, message) {
    console.log('Showing success:', {
        input: input,
        errorElement: errorElement,
        message: message
    });
    
    input.classList.remove('border-red-500');
    input.classList.add('border-green-500');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    errorElement.classList.remove('text-red-500');
    errorElement.classList.add('text-green-500');
}

function setupOptionRowValidation(row) {
    console.log('Setting up validation for row:', row.id);
    const select = row.querySelector('select');
    const input = row.querySelector('input');
    
    console.log('Adding event listeners to:', {
        select: select,
        input: input
    });

    select.addEventListener('change', function(e) {
        console.log('Select change event fired:', {
            element: this,
            value: this.value,
            event: e
        });
        
        validateOptionSelect(this);
        if (this.value) {
            try {
                const optionData = JSON.parse(this.value);
                input.value = '';
                input.placeholder = `Enter ${optionData.type}`;
                console.log('Updated input placeholder:', {
                    type: optionData.type,
                    placeholder: input.placeholder
                });
            } catch (error) {
                console.error('Error parsing option data:', error);
            }
        }
    });

    input.addEventListener('input', function(e) {
        console.log('Input event fired:', {
            element: this,
            value: this.value,
            event: e
        });
        validateOptionValue(this);
    });

    // Add blur events for validation when focus leaves the fields
    input.addEventListener('blur', function(e) {
        console.log('Input blur event fired:', {
            element: this,
            value: this.value,
            event: e
        });
        validateOptionValue(this);
    });

    select.addEventListener('blur', function(e) {
        console.log('Select blur event fired:', {
            element: this,
            value: this.value,
            event: e
        });
        validateOptionSelect(this);
    });

    console.log('Validation setup complete for row:', row.id);
}


function validateOptionValue(input) {
    console.log('validateOptionValue called with:', {
        input: input,
        value: input.value
    });

    const errorElement = input.parentElement.querySelector('.form__input-error-message');
    const select = input.closest('.option-row').querySelector('select');
    
    console.log('Found related elements:', {
        errorElement: errorElement,
        select: select,
        selectValue: select.value
    });
    
    // If no option is selected and no value entered, that's fine (both optional)
    if (!select.value && !input.value.trim()) {
        showSuccess(input, errorElement, '');
        return true;
    }

    // If option selected but no value, that's ok too (optional)
    if (select.value && !input.value.trim()) {
        showSuccess(input, errorElement, '');
        return true;
    }

    try {
        const optionData = JSON.parse(select.value);
        const value = input.value.trim();
        
        console.log('Validating option:', {
            optionData: optionData,
            value: value,
            type: optionData.type
        });

        if (!value) {
            console.log('Empty value detected');
            showError(input, errorElement, 'Option value is required');
            return false;
        }

        switch (optionData.type) {
            case 'ipv6-address':
                console.log('Validating as IPv6 address');
                if (!isValidIPv6(value)) {
                    console.log('Invalid IPv6 address:', value);
                    showError(input, errorElement, 'Invalid IPv6 address format');
                    return false;
                }
                console.log('Valid IPv6 address');
                break;

                case 'uint32':
                    // First check if the input contains any non-numeric characters
                    if (!/^\d+$/.test(value)) {
                        showError(input, errorElement, 'Value must contain only numbers');
                        return false;
                    }
                    const num = parseInt(value);
                    if (isNaN(num) || num < 0 || num > 4294967295) {
                        showError(input, errorElement, 'Value must be a number between 0 and 4294967295');
                        return false;
                    }
                    break;

            default:
                console.log('Unknown type:', optionData.type);
        }

        console.log('Validation successful');
        showSuccess(input, errorElement, 'Valid value');
        return true;

    } catch (error) {
        console.error('Error during validation:', error);
        showError(input, errorElement, 'Validation error occurred');
        return false;
    }
}


function validateOptionSelect(select) {
    console.log('validateOptionSelect called with:', {
        select: select,
        value: select.value
    });

    const errorElement = select.parentElement.querySelector('.form__input-error-message');
    console.log('Found error element:', errorElement);
    
    if (!select.value) {
        select.classList.remove('border-red-500');
        errorElement.textContent = '';
        errorElement.style.display = 'none';
        return true;
    }

    try {
        const optionData = JSON.parse(select.value);
        console.log('Selected option data:', optionData);
        select.classList.remove('border-red-500');
        select.classList.add('border-green-500');
        errorElement.textContent = '';
        errorElement.style.display = 'none';
        return true;
    } catch (error) {
        console.error('Error parsing option data:', error);
        select.classList.add('border-red-500');
        errorElement.textContent = 'Invalid option data';
        errorElement.style.display = 'block';
        return false;
    }
}



let currentSubnetId = null;
let currentSubnetPrefix = null;

function toggleStaticLeaseForm(subnetId, subnetPrefix) {
    const form = document.getElementById('addStaticLeaseForm');
    
    if (form.classList.contains('hidden')) {
        // Store subnet context
        currentSubnetId = subnetId;
        currentSubnetPrefix = subnetPrefix;
        
        form.classList.remove('hidden');
        
        // Set the subnet prefix in the form
        const prefixInput = document.getElementById('ipAddressPrefix');
        if (prefixInput && subnetPrefix) {
            // Extract prefix without the /64
            const prefix = subnetPrefix.split('/')[0];
            prefixInput.value = prefix;
        }
        
        populateSubnetOptions();
        populateDhcpOptions();
        
        // IPv6 Address Validation
        const ipAddressSuffixInput = document.getElementById('ipAddressSuffix');
        if (ipAddressSuffixInput) {
            ipAddressSuffixInput.addEventListener('input', function() {
                const input = this;
                let errorElement = document.querySelector(`#${input.id} + .form__input-error-message`);
                if (!errorElement) {
                    errorElement = input.parentElement.nextElementSibling;
                }
                if (!errorElement) {
                    errorElement = input.closest('.form-group').querySelector('.form__input-error-message');
                }

                let ipAddressPrefix = document.getElementById('ipAddressPrefix').value;
                // Remove any trailing colons from the prefix
                ipAddressPrefix = ipAddressPrefix.replace(/:+$/, '');
                // Add :: to the prefix
                ipAddressPrefix = ipAddressPrefix + '::';
                
                // Remove any leading colons from the suffix
                const suffix = input.value.replace(/^:+/, '');
                const fullIPv6 = ipAddressPrefix + suffix;
                
                console.log('Prefix:', ipAddressPrefix);
                console.log('Suffix:', suffix);
                console.log('Full IPv6:', fullIPv6);

                if (!poolRange) {
                    console.log('No pool range defined');
                    input.classList.add('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Please select a DHCP option first';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-green-500');
                        errorElement.classList.add('text-red-500');
                    }
                    return;
                }

                console.log('Pool range:', poolRange);
                const isValid = isValidIPv6InRange(fullIPv6, poolRange.start, poolRange.end);
                console.log('Validation result:', isValid);

                if (!isValid) {
                    input.classList.add('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'IPv6 address must be valid and within the selected pool range';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-green-500');
                        errorElement.classList.add('text-red-500');
                    }
                } else {
                    input.classList.remove('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Valid IPv6 address';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-red-500');
                        errorElement.classList.add('text-green-500');
                    }
                }
            });
        }

        // DUID Validation
        const duidInput = document.getElementById('duid');
        if (duidInput) {
            duidInput.addEventListener('input', function() {
                const input = this;
                let errorElement = document.querySelector(`#${input.id} + .form__input-error-message`);
                if (!errorElement) {
                    errorElement = input.parentElement.nextElementSibling;
                }
                if (!errorElement) {
                    errorElement = input.closest('.form-group').querySelector('.form__input-error-message');
                }

                const isValid = isValidDUID(input.value);

                if (!isValid) {
                    input.classList.add('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Invalid DUID format. Must be 11 pairs of hexadecimal numbers separated by colons';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-green-500');
                        errorElement.classList.add('text-red-500');
                    }
                } else {
                    input.classList.remove('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Valid DUID format';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-red-500');
                        errorElement.classList.add('text-green-500');
                    }
                }
            });
        }

        // DHCP Options Validation
        const dhcpOptionsSelect = document.getElementById('dhcpOptions');
        if (dhcpOptionsSelect) {
            dhcpOptionsSelect.addEventListener('change', function() {
                const input = this;
                let errorElement = document.querySelector(`#${input.id} + .form__input-error-message`);
                if (!errorElement) {
                    errorElement = input.parentElement.nextElementSibling;
                }
                if (!errorElement) {
                    errorElement = input.closest('.form-group').querySelector('.form__input-error-message');
                }

                if (!input.value) {
                    input.classList.add('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Please select a DHCP option';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-green-500');
                        errorElement.classList.add('text-red-500');
                    }
                } else {
                    input.classList.remove('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Valid option selected';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-red-500');
                        errorElement.classList.add('text-green-500');
                    }
                }
            });
        }

        // Option Value Validation
        const optionValueInput = document.getElementById('optionValue');
        if (optionValueInput) {
            optionValueInput.addEventListener('input', function() {
                const input = this;
                let errorElement = document.querySelector(`#${input.id} + .form__input-error-message`);
                if (!errorElement) {
                    errorElement = input.parentElement.nextElementSibling;
                }
                if (!errorElement) {
                    errorElement = input.closest('.form-group').querySelector('.form__input-error-message');
                }

                if (!input.value) {
                    input.classList.add('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Option value is required';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-green-500');
                        errorElement.classList.add('text-red-500');
                    }
                } else {
                    input.classList.remove('border-red-500');
                    if (errorElement) {
                        errorElement.textContent = 'Valid option value';
                        errorElement.style.display = 'block';
                        errorElement.classList.remove('text-red-500');
                        errorElement.classList.add('text-green-500');
                    }
                }
            });
        }
    } else {
        form.classList.add('hidden');
        
        // Clean up event listeners
        const inputs = ['ipAddressSuffix', 'duid', 'dhcpOptions', 'optionValue'];
        inputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.replaceWith(element.cloneNode(true));
            }
        });
    }
}

function toggleLeaseForm() {
    const form = document.getElementById('leaseForm');
    const table = document.getElementById('leasesTable');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        table.classList.add('hidden');
    } else {
        form.classList.add('hidden');
        table.classList.remove('hidden');
    }
}


async function getSwitchName(switchId) {
    try {

        
        const response = await fetch(`/api/switches/${switchId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        
        return data.data.hostname || 'N/A';
    } catch (error) {

        return 'Error loading hostname';
    }
}



async function loadSwitches() {

    
    const tableBody = document.getElementById('switchesTableBody');
    if (!tableBody) {

        return;
    }

    try {
        // Show loading state
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-4 text-center">
                    <div class="flex justify-center items-center">
                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading switches...
                    </div>
                </td>
            </tr>
        `;

        const response = await fetch('/api/dhcp/subnets', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Subnets data:', data); // Debug log
        
        // Group subnets by switch_id
        const groupedSubnets = data.reduce((acc, subnet) => {
            // Add debug logging

            
            // Ensure switch_id is correctly set
            const switchId = subnet.switch_id !== undefined ? subnet.switch_id : null;
            if (switchId === null || switchId === undefined) {

                return acc;
            }

            // Convert switchId to string to ensure consistent handling
            const switchIdKey = switchId.toString();
            
            if (!acc[switchIdKey]) {
                acc[switchIdKey] = [];
            }
            acc[switchIdKey].push(subnet);
            return acc;
        }, {});

        console.log('Grouped subnets:', groupedSubnets); // Debug log

        // Clear the loading state
        tableBody.innerHTML = '';        
        
        // If no data
        if (Object.keys(groupedSubnets).length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No switches found
                    </td>
                </tr>
            `;
            return;
        }

        // Render the grouped subnets
        for (const [switchId, subnets] of Object.entries(groupedSubnets)) {
            console.log('Processing switch:', switchId, subnets); // Debug log
            
            const row = document.createElement('tr');
            row.className = "border-b border-gray-200 hover:bg-gray-100";
            
            // Fetch switch name
            console.log('Fetching switch name for ID:', switchId); // Debug log
            const switchName = await getSwitchName(switchId);
            console.log('Got switch name:', switchName); // Debug log
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${switchName}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${subnets.map(subnet => {
                        const bviNum = subnet.bvi_interface !== null && subnet.bvi_interface !== undefined 
                            ? 'BVI' + (100 + parseInt(subnet.bvi_interface)) 
                            : 'N/A';
                        return bviNum;
                    }).join(', ')}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${subnets.map(subnet => subnet.subnet).join(', ')}
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                    ${subnets.map(subnet => 
                        subnet.pool ? `<div class="flex flex-col"><span>${subnet.pool.start}</span><span>${subnet.pool.end}</span></div>` : 'No pool'
                    ).join(', ')}
                </td>
                <!-- other cells -->
                <td class="px-6 py-3">
                    <div class="flex space-x-2">
                        <button onclick="toggleLeases(this, '${switchId}', '${subnets[0].bvi_interface_id}')" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            View Leases
                        </button>
                        <button onclick="viewStaticLeases('${subnets[0].id}')" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            View Reservations
                        </button>
                        <button onclick="toggleStaticLeaseForm('${subnets[0].id}', '${subnets[0].subnet}')" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Reservation
                        </button>
                    </div>
                </td>
            `;


            tableBody.appendChild(row);
        }

    } catch (error) {

        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-4 text-center text-red-500">
                    Error loading switches: ${error.message}
                </td>
            </tr>
        `;
    }
}

let poolRange = null;
let subnetPrefix = null;

function setValidationMessage(input, message, isError = true) {
    const validationMessage = input.parentElement.nextElementSibling;
    if (validationMessage) {
        validationMessage.textContent = message;
        if (isError) {
            input.classList.add('border-red-500');
            validationMessage.classList.add('text-red-600');
            validationMessage.classList.remove('text-green-600');
        } else {
            input.classList.remove('border-red-500');
            validationMessage.classList.remove('text-red-600');
            validationMessage.classList.add('text-green-600');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded - initializing validation');

    loadSwitches();
    populateSubnetOptions();
    populateDhcpOptions();
    
    const ipAddressSuffixInput = document.getElementById('ipAddressSuffix');
    if (ipAddressSuffixInput) {
        ipAddressSuffixInput.addEventListener('input', function() {
            const input = this;
            const validationMessage = input.nextElementSibling;
            const ipAddressPrefix = document.getElementById('ipAddressPrefix').value.replace('::', '');
            const ipAddress = ipAddressPrefix + input.value;

            if (!isValidIPv6(ipAddress) || !poolRange || !isValidIPv6InRange(ipAddress, poolRange.start, poolRange.end)) {
                input.classList.remove('border-gray-300');
                input.classList.add('border-red-500');
                if (validationMessage) {
                    validationMessage.textContent = 'Invalid IPv6 address or out of range';
                    validationMessage.classList.remove('text-green-600');
                    validationMessage.classList.add('text-red-600');
                }
            } else {
                input.classList.remove('border-red-500');
                input.classList.add('border-gray-300');
                if (validationMessage) {
                    validationMessage.textContent = 'Valid IPv6 address';
                    validationMessage.classList.remove('text-red-600');
                    validationMessage.classList.add('text-green-600');
                }
            }
        });
    }

    const duidInput = document.getElementById('duid');
    if (duidInput) {
        duidInput.addEventListener('input', function() {
            const input = this;
            const validationMessage = input.nextElementSibling;
            const duid = input.value;

            if (!duid) {
                input.classList.remove('border-gray-300');
                input.classList.add('border-red-500');
                if (validationMessage) {
                    validationMessage.textContent = 'DUID is required';
                    validationMessage.classList.remove('text-green-600');
                    validationMessage.classList.add('text-red-600');
                }
            } else {
                input.classList.remove('border-red-500');
                input.classList.add('border-gray-300');
                if (validationMessage) {
                    validationMessage.textContent = 'Valid DUID';
                    validationMessage.classList.remove('text-red-600');
                    validationMessage.classList.add('text-green-600');
                }
            }
        });
    }
});




function showLeases(switchId, bviId) {
    const url = new URL(window.location);
    url.searchParams.set('switch', switchId);
    url.searchParams.set('bvi', bviId);
    window.history.pushState({}, '', url);

    let lastIpAddress = 'start';
    const itemsPerPage = 10;

    Swal.fire({
        title: 'DHCPv6 Leases',
        html: `
            <div id="leasesTableContainer" class="hidden">
                <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">IP Address</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">DUID</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">State</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Start Time</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">End Time</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="leasesTableBody" class="divide-y divide-gray-200 bg-white">
                        </tbody>
                    </table>
                </div>
                <div id="paginationContainer" class="mt-4 flex justify-between items-center px-4">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button id="prevButtonMobile" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </button>
                        <button id="nextButtonMobile" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700" id="paginationInfo">
                                Showing <span class="font-medium" id="startRange">0</span> to <span class="font-medium" id="endRange">0</span> of <span class="font-medium" id="totalItems">0</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <button id="prevButton" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <button id="nextButton" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <div id="loadingIndicator" class="flex justify-center items-center py-4">
                <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading leases...
            </div>
        `,
        width: '80%',
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: () => {
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');
            const prevButtonMobile = document.getElementById('prevButtonMobile');
            const nextButtonMobile = document.getElementById('nextButtonMobile');

            // Add event listeners for pagination
            prevButton?.addEventListener('click', () => {
                if (lastIpAddress !== 'start') {
                    loadLeases(switchId, bviId, 'start', itemsPerPage);
                    lastIpAddress = 'start';
                }
            });

            nextButton?.addEventListener('click', () => {
                const tableBody = document.getElementById('leasesTableBody');
                if (tableBody) {
                    const rows = tableBody.getElementsByTagName('tr');
                    if (rows.length > 0) {
                        const lastRow = rows[rows.length - 1];
                        const ipAddress = lastRow.cells[0].textContent;
                        lastIpAddress = ipAddress;
                        loadLeases(switchId, bviId, lastIpAddress, itemsPerPage);
                    }
                }
            });

            prevButtonMobile?.addEventListener('click', () => {
                if (lastIpAddress !== 'start') {
                    loadLeases(switchId, bviId, 'start', itemsPerPage);
                    lastIpAddress = 'start';
                }
            });

            nextButtonMobile?.addEventListener('click', () => {
                const tableBody = document.getElementById('leasesTableBody');
                if (tableBody) {
                    const rows = tableBody.getElementsByTagName('tr');
                    if (rows.length > 0) {
                        const lastRow = rows[rows.length - 1];
                        const ipAddress = lastRow.cells[0].textContent;
                        lastIpAddress = ipAddress;
                        loadLeases(switchId, bviId, lastIpAddress, itemsPerPage);
                    }
                }
            });

            // Initial load with 'start'
            loadLeases(switchId, bviId, 'start', itemsPerPage);
        }
    });
}

let subnetId = null;

async function loadLeases(switchId, bviId, from = 'start', limit = 10) {
    console.log('loadLeases called with:', { switchId, bviId, from, limit }); // Debug log

    const tableContainer = document.getElementById('leasesTableContainer');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const tableBody = document.getElementById('leasesTableBody');
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const prevButtonMobile = document.getElementById('prevButtonMobile');
    const nextButtonMobile = document.getElementById('nextButtonMobile');

    if (loadingIndicator) loadingIndicator.classList.remove('hidden');
    if (tableContainer) tableContainer.classList.add('hidden');

    try {
        const response = await fetch(`/api/dhcp/leases/${switchId}/${bviId}/${from}/${limit}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseData = await response.json();
        
        if (!responseData.success || !responseData.data || !responseData.data.leases) {
            throw new Error('Invalid data format received from server');
        }

        const { leases } = responseData.data;
        if (leases.length > 0) {
            subnetId = leases[0]['subnet-id']; // Extract the subnet-id from the first lease
            console.log('subnetId received and saved:', subnetId); // Debug log
        }

        const pagination = responseData.data.pagination;

        if (loadingIndicator) loadingIndicator.classList.add('hidden');
        if (tableContainer) tableContainer.classList.remove('hidden'); // Show the container

        if (tableBody) {
            if (leases.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-500">No leases found</span>
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                tableBody.innerHTML = leases.map(lease => `
                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm text-gray-900">
                                    ${lease['ip-address']}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${lease.duid}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                lease.state === 0 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-yellow-100 text-yellow-800'
                            }">
                                ${lease.state === 0 ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${new Date(lease.cltt * 1000).toLocaleString()}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${new Date((lease.cltt + lease['valid-lft']) * 1000).toLocaleString()}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium">
                            <button onclick="deleteLease('${lease['ip-address']}')" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        }

        if (prevButton && prevButtonMobile) {
            prevButton.disabled = from === 'start';
            prevButtonMobile.disabled = from === 'start';
            prevButton.classList.toggle('opacity-50', from === 'start');
            prevButtonMobile.classList.toggle('opacity-50', from === 'start');
        }

        if (nextButton && nextButtonMobile) {
            const hasMore = pagination.hasMore;
            nextButton.disabled = !hasMore;
            nextButtonMobile.disabled = !hasMore;
            nextButton.classList.toggle('opacity-50', !hasMore);
            nextButtonMobile.classList.toggle('opacity-50', !hasMore);
        }

        const startRange = document.getElementById('startRange');
        const endRange = document.getElementById('endRange');
        const totalItems = document.getElementById('totalItems');
        
        if (startRange && endRange && totalItems) {
            startRange.textContent = pagination.total > 0 ? 1 : 0;
            endRange.textContent = leases.length;
            totalItems.textContent = pagination.total;
        }

    } catch (error) {
        console.error('Error loading leases:', error);
        if (loadingIndicator) loadingIndicator.classList.add('hidden');
        if (tableContainer) tableContainer.classList.remove('hidden');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center">
                        <div class="flex flex-col items-center justify-center space-y-3">
                            <svg class="h-8 w-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-red-500">Error loading leases: ${error.message}</span>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
}

async function viewStaticLeases(subnetId) {
    let detailsRow;
    let currentRow;

    try {
        // Show loading state in the current row
        currentRow = event.target.closest('tr');
        const nextRow = currentRow.nextElementSibling;
        
        // If there's already a details row, remove it
        if (nextRow && nextRow.classList.contains('details-row')) {
            nextRow.remove();
            return;
        }

        // Create and insert the details row
        detailsRow = document.createElement('tr');
        detailsRow.className = 'details-row bg-gray-50';
        detailsRow.innerHTML = `
            <td colspan="6" class="px-6 py-4">
                <div class="flex justify-center items-center">
                    <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading reservations...
                </div>
            </td>
        `;
        currentRow.parentNode.insertBefore(detailsRow, currentRow.nextSibling);

        // Fetch static leases data
        const response = await fetch(`/api/dhcp/static/${subnetId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        // Check if response indicates no hosts found (this is not an error)
        if (data.result === 3 || (data.text && data.text.includes('0 IPv6 host(s) found'))) {
            // No reservations found - this is normal, not an error
            detailsRow.innerHTML = `
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                    No static reservations found for this subnet
                </td>
            `;
            return;
        }
        
        if (data.result === 0) {
            const hosts = data.hosts || [];
            
            if (hosts.length === 0) {
                detailsRow.innerHTML = `
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No static reservations found for this subnet
                    </td>
                `;
                return;
            }

            // Create table with static leases data
            const tableContent = `
                <td colspan="6" class="px-6 py-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Address</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hostname</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${hosts.map(host => `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        ${host['ip-addresses'] ? host['ip-addresses'].join(', ') : 'N/A'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${host['hw-address'] || 'N/A'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${host.hostname || 'N/A'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <button onclick="editReservation(${JSON.stringify(host).replace(/"/g, '&quot;')})" 
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            Edit
                                        </button>
                                        <button onclick="deleteReservation('${host['ip-addresses'][0]}', ${host['subnet-id']})" 
                                                class="text-red-600 hover:text-red-900">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </td>
            `;
            detailsRow.innerHTML = tableContent;
        } else {
            throw new Error(data.message || 'Failed to fetch static leases');
        }

    } catch (error) {
        console.error('Error:', error);
        if (detailsRow) {
            detailsRow.innerHTML = `
                <td colspan="6" class="px-6 py-4 text-center text-red-500">
                    Error loading reservations: ${error.message}
                </td>
            `;
        }
    }
}


function formatDhcpOptions(options) {
    if (!options || options.length === 0) return '-';
    return options.map(opt => `${opt.name}: ${opt.value}`).join(', ');
}



function updatePaginationControls(pagination, switchId, bviId) {
    const paginationContainer = document.getElementById('paginationContainer');
    if (!paginationContainer) {

        return;
    }

    // Calculate if there are more leases to load
    const hasMore = pagination.hasMore && pagination.total > (pagination.limit || 10);

    if (hasMore) {
        paginationContainer.innerHTML = `
            <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                <div class="flex justify-between flex-1 sm:hidden">
                    <button onclick="loadLeases('${switchId}', '${bviId}', '${pagination.nextFrom}', ${pagination.limit})"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Load More
                    </button>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium">${pagination.limit}</span> of <span class="font-medium">${pagination.total}</span> results
                        </p>
                    </div>
                    <div>
                        <button onclick="loadLeases('${switchId}', '${bviId}', '${pagination.nextFrom}', ${pagination.limit})"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Load More
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        paginationContainer.innerHTML = `
            <div class="flex justify-center px-4 py-3 sm:px-6">
                <p class="text-sm text-gray-700">
                    Showing all ${pagination.total} results
                </p>
            </div>
        `;
    }
}




function deleteLease(IPv6Address) {
    console.log('deleteLease called with:', { IPv6Address, subnetId }); // Debug log

    Swal.fire({
        title: 'Delete Lease?',
        text: `Are you sure you want to delete the lease for ${IPv6Address}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('/api/dhcp/leases', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        'ip-address': IPv6Address,
                        'subnet-id': subnetId,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    Swal.fire('Deleted!', 'The lease has been deleted.', 'success');
                    // Optionally, reload the leases
                    loadLeases(); // Call loadLeases without parameters
                } else {
                    Swal.fire('Error!', data.message || 'An error occurred while deleting the lease.', 'error');
                }
            } catch (error) {
                Swal.fire('Error!', error.message || 'An error occurred while deleting the lease.', 'error');
            }
        }
    });
}

async function editReservation(host) {
    // Get existing options
    const existingOptions = host['option-data'] || [];
    const optionsHtml = existingOptions.map((opt, idx) => `
        <div class="border-b pb-2 mb-2" id="option-${idx}">
            <div class="flex gap-2 items-center">
                <input type="text" value="${opt.name}" readonly class="flex-1 px-2 py-1 border rounded bg-gray-50 text-sm" />
                <input type="text" value="${opt.data}" id="opt-value-${idx}" class="flex-1 px-2 py-1 border rounded text-sm" />
                <button onclick="document.getElementById('option-${idx}').remove()" class="text-red-600 text-sm">Remove</button>
            </div>
        </div>
    `).join('');

    const { value: formValues } = await Swal.fire({
        title: 'Edit Reservation',
        html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                    <input id="edit-ip" type="text" value="${host['ip-addresses'][0]}" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DUID</label>
                    <input id="edit-duid" type="text" value="${host.duid || ''}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hostname</label>
                    <input id="edit-hostname" type="text" value="${host.hostname || ''}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DHCP Options</label>
                    <div id="options-container" class="border rounded-md p-3 max-h-48 overflow-y-auto">
                        ${optionsHtml || '<p class="text-sm text-gray-500">No options configured</p>'}
                    </div>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        confirmButtonColor: '#3B82F6',
        width: '600px',
        preConfirm: () => {
            // Collect updated options
            const options = [];
            existingOptions.forEach((opt, idx) => {
                const optEl = document.getElementById(`option-${idx}`);
                if (optEl) {
                    const valueInput = document.getElementById(`opt-value-${idx}`);
                    if (valueInput) {
                        options.push({
                            code: opt.code,
                            name: opt.name,
                            data: valueInput.value,
                            space: opt.space || 'dhcp6'
                        });
                    }
                }
            });

            return {
                ip: document.getElementById('edit-ip').value,
                duid: document.getElementById('edit-duid').value,
                hostname: document.getElementById('edit-hostname').value,
                options: options
            };
        }
    });

    if (formValues) {
        try {
            // First delete the old reservation
            const deleteResponse = await fetch('/api/dhcp/leases', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    'ip-address': host['ip-addresses'][0],
                    'subnet-id': host['subnet-id']
                })
            });
            
            // Then add the updated reservation
            const addResponse = await fetch('/api/dhcp/static', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ipAddress: formValues.ip,
                    duid: formValues.duid,
                    subnetId: parseInt(host['subnet-id']),
                    hostname: formValues.hostname,
                    options: formValues.options
                })
            });

            const result = await addResponse.json();
            
            if (result.success) {
                Swal.fire('Updated!', 'Reservation has been updated.', 'success');
                // Reload reservations for this subnet
                loadStaticLeases(host['subnet-id']);
            } else {
                Swal.fire('Error!', result.message || 'Failed to update reservation', 'error');
            }
        } catch (error) {
            Swal.fire('Error!', error.message || 'An error occurred', 'error');
        }
    }
}

async function deleteReservation(ipAddress, subnetId) {
    const result = await Swal.fire({
        title: 'Delete Reservation?',
        html: `Are you sure you want to delete the reservation for <strong>${ipAddress}</strong>?<br><small class="text-gray-600">This cannot be undone.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/dhcp/reservations/${ipAddress}`, {
                method: 'DELETE'
            });

            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Deleted!', 'Reservation has been deleted.', 'success');
                // Reload reservations for this subnet
                loadStaticLeases(subnetId);
            } else {
                Swal.fire('Error!', data.message || 'Failed to delete reservation', 'error');
            }
        } catch (error) {
            Swal.fire('Error!', error.message || 'An error occurred', 'error');
        }
    }
}

</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>
