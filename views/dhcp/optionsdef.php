<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;


// Set active navigation item
$currentPage = 'DHCP';
$subPage = 'optionsdef';


// Page title
$pageTitle = 'DHCPv6 Option Defintions Configuration';

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
            <h1 class="text-2xl font-bold text-gray-800">DHCPv6 Option Definitions</h1>
            <button onclick="openCreateDefModal()" 
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Option
            </button>
        </div>

        <!-- Options Table -->
        <div class="overflow-x-auto">
            <table id="optionDefsTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Space</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Array</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Table content will be dynamically populated -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create DefModal -->
<div id="createDefModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Add DHCPv6 Option Definition</h3>
            <button onclick="closeCreateDefModal()" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="createOptionDefForm" method="post" onsubmit="handleCreateSubmit(event)" novalidate>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="code">
                    Option Code <span class="text-red-500">*</span>
                </label>
                <input type="number" id="code" name="code" required min="0" max="65535"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       oninput="validateOptionCode(this)">
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
                <button type="button" onclick="closeCreateDefModal()"
                        class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 focus:outline-none focus:shadow-outline">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit DefModal -->
<div id="editDefModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <!-- Will be populated dynamically -->
</div>

<script>
document.addEventListener('DOMContentLoaded', loadOptionDefs);

// Validation Functions
function validateOptionCode(input) {
    const errorElement = document.getElementById('codeError');
    const value = parseInt(input.value);
    
    if (!input.value) {
        showInputError(input, errorElement, 'Option code is required');
        return false;
    }
    
    if (isNaN(value) || value < 0 || value > 65535) {
        showInputError(input, errorElement, 'Option code must be between 0 and 65535');
        return false;
    }
    
    hideInputError(input, errorElement);
    return true;
}

function validateName(input) {
    const errorElement = document.getElementById('nameError');
    
    if (!input.value.trim()) {
        showInputError(input, errorElement, 'Name is required');
        return false;
    }
    
    if (input.value.length > 64) {
        showInputError(input, errorElement, 'Name must be less than 64 characters');
        return false;
    }
    
    hideInputError(input, errorElement);
    return true;
}

function validateType(input) {
    const errorElement = document.getElementById('typeError');
    
    if (!input.value) {
        showInputError(input, errorElement, 'Type is required');
        return false;
    }
    
    hideInputError(input, errorElement);
    return true;
}

function validateSpace(input) {
    if (!input) {
        console.error('Space input element not found');
        return false;
    }

    const errorElement = input.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains('text-red-500')) {
        console.error('Space error element not found');
        return false;
    }

    if (!input.value.trim()) {
        showInputError(input, errorElement, 'Space is required');
        return false;
    }

    const space = input.value.trim();
    
    // Check for valid characters (letters, numbers, and hyphens)
    const validSpaceRegex = /^[a-zA-Z0-9-]+$/;
    if (!validSpaceRegex.test(space)) {
        showInputError(input, errorElement, 'Space can only contain letters, numbers, and hyphens');
        return false;
    }

    // Check length
    if (space.length > 255) {
        showInputError(input, errorElement, 'Space must not exceed 255 characters');
        return false;
    }

    hideInputError(input, errorElement);
    return true;
}

function isValidIPv6(address) {
    try {
        return address.match(/^(?:(?:[a-fA-F\d]{1,4}:){7}[a-fA-F\d]{1,4}|(?=(?:[a-fA-F\d]{0,4}:){0,7}[a-fA-F\d]{0,4}$)(([0-9a-fA-F]{1,4}:){1,7}|:)((:[0-9a-fA-F]{1,4}){1,7}|:))$/);
    } catch (e) {
        return false;
    }
}

// Helper Functions
function showInputError(input, errorElement, message) {
    input.classList.add('border-red-500');
    errorElement.textContent = message;
    errorElement.classList.remove('hidden');
}

function hideInputError(input, errorElement) {
    input.classList.remove('border-red-500');
    errorElement.classList.add('hidden');
}

// DefModal Functions
function openCreateDefModal() {
    document.getElementById('createDefModal').classList.remove('hidden');
}

