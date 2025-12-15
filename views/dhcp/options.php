<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;


// Set active navigation item
$currentPage = 'DHCP';
$subPage = 'options';


// Page title
$pageTitle = 'DHCPv6 Options Configuration';

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}


// Start output buffering
ob_start();

// Include the DCHP menu
require BASE_PATH . '/views/dhcp-menu.php';



?>

<!-- Main Content -->
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">DHCPv6 Option</h1>
        </div>
        <!-- Options Table -->
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Code
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Name
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Space
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Value
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="optionsTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Table content will be dynamically inserted here -->
            </tbody>
        </table>
        
        </div>
    </div>
</div>



<!-- Create Modal -->
 <!-- Error Message Container -->
<div id="errorMessage" class="hidden fixed bottom-0 right-0 mb-4 mr-4 bg-red-500 text-white px-6 py-4 rounded-lg">
</div>

<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Add DHCPv6 Option</h3>
            <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="createOptionForm" method="post" onsubmit="handleCreateSubmit(event)" novalidate>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="code">
                    Option Code <span class="text-red-500">*</span>
                </label>
                <input type="number" id="code" name="code" required min="0" max="65535"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="codeError" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                    Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       oninput="validateName(this)">
                <p id="nameError" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="type">
                    Type <span class="text-red-500">*</span>
                </label>
                <select id="type" name="type" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        onchange="validateType(this)">
                    <option value="">Select a type</option>
                    <option value="binary">Binary</option>
                    <option value="boolean">Boolean</option>
                    <option value="uint8">Uint8</option>
                    <option value="uint16">Uint16</option>
                    <option value="uint32">Uint32</option>
                    <option value="ipv6-address">IPv6 Address</option>
                    <option value="ipv6-prefix">IPv6 Prefix</option>
                    <option value="string">String</option>
                    <option value="fqdn">FQDN</option>
                </select>
                <p id="typeError" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="array">
                    Array
                </label>
                <input type="checkbox" id="array" name="array"
                       class="form-checkbox h-5 w-5 text-blue-600">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="space">
                    Space <span class="text-red-500">*</span>
                </label>
                <input type="text" id="space" name="space" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       oninput="validateSpace(this)">
                <p id="spaceError" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeCreateModal()"
                        class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 focus:outline-none focus:shadow-outline">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <!-- Will be populated dynamically -->
</div>
<script>
    // Utility Functions
    function showSuccess(message) {
        const successDiv = document.getElementById('successMessage');
        successDiv.textContent = message;
        successDiv.classList.remove('hidden');
        setTimeout(() => successDiv.classList.add('hidden'), 3000);
    }

    function showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
        setTimeout(() => errorDiv.classList.add('hidden'), 3000);
    }

    // Validation Functions
    function validateDhcpOption(field, value, type = '') {
        // Basic validation - check if value exists
        if (value === undefined || value === null || value === '') {
            return {
                isValid: false,
                message: 'This field is required'
            };
        }

        // Convert value to string if it isn't already
        const stringValue = String(value);

        switch (field) {
            case 'value':
                switch (type.toLowerCase()) {
                    case 'uint32':
                        // Check if it's a valid positive integer within uint32 range
                        const num = Number(stringValue);
                        const isValidUint32 = Number.isInteger(num) && num >= 0 && num <= 4294967295;
                        return {
                            isValid: isValidUint32,
                            message: isValidUint32 ? '' : 'Please enter a valid number between 0 and 4294967295'
                        };

                    case 'ipv6-address':
                        const ipv6Regex = /^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;
                        
                        // Support comma-separated addresses for array options
                        if (stringValue.includes(',')) {
                            const addresses = stringValue.split(',').map(addr => addr.trim());
                            const allValid = addresses.every(addr => ipv6Regex.test(addr));
                            return {
                                isValid: allValid,
                                message: allValid ? '' : 'One or more IPv6 addresses are invalid. Use format: 2001:db8::1, 2001:db8::2'
                            };
                        }
                        
                        return {
                            isValid: ipv6Regex.test(stringValue),
                            message: ipv6Regex.test(stringValue) ? '' : 'Please enter a valid IPv6 address or comma-separated addresses'
                        };

                    default:
                        // For any other type, just ensure it's not empty
                        return {
                            isValid: true,
                            message: ''
                        };
                }

            default:
                return {
                    isValid: true,
                    message: ''
                };
        }
    }

    function updateValidationUI(prefix, validation) {
        const errorElement = document.getElementById(`${prefix}Error`);
        if (errorElement) {
            errorElement.textContent = validation.message;
            errorElement.classList.toggle('hidden', validation.isValid);
        }
    }



    // Input Field Generation
    function generateInputField(type, value = '', id = 'optionData') {
        let inputType = 'text';
        let extraAttributes = '';
        let placeholder = '';
        
        switch (type.toLowerCase()) {
            case 'uint8':
                inputType = 'number';
                extraAttributes = 'min="0" max="255"';
                placeholder = '0-255';
                break;
            case 'uint16':
                inputType = 'number';
                extraAttributes = 'min="0" max="65535"';
                placeholder = '0-65535';
                break;
            case 'uint32':
                inputType = 'number';
                extraAttributes = 'min="0" max="4294967295"';
                placeholder = '0-4294967295';
                break;
            case 'string':
                inputType = 'text';
                placeholder = 'Enter text string';
                break;
            case 'ipv6-address':
                inputType = 'text';
                placeholder = '2001:db8::1 or 2001:db8::1, 2001:db8::2';
                break;
            case 'ipv6-prefix':
                inputType = 'text';
                placeholder = '2001:db8::/64';
                break;
            case 'psid':
                inputType = 'number';
                extraAttributes = 'min="0" max="65535"';
                placeholder = 'PSID value (0-65535)';
                break;
            case 'binary':
                inputType = 'text';
                placeholder = 'Hexadecimal format';
                break;
            case 'fqdn':
                inputType = 'text';
                placeholder = 'example.com';
                break;
            default:
                inputType = 'text';
                placeholder = `Enter ${type}`;
        }

        return `<input type="${inputType}" 
                       id="${id}" 
                       value="${value}"
                       placeholder="${placeholder}"
                       ${extraAttributes}
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <div class="text-sm text-gray-500 mt-1">${placeholder}</div>`;
    }

    // Modal Functions