function closeCreateDefModal() {
    document.getElementById('createDefModal').classList.add('hidden');
    document.getElementById('createOptionDefForm').reset();
    // Clear any error messages
    const errorElements = document.querySelectorAll('.text-red-500');
    errorElements.forEach(element => element.classList.add('hidden'));
}

// Generate input field based on option type
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
            placeholder = '2001:db8::1';
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

function openEditDefModal(optionDef) {
    originalOptionDef = { ...optionDef };

    const modal = document.getElementById('editDefModal');
        modal.innerHTML = `
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Edit DHCPv6 Option Definition</h3>
                    <button onclick="closeEditDefModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form id="editOptionDefForm" onsubmit="handleEditSubmit(event)">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Option Code</label>
                        <input type="number" id="editCode" value="${optionDef.code}" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100" 
                            readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                        <input type="text" id="editName" value="${optionDef.name}" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" 
                            required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                        <select id="editType" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" 
                                required>
                            <option value="">Select Type</option>
                            <option value="binary" ${optionDef.type === 'binary' ? 'selected' : ''}>Binary</option>
                            <option value="boolean" ${optionDef.type === 'boolean' ? 'selected' : ''}>Boolean</option>
                            <option value="uint8" ${optionDef.type === 'uint8' ? 'selected' : ''}>Uint8</option>
                            <option value="uint16" ${optionDef.type === 'uint16' ? 'selected' : ''}>Uint16</option>
                            <option value="uint32" ${optionDef.type === 'uint32' ? 'selected' : ''}>Uint32</option>
                            <option value="ipv6-address" ${optionDef.type === 'ipv6-address' ? 'selected' : ''}>IPv6 Address</option>
                            <option value="ipv6-prefix" ${optionDef.type === 'ipv6-prefix' ? 'selected' : ''}>IPv6 Prefix</option>
                            <option value="string" ${optionDef.type === 'string' ? 'selected' : ''}>String</option>
                            <option value="fqdn" ${optionDef.type === 'fqdn' ? 'selected' : ''}>FQDN</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Space</label>
                        <input type="text" id="editSpace" value="${optionDef.space}" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" 
                            required>
                        <span class="text-red-500 text-xs hidden"></span>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="editArray" ${optionDef.array ? 'checked' : ''} class="mr-2">
                            <span class="text-gray-700 text-sm font-bold">Array</span>
                        </label>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeEditDefModal()" 
                                class="mr-2 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        `;
        modal.classList.remove('hidden');

    // Add change listeners after the HTML is created
    ['editName', 'editType', 'editArray', 'editSpace'].forEach(id => {
        document.getElementById(id).addEventListener('input', checkChanges);
    });

    // Initial check
    checkChanges();
}

function checkChanges() {
    const currentValues = {
        name: document.getElementById('editName').value,
        type: document.getElementById('editType').value,
        array: document.getElementById('editArray').checked
    };

    const hasChanges = 
        currentValues.name !== originalOptionDef.name ||
        currentValues.type !== originalOptionDef.type ||
        currentValues.array !== originalOptionDef.array;

    const saveButton = document.getElementById('editSubmitButton');
    saveButton.disabled = !hasChanges;
    saveButton.classList.toggle('opacity-50', !hasChanges);
    saveButton.classList.toggle('cursor-not-allowed', !hasChanges);
}

function closeEditDefModal() {
    document.getElementById('editDefModal').classList.add('hidden');
    originalOptionDef = null; // Clear the stored original values
}


// Form Handling Functions
async function handleCreateSubmit(event) {
     // Ensure we prevent the default form submission first
    if (event) {
        event.preventDefault();
    }

    if (!validateForm()) {
        return;
    }
    
    console.log('handleCreateSubmit called', {
        event: event,
        eventType: event?.type,
        target: event?.target,
        currentTarget: event?.currentTarget
    });
    
    // Get form values
    const code = document.getElementById('code').value;
    const name = document.getElementById('name').value;
    const type = document.getElementById('type').value;
    const array = document.getElementById('array').checked;
    const space = document.getElementById('space').value;

    // Validate all fields
    if (!validateOptionCode(document.getElementById('code')) ||
        !validateName(document.getElementById('name')) ||
        !validateType(document.getElementById('type')) ||
        !validateSpace(document.getElementById('space'))) {
        return;
    }

    const formData = {
        code: parseInt(code),
        name: name,
        type: type,
        array: array,
        space: space
    };

    try {
        const response = await fetch('/api/dhcp/optionsdef', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        
        if (result.success) {
            await loadOptionDefs(); // Reload the table
            closeCreateDefModal();
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Option definition created successfully',
                customClass: {
                    popup: 'bg-white rounded-lg shadow-xl',
                    title: 'text-gray-800 text-xl font-bold',
                    content: 'text-gray-600',
                    confirmButton: 'bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
                }
            });
        } else {
            throw new Error(result.error || 'Failed to create option definition');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to create option definition: ' + error.message,
            customClass: {
                popup: 'bg-white rounded-lg shadow-xl',
                title: 'text-gray-800 text-xl font-bold',
                content: 'text-gray-600',
                confirmButton: 'bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
            }
        });
    }
}

async function handleEditSubmit(event) {
    event.preventDefault();
    
    const code = document.getElementById('editCode').value;
    const name = document.getElementById('editName').value;
    const type = document.getElementById('editType').value;
    const array = document.getElementById('editArray').checked;
    const space = document.getElementById('editSpace').value;

    // Validate all fields
    if (!validateEditForm()) {
        return;
    }

    const formData = {
        code: parseInt(code),  // Make sure code is included and parsed as integer
        name: name,
        type: type,
        array: array,
        space: space
    };

    console.log('Sending update data:', formData); // Debug log

    try {
        const response = await fetch(`/api/dhcp/optionsdef/${formData.code}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)  // Make sure code is in the body
        });

        const responseText = await response.text();
        console.log('Response:', responseText);  // Debug log

        if (!response.ok) {
            const errorData = JSON.parse(responseText);
            throw new Error(errorData.error || 'Failed to update option definition');
        }

        const result = JSON.parse(responseText);
        
        if (result.success) {
            await loadOptionDefs();
            closeEditDefModal();
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Option definition updated successfully',
                customClass: {
                    popup: 'bg-white rounded-lg shadow-xl',
                    title: 'text-gray-800 text-xl font-bold',
                    content: 'text-gray-600',
                    confirmButton: 'bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
                }
            });
        } else {
            throw new Error(result.error || 'Failed to update option definition');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message,
            customClass: {
                popup: 'bg-white rounded-lg shadow-xl',
                title: 'text-gray-800 text-xl font-bold',
                content: 'text-gray-600',
                confirmButton: 'bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
            }
        });
    }
}


function validateForm() {
    let isValid = true;
    
    const nameInput = document.getElementById('name');
    const typeInput = document.getElementById('type');
    const spaceInput = document.getElementById('space');

    // Use existing validation functions
    if (!validateName(nameInput)) isValid = false;
    if (!validateType(typeInput)) isValid = false;
    if (!validateSpace(spaceInput)) isValid = false;

    return isValid;
}


function validateEditForm() {
    let isValid = true;
    
    const nameInput = document.getElementById('editName');
    const typeInput = document.getElementById('editType');
    const spaceInput = document.getElementById('editSpace');

    // Use existing validation functions
    if (!validateName(nameInput)) isValid = false;
    if (!validateType(typeInput)) isValid = false;
    if (!validateSpace(spaceInput)) isValid = false;

    return isValid;
}

// CRUD Operations
async function loadOptionDefs() {
    try {
        const response = await fetch('/api/dhcp/optionsdef', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get the raw response text first for debugging
        const rawText = await response.text();
        console.log('Raw Response Text:', rawText);

        // Check if response is empty
        if (!rawText) {
            throw new Error('Empty response received');
        }

        // Try parsing with more detailed error handling
        let data;
        try {
            data = JSON.parse(rawText);
            console.log('Successfully parsed JSON data:', data);
            console.log('Data structure:', {
                isArray: Array.isArray(data),
                hasFirstElement: data[0] ? true : false,
                hasArguments: data[0]?.arguments ? true : false,
                hasOptionDefs: data[0]?.arguments?.['option-defs'] ? true : false
            });
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            throw new Error('Failed to parse JSON response');
        }

        // Validate the response structure
        if (!Array.isArray(data)) {
            throw new Error('Response is not an array');
        }

        const tbody = document.querySelector('#optionDefsTable tbody');
        tbody.innerHTML = '';

        // If there's no data, display a message in the table
        if (data.length === 0 || !data[0] || !data[0].arguments || !data[0].arguments['option-defs']) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                    No option definitions found
                </td>
            `;
            tbody.appendChild(row);
            return;
        }

        const optionDefs = data[0].arguments['option-defs'];
        console.log('Option Definitions:', optionDefs);

        optionDefs.forEach(def => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${def.code}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${def.name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${def.type}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${def.space}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${def.array ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Yes</span>' 
                              : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">No</span>'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick='openEditDefModal(${JSON.stringify(def)})' class="text-indigo-600 hover:text-indigo-900 mr-3">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button 
                    onclick="deleteOptionDef(${def.code}, '${def.space}', '${def.name}')" class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });

    } catch (error) {
        console.error('Detailed Error:', {
            message: error.message,
            stack: error.stack
        });
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load option definitions: ' + error.message,
            customClass: {
                popup: 'bg-white rounded-lg shadow-xl',
                title: 'text-gray-800 text-xl font-bold',
                content: 'text-gray-600',
                confirmButton: 'bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
            }
        });
    }
}


async function deleteOptionDef(code, space, name) {
    console.log('Code:', code);
    console.log('Space:', space);
    console.log('Name:', name);

    Swal.fire({
        title: 'Delete DHCPv6 Option Definition?',
        html: `
            <div class="text-left">
                <p class="mb-2">You are about to delete:</p>
                <div class="bg-gray-50 p-3 rounded-md mb-4">
                    <p><span class="font-semibold">Name:</span> ${name}</p>
                    <p><span class="font-semibold">Code:</span> ${code}</p>
                    <p><span class="font-semibold">Space:</span> ${space}</p>
                </div>
                <p class="text-red-600">This will delete both the DHCPv6 Option and its Definition. This action cannot be undone!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete both!',
        customClass: {
            popup: 'bg-white rounded-lg shadow-xl',
            title: 'text-gray-800 text-xl font-bold',
            content: 'text-gray-600',
            htmlContainer: 'text-left',
            confirmButton: 'bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline',
            cancelButton: 'bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                // First try to delete the DHCPv6 Option
                const optionResponse = await fetch(`/api/dhcp/options/${code}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ space: space })
                });

                // Even if the option deletion fails, continue with definition deletion
                if (!optionResponse.ok) {
                    console.warn('DHCPv6 Option might not exist or failed to delete');
                }

                // Then delete the Option Definition
                const defResponse = await fetch(`/api/dhcp/optionsdef/${code}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ space: space })
                });

                if (!defResponse.ok) {
                    throw new Error('Network response was not ok');
                }

                const result = await defResponse.json();
                
                if (result.success) {
                    await loadOptionDefs();
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted Successfully',
                        html: `
                            <div class="text-left">
                                <p class="mb-2">The following items have been deleted:</p>
                                <div class="bg-gray-50 p-3 rounded-md">
                                    <p><span class="font-semibold">Name:</span> ${name}</p>
                                    <p><span class="font-semibold">Code:</span> ${code}</p>
                                    <p><span class="font-semibold">Space:</span> ${space}</p>
                                </div>
                            </div>
                        `,
                        customClass: {
                            popup: 'bg-white rounded-lg shadow-xl',
                            title: 'text-gray-800 text-xl font-bold',
                            content: 'text-gray-600',
                            htmlContainer: 'text-left',
                            confirmButton: 'bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
                        }
                    });
                } else {
                    throw new Error(result.error || 'Failed to delete option definition');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to delete option definition: ' + error.message,
                    customClass: {
                        popup: 'bg-white rounded-lg shadow-xl',
                        title: 'text-gray-800 text-xl font-bold',
                        content: 'text-gray-600',
                        confirmButton: 'bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline'
                    }
                });
            }
        }
    });
}



</script>


<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>