function openCreateModal(optionDef) {
    const modal = document.getElementById('createModal');
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Configure DHCPv6 Option</h3>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="createOptionForm" onsubmit="handleCreateSubmit(event)">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Option Code</label>
                    <input type="number" id="optionCode" value="${optionDef.code}" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" id="optionName" value="${optionDef.name}" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                    <input type="text" id="optionType" value="${optionDef.type}" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Space</label>
                    <input type="text" id="optionSpace" value="${optionDef.space}" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="optionData">
                        Value <span class="text-red-500">*</span>
                    </label>
                    ${generateInputField(optionDef.type, '', 'optionData')}
                    <p id="dataError" class="text-red-500 text-xs italic hidden"></p>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeCreateModal()" 
                            class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                        Save
                    </button>
                </div>
            </form>
        </div>
    `;
    modal.classList.remove('hidden');

    // Setup validation after modal content is created
    const optionData = document.getElementById('optionData');
    if (optionData) {
        optionData.addEventListener('input', function() {
            const validation = validateDhcpOption('data', this.value, optionDef.type);
            updateValidationUI('data', validation);
        });
    }
}


    function closeCreateModal() {
        const modal = document.getElementById('createModal');
        modal.classList.add('hidden');
        const errorElement = document.getElementById('dataError');
        if (errorElement) {
            errorElement.classList.add('hidden');
            errorElement.textContent = '';
        }
    }

    let optionsData = [];
    async function loadOptions() {
    try {
        const response = await fetch('/api/dhcp/options');
        const data = await response.json();
        optionsData = data; // Store the data globally
        updateOptionsTable(data);
    } catch (error) {
        console.error('Error loading options:', error);
        showError('Failed to load options');
    }
}


    async function handleCreateSubmit(event) {
        event.preventDefault();
    
        const space = document.getElementById('optionSpace').value;
        const optionData = document.getElementById('optionData').value;
        const code = document.getElementById('optionCode').value;
        
        try {
            const response = await fetch('/api/dhcp/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    space: String(space),
                    data: String(optionData).trim(),
                    code: parseInt(code, 10)
                })
            });
    
            const result = await response.json();
            
            if (result.success) {
                closeCreateModal();
                loadOptions();
                showSuccess('Option created successfully');
            } else {
                throw new Error(result.error || 'Failed to save option');
            }
        } catch (error) {
            console.error('Error:', error);
            const errorElement = document.getElementById('dataError');
            if (errorElement) {
                errorElement.textContent = error.message;
                errorElement.classList.remove('hidden');
            }
        }
    }
    
    async function handleEditSubmit(event) {
    event.preventDefault();

    const code = document.getElementById('editCode').value;
    const value = document.getElementById('editValue').value;
    const space = document.getElementById('editSpace').value;

    if (!code || !value || !space) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Code, value and space are required'
        });
        return;
    }

    try {
        const response = await fetch(`/api/dhcp/options/${code}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                code: parseInt(code),
                space: space,
                data: value
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Show success message
        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Option updated successfully'
        });

        // Close the modal and refresh the table
        closeEditModal();
        await loadOptions();
        
    } catch (error) {
        console.error('Error updating option:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to update option'
        });
    }
}

    

async function loadOptions() {
    try {
        const response = await fetch('/api/dhcp/options', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();
        if (result.success) {
            updateOptionsTable(result.data);
        } else {
            throw new Error(result.error || 'Failed to load options');
        }
    } catch (error) {
        showError('Failed to load options: ' + error.message);
    }
}


function findOptionDef(code) {
    if (!optionsData || !Array.isArray(optionsData)) {
        console.error('Options data is not available');
        return null;
    }

    const option = optionsData.find(opt => 
        opt.definition && opt.definition.code === code
    );

    if (!option) {
        console.error(`Option with code ${code} not found`);
        return null;
    }

    return {
        code: option.definition.code,
        name: option.definition.name,
        type: option.definition.type,
        space: option.definition.space,
        value: option.option ? option.option.data : ''
    };
}

// Function to validate option value based on type
function validateValue(value, type) {
    if (!value) {
        showError('Value is required');
        return false;
    }

    const validation = validateDhcpOption('data', value, type);
    if (!validation.isValid) {
        showError(validation.error);
        return false;
    }

    return true;
}

// Function to update the options table with new data
function updateOptionsTable(options) {
    const tableBody = document.getElementById('optionsTableBody');
    if (!tableBody) {
        console.error('Options table body not found');
        return;
    }

    // Check if options is undefined or empty
    if (!options || options.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No options found</td></tr>';
        return;
    }

    tableBody.innerHTML = '';
    options.forEach(option => {
        const definition = option.definition || {};

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${definition.code || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${definition.name || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${definition.type || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${definition.space || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${option.option ? option.option.data : 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${option.option ? `
                    <button onclick='openEditModal(${JSON.stringify(option).replace(/'/g, "&#39;").replace(/"/g, "&quot;")})'
                            class="text-blue-600 hover:text-blue-900 mr-2">
                        Edit
                    </button>
                    <button onclick="deleteOption(${definition.code}, '${definition.space}')"
                            class="text-red-600 hover:text-red-900">
                        Delete
                    </button>
                ` : `
                    <button onclick='openEditModal(${JSON.stringify({definition: option.definition, option: {data: "", space: definition.space}}).replace(/'/g, "&#39;").replace(/"/g, "&quot;")})'
                            class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200">
                        Configure DHCPv6 Option
                    </button>
                `}
            </td>
        `;
        tableBody.appendChild(row);
    });
}

// Function to close edit modal
function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Error handling utility function
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (!errorDiv) {
        console.error('Error message element not found');
        alert(message); // Fallback to alert if element not found
        return;
    }
    
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    
    // Hide the message after 3 seconds
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 3000);
}

function openEditModal(option) {
    const modal = document.getElementById('editModal');
    if (!modal) return;

    const originalValue = option.option ? option.option.data : '';
    const optionType = option.definition.type;

    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Edit DHCPv6 Option</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="editOptionForm" onsubmit="handleEditSubmit(event)">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Option Code</label>
                    <input type="number" id="editCode" value="${option.definition.code}" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" id="editName" value="${option.definition.name}"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Space</label>
                    <input type="text" id="editSpace" value="${option.definition.space}"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                        readonly disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Value <span class="text-red-500">*</span></label>
                    <input type="text" id="editValue" value="${originalValue}"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p id="editError" class="text-red-500 text-xs italic hidden"></p>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeEditModal()" 
                            class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" id="saveButton"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline disabled:opacity-50 disabled:cursor-not-allowed">
                        Save
                    </button>
                </div>
            </form>
        </div>
    `;
    modal.classList.remove('hidden');

    // Setup value change detection
    const editValue = document.getElementById('editValue');
    const saveButton = document.getElementById('saveButton');

    if (editValue && saveButton) {
        // Initial state
        saveButton.disabled = true;

        editValue.addEventListener('input', (e) => {
            const newValue = e.target.value;
            
            // Pass the type to the validation function
            const validation = validateDhcpOption('value', newValue, optionType);
            updateValidationUI('edit', validation);

            // Disable save button if value hasn't changed or is invalid
            saveButton.disabled = (newValue === originalValue) || !validation.isValid;
        });

        // Trigger initial validation
        const initialValidation = validateDhcpOption('value', originalValue, optionType);
        updateValidationUI('edit', initialValidation);
    }
}




// Function to delete an option
function deleteOption(code, space) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Perform delete operation with both code and space
            fetch(`/api/dhcp/options/${code}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    space: space
                })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return Swal.fire(
                    'Deleted!',
                    'Option has been deleted.',
                    'success'
                );
            })
            .then(() => {
                loadOptions(); 
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'Failed to delete option.',
                    'error'
                );
            });
        }
    });
}


    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Add your initialization code here
        loadOptions();
    });
</script>




<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>