<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Models\CinSwitch;
use App\Models\DHCP;


// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$error = null;
$switches = [];

// Set active navigation item
$currentPage = 'DHCP';
$subPage = 'subnets';

// Page title
$pageTitle = 'DHCP Subnets Configuration';

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $subnetModel = new DHCP($db); 
    $switches = $cinSwitch->getAllSwitches();
    
    // Add DHCP subnets
    $dhcp = new DHCP($db);
    error_log("====== DHCP View: About to call DHCPModel->getEnrichedSubnets() ======");
    $subnets = $subnetModel->getEnrichedSubnets();
    error_log("getEnrichedSubnets called, result: " . json_encode($subnets));
    error_log("====== DHCP View: Returned from DHCPModel->getEnrichedSubnets() ======");



    // Create an expanded list of switches with their BVI interfaces
    $expandedSwitches = [];
    foreach ($switches as $switch) {
        $bviInterfaces = $cinSwitch->getBVIInterfaces($switch['id']);

        if (!empty($bviInterfaces)) {
            foreach ($bviInterfaces as $bvi) {
                $expandedSwitch = $switch;
                $expandedSwitch['switch_id'] = $switch['id'] ?? '';
                $expandedSwitch['bvi_interface'] = $bvi['interface_number'] ?? '';
                $expandedSwitch['bvi_interface_id'] = $bvi['id'] ?? '';
                $expandedSwitch['ipv6_address'] = $bvi['ipv6_address'] ?? '';

                $matchingSubnet = array_filter($subnets, function($subnet) use ($bvi) {
                    return isset($subnet['bvi_interface_id']) && 
                           isset($bvi['id']) && 
                           $subnet['bvi_interface_id'] == $bvi['id'];
                });
                $expandedSwitch['subnet'] = !empty($matchingSubnet) ? reset($matchingSubnet) : null;
                $expandedSwitches[] = $expandedSwitch;
            }

            } else {
                $expandedSwitch = $switch;
                $expandedSwitch['switch_id'] = '';
                $expandedSwitch['bvi_interface'] = '';
                $expandedSwitch['bvi_interface_id'] = '';
                $expandedSwitch['ipv6_address'] = '';
                $expandedSwitch['subnet'] = '';
                $expandedSwitches[] = $expandedSwitch;
            }
       }
    
    $switches = $expandedSwitches;
    error_log('Expanded switches: ' . json_encode($switches));
} catch (\Exception $e) {
    $error = $e->getMessage();
}

// Start output buffering
ob_start();

// Include the DCHP menu
require BASE_PATH . '/views/dhcp-menu.php';



?>



<div class="container mx-auto px-4 py-6">
    <div class="mb-4">
        <input type="text" 
               id="searchInput" 
               placeholder="Search by hostname, BVI, IPv6 or subnet..." 
               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
               onkeyup="performSearch(this.value)">
    </div>    


    <!-- Main Table -->
<!-- Main Table -->
<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">DHCP Subnet Configuration</h1>
        <p class="mt-2 text-sm text-gray-600">Configure and manage DHCP subnets for switches and interfaces</p>
    </div>

    <!-- Table Container -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Switch Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BVI Interface</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IPv6 Address</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DHCP Subnet</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pool Range</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CCAP Core</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($switches as $switch): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($switch['hostname'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= !empty($switch['bvi_interface']) ? 'BVI' . (100 + intval($switch['bvi_interface'])) : '' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($switch['ipv6_address'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                    <?= htmlspecialchars($switch['subnet']['subnet']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not Configured</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-normal text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet']) && isset($switch['subnet']['pool'])): ?>
                                    <div class="flex flex-col">
                                        <span class="mb-1"><?= htmlspecialchars($switch['subnet']['pool']['start']) ?></span>
                                        <span><?= htmlspecialchars($switch['subnet']['pool']['end']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">Not configured</span>
                                <?php endif; ?>
                            </td>


                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                    <?= htmlspecialchars($switch['subnet']['ccap_core'] ?? 'Not set') ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not configured</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                    <button onclick="showEditSubnetModal('<?= htmlspecialchars(json_encode($switch['subnet']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($switch['ipv6_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                                        Edit
                                    </button>
                                    <button onclick="deleteSubnet('<?= $switch['subnet']['id'] ?>', '<?= htmlspecialchars(json_encode($switch['subnet']['subnet']), ENT_QUOTES, 'UTF-8') ?>')"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Delete
                                    </button>
                                <?php else: ?>
                                    <button onclick="showCreateSubnetModal('<?= htmlspecialchars($switch['switch_id'] ?? '') ?>','<?= htmlspecialchars($switch['bvi_interface'] ?? '') ?>', '<?= htmlspecialchars($switch['ipv6_address'] ?? '') ?>', '<?= htmlspecialchars($switch['bvi_interface_id'] ?? '') ?>')" 
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Configure
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Create Modal -->
<div id="createSubnetModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-4">Create New DHCPv6 Subnet</h3>
            <form id="createSubnetForm" class="mt-2">
                <input type="hidden" id="create_switch_id" name="switch_id">
                <input type="hidden" id="create_subnet_id" name="subnet_id">
                <input type="hidden" id="create_interface" name="interface">
                <input type="hidden" id="create_interface_id" name="interface_id">


                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_subnet">
                            Subnet Prefix
                        </label>
                        <input type="text" id="create_subnet" name="subnet" required 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            onchange="validateIPv6Address(this)">
                        <span id="create_subnetError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_mask">
                            Prefix Length
                        </label>
                        <input type="text" id="create_mask" name="mask" value="64" readonly
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_pool_start">
                            Pool Start
                        </label>
                        <input type="text" id="create_pool_start" name="pool_start" readonly disabled
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                        <span id="create_pool_startError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_pool_end">
                            Pool End
                        </label>
                        <input type="text" id="create_pool_end" name="pool_end" readonly disabled
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                        <span id="create_pool_endError" class="text-red-500 text-xs hidden"></span>
                    </div>


                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_ccap_core_address">
                            CCAP Core Address
                        </label>
                        <input type="text" id="create_ccap_core_address" name="ccap_core_address" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               onchange="validateIPv6Address(this)">
                        <span id="create_ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_relay_address">
                            Relay Address
                        </label>
                        <input type="text" id="create_relay_address" name="relay_address" required readonly
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                            onclick="document.getElementById('createSubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Create Subnet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="rounded-md shadow-sm -space-y-px">
<!-- Edit Modal -->
<div id="editSubnetModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <span class="close cursor-pointer text-gray-500 hover:text-gray-700" onclick="closeModal('editSubnetModal')">&times;</span>
        <h2 class="text-xl font-semibold mb-4">Edit CCAP Core Address</h2>
        <form id="editForm" class="space-y-4">
            <div class="form-group">
                <label for="dhcpPrefix" class="block text-sm font-medium text-gray-700">DHCP Prefix:</label>
                <input type="text" id="dhcpPrefix" name="dhcpPrefix" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" readonly>
            </div>
            <div class="form-group">
                <label for="subnetPool" class="block text-sm font-medium text-gray-700">Subnet Pool:</label>
                <input type="text" id="subnetPool" name="subnetPool" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" readonly>
            </div>
            <div class="form-group">
                <label for="edit_ccap_core_address" class="block text-sm font-medium text-gray-700">CCAP Core Address:</label>
                <input type="text" id="edit_ccap_core_address" name="ccap_core_address" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" required>
                <p id="ccap_core_address_error" class="text-red-500 text-sm hidden">Invalid IPv6 address</p>
            </div>
            <input type="hidden" id="edit_subnet_id" name="subnet_id">
            <input type="hidden" id="edit_switch_id" name="switch_id">
            <input type="hidden" id="edit_interface" name="interface">
            <input type="hidden" id="edit_interface_id" name="interface_id">
            <input type="hidden" id="edit_subnet" name="subnet">
            <input type="hidden" id="edit_pool_start" name="pool_start">
            <input type="hidden" id="edit_pool_end" name="pool_end">
            <div class="flex justify-end">
                <button type="submit" id="saveChangesButton" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 disabled:opacity-50" disabled>Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>

let originalFormData = {};

function checkFormChanges() {
    console.debug('Checking form changes'); // Debug
    const ccapCoreField = document.getElementById('edit_ccap_core_address');
    const saveButton = document.getElementById('editSubnetSaveButton');
    
    if (ccapCoreField && saveButton) {
        const currentValue = ccapCoreField.value;
        const isOriginalValue = currentValue === originalFormData.ccap_core_address;
        
        console.debug('Current value:', currentValue); // Debug
        console.debug('Original value:', originalFormData.ccap_core_address); // Debug
        console.debug('Is original value:', isOriginalValue); // Debug
        
        // Disable button if it's the original value or invalid IPv6
        saveButton.disabled = isOriginalValue || !validateIPv6Address(ccapCoreField);
    }
}



document.addEventListener('DOMContentLoaded', function() {
    // Helper Functions
    function setInputValueWithoutValidation(elementId, value, readonly = false) {
        const element = document.getElementById(elementId);
        if (element) {
            element.value = value || '';
            if (readonly) {
                element.setAttribute('readonly', 'readonly');
                element.classList.add('bg-gray-100');
            } else {
                element.removeAttribute('readonly');
                element.classList.remove('bg-gray-100');
            }
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Validation Functions
    function validateIPv6Address(input) {
        if (!input.value) {
            const errorSpan = document.getElementById(input.id + 'Error');
            if (errorSpan) {
                errorSpan.classList.add('hidden');
            }
            input.classList.remove('border-red-500');
            return true;
        }

        const errorSpan = document.getElementById(input.id + 'Error');
        if (!errorSpan) return true;

        const ipv6Regex = /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;

        const isValid = ipv6Regex.test(input.value);
        
        if (!isValid) {
            errorSpan.textContent = 'Please enter a valid IPv6 address';
            errorSpan.classList.remove('hidden');
            input.classList.add('border-red-500');
            return false;
        } else {
            errorSpan.classList.add('hidden');
            input.classList.remove('border-red-500');
            return true;
        }
    }

    // Create debounced version of validation
    const debouncedValidation = debounce((input) => {
        validateIPv6Address(input);
    }, 300);

    // Modal Functions
    function showCreateSubnetModal(Switchid = null, bviInterface = null, ipv6Address = null, bviInterfaceId = null) {
        const modal = document.getElementById('createSubnetModal');
        if (!modal) return;

        // Reset form and clear any previous error states
        const errorSpans = modal.querySelectorAll('[id$="Error"]');
        errorSpans.forEach(span => span.classList.add('hidden'));
        
        const inputFields = modal.querySelectorAll('input');
        inputFields.forEach(input => input.classList.remove('border-red-500'));

        // Set initial values without triggering validation
        setInputValueWithoutValidation('create_switch_id', Switchid || '' );
        setInputValueWithoutValidation('create_interface', bviInterface || '');
        setInputValueWithoutValidation('create_interface_id', bviInterfaceId || '');

        if (ipv6Address) {
            const prefix = ipv6Address.split('::')[0];
            if (prefix) {
                setInputValueWithoutValidation('create_subnet', prefix + '::', true);
                setInputValueWithoutValidation('create_relay_address', ipv6Address, true);
                setInputValueWithoutValidation('create_pool_start', prefix + '::2');
                setInputValueWithoutValidation('create_pool_end', prefix + '::fffe');
            }
        }

        
        modal.classList.remove('hidden');
    }

    async function handleEditSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Validate CCAP Core IPv6 address before submission
    const ccapCore = formData.get('ccap_core_address');
    if (!validateIPv6Address(ccapCore)) {
        await Swal.fire({
            title: 'Validation Error!',
            text: 'Please enter a valid IPv6 address for CCAP Core',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    // Get the subnet ID from the hidden input
    const subnetId = formData.get('subnet_id');

    // Create the data object with all required fields
    const data = {
        id: subnetId,
        subnet: formData.get('subnet'),
        pool: {
            start: formData.get('pool_start'),
            end: formData.get('pool_end')
        },
        bvi_interface_id: formData.get('interface_id'),
        ccap_core: ccapCore,
        relay_address: formData.get('relay_address')
    };

    try {
        const response = await fetch(`/api/subnets/${subnetId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        console.log('Success:', result);

        await Swal.fire({
            title: 'Success!',
            text: 'Subnet updated successfully',
            icon: 'success',
            confirmButtonColor: '#3085d6'
        });

        closeModal('editSubnetModal');
        await loadSubnets();
    } catch (error) {
        console.error('Error:', error);
        await Swal.fire({
            title: 'Error!',
            text: 'Failed to update subnet',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }
}

function showEditSubnetModal(subnetData, relay) {


    
  console.log('Modal data:', subnetData);

  const modal = document.getElementById('editSubnetModal');
    if (!modal) return;

    try {
        const subnet = typeof subnetData === 'string' ? JSON.parse(subnetData) : subnetData;


        // Reset form and clear any previous error states
        const errorSpans = modal.querySelectorAll('[id$="Error"]');
        errorSpans.forEach(span => span.classList.add('hidden'));
        
        const inputFields = modal.querySelectorAll('input');
        inputFields.forEach(input => input.classList.remove('border-red-500'));

        // Update modal content with smaller width
        const modalContent = `
            <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white">
                <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-4">Edit DHCPv6 Subnet</h3>
                <div class="mb-4 text-center">
                    <p class="text-gray-600">DHCP Prefix: <span class="font-semibold">${subnet.subnet}</span></p>
                    <p class="text-gray-600 mt-2">Subnet Pool: <span class="font-semibold">${subnet.pool.start} - ${subnet.pool.end}</span></p>
                </div>
                <form id="editSubnetForm">
                    <input type="hidden" id="edit_subnet_id" name="subnet_id">
                    <input type="hidden" id="edit_interface" name="interface">
                    <input type="hidden" id="edit_interface_id" name="interface_id">
                    <input type="hidden" id="edit_switch_id" name="switch_id">
                    <input type="hidden" id="edit_subnet" name="subnet">
                    <input type="hidden" id="edit_pool_start" name="pool_start">
                    <input type="hidden" id="edit_pool_end" name="pool_end">
                    <input type="hidden" id="edit_relay_address" name="relay_address">



                    <div class="mb-4">
                        <label for="edit_ccap_core_address" class="block text-gray-700 text-sm font-bold mb-2">CCAP Core Address</label>
                        <input type="text" 
                               id="edit_ccap_core_address" 
                               name="ccap_core_address" 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               oninput="validateIPv6Address(this)">
                        <span id="edit_ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mt-4 flex justify-end">
                    <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                            onclick="document.getElementById('editSubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                        <button type="submit" 
                                id="editSubnetSaveButton" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                disabled>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>`;

        modal.innerHTML = modalContent;

        // Set form values
        setInputValueWithoutValidation('edit_subnet_id', subnet.id);
        setInputValueWithoutValidation('edit_switch_id', subnet.switch_id);
        setInputValueWithoutValidation('edit_interface_id', subnet.bvi_interface_id);
        setInputValueWithoutValidation('edit_interface', subnet.bvi_interface);
        setInputValueWithoutValidation('edit_subnet', subnet.subnet, true);
        setInputValueWithoutValidation('edit_pool_start', subnet.pool.start, true);
        setInputValueWithoutValidation('edit_pool_end', subnet.pool.end, true);
        setInputValueWithoutValidation('edit_ccap_core_address', subnet.ccap_core);
        setInputValueWithoutValidation('edit_relay_address', relay, true);

        // Set original form data
        originalFormData = {
            ccap_core_address: subnet.ccap_core
        };

        const saveButton = document.getElementById('editSubnetSaveButton');
        if (saveButton) {
            saveButton.disabled = true;
        }

        // Add event listeners
        const ccapCoreField = document.getElementById('edit_ccap_core_address');
        if (ccapCoreField) {
            console.debug('Setting up CCAP Core field listeners'); 
            ccapCoreField.addEventListener('input', function(e) {
                validateIPv6Address(this);  
                checkFormChanges();   
            });    
            ccapCoreField.addEventListener('keyup', function(e) {
                validateIPv6Address(this);
                checkFormChanges();
            });
        }




        const form = document.getElementById('editSubnetForm');
        if (form) {
            form.addEventListener('submit', handleEditSubmit);
        }

        modal.classList.remove('hidden');
    } catch (error) {
        console.error('Error parsing subnet data:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Failed to load subnet data',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }
}


    // Form Handlers
    async function handleCreateSubmit(e) {
        e.preventDefault();

        // Validate all IPv6 addresses before submitting
        const ipv6Fields = ['create_subnet', 'create_pool_start', 'create_pool_end', 'create_ccap_core_address', 'relay'];
        let isValid = true;

        ipv6Fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !validateIPv6Address(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please correct the IPv6 addresses before submitting.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const subnet = document.getElementById('create_subnet').value + '/64';
            try {
                const duplicateCheck = await checkDuplicateSubnet(subnet);
                if (duplicateCheck.exists) {
                    Swal.fire({
                        title: 'Duplicate Subnet!',
                        text: 'This subnet already exists in the database.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to check for duplicate subnet.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

        const formData = {
            switch_id: document.getElementById('create_switch_id').value,
            bvi_interface: document.getElementById('create_interface').value,
            bvi_interface_id: document.getElementById('create_interface_id').value,
            subnet: document.getElementById('create_subnet').value + '/64',
            pool_start: document.getElementById('create_pool_start').value,
            pool_end: document.getElementById('create_pool_end').value,
            ccap_core_address: document.getElementById('create_ccap_core_address').value,
            relay_address: document.getElementById('create_relay_address').value,
            ipv6_address: document.getElementById('create_relay_address').value

            
        };

        const confirmHtml = `
            <div class="text-left">
                <p class="font-bold mb-2">Please confirm the following subnet configuration:</p>
                <p><span class="font-semibold">Subnet:</span> 
                    <span class="text-blue-600">${formData.subnet}</span></p>
                <p><span class="font-semibold">Pool Start:</span> 
                    <span class="text-blue-600">${formData.pool_start}</span></p>
                <p><span class="font-semibold">Pool End:</span> 
                    <span class="text-blue-600">${formData.pool_end}</span></p>
                <p><span class="font-semibold">CCAP Core Address:</span> 
                    <span class="text-blue-600">${formData.ccap_core_address}</span></p>
                <p><span class="font-semibold">Relay Address:</span> 
                    <span class="text-blue-600">${formData.relay_address}</span></p>
            </div>
        `;

        const result = await Swal.fire({
            title: 'Confirm Subnet Creation',
            html: confirmHtml,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, create subnet',
            cancelButtonText: 'Cancel',
            width: '600px'
        });

        if (result.isConfirmed) {
        try {
            const response = await fetch('/api/dhcp/subnets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                const modal = document.getElementById('createSubnetModal');
                if (modal) {
                    modal.classList.add('hidden');
                }

                // Show success message before reload
                await Swal.fire({
                    title: 'Success!',
                    text: 'Subnet has been created successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });

                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to save subnet configuration.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }
}

    async function handleEditSubmit(e) {
        e.preventDefault();
        
        // Validate all IPv6 addresses before submitting
        const ipv6Fields = ['edit_pool_start', 'edit_pool_end', 'edit_ccap_core_address'];
        let isValid = true;

        ipv6Fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !validateIPv6Address(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please correct the IPv6 addresses before submitting.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
    const subnet_id = document.getElementById('edit_subnet_id').value;
        console.log('edit_subnet_id element:', document.getElementById('edit_subnet_id'));
        console.log('edit_switch_id element:', document.getElementById('edit_switch_id'));
        console.log('edit_interface_id element:', document.getElementById('edit_interface_id'));
        console.log('edit_subnet:', document.getElementById('edit_subnet'));

        console.log(document.getElementById('edit_subnet').value);
        const formData = {
            subnet_id: subnet_id,
            switch_id: document.getElementById('edit_switch_id').value,
            subnet: document.getElementById('edit_subnet').value,
            bvi_interface_id: document.getElementById('edit_interface_id').value,
            bvi_interface: document.getElementById('edit_interface').value,
            pool_start: document.getElementById('edit_pool_start').value,
            pool_end: document.getElementById('edit_pool_end').value,
            ccap_core_address: document.getElementById('edit_ccap_core_address').value,
            relay_address: document.getElementById('edit_relay_address').value,
            ipv6_address: document.getElementById('edit_relay_address').value
        };

        console.log('Sending data:', formData); // Add this debug log

        // Create changes summary
        let changesHtml = '<div class="text-left">';
        if (formData.ccap_core_address !== originalFormData.ccap_core_address) {
            changesHtml += `<p>CCAP Core Address: <br>
                <span class="text-red-500">${originalFormData.ccap_core_address}</span> → 
                <span class="text-green-500">${formData.ccap_core_address}</span></p>`;
        }
        changesHtml += '</div>';

        // Add confirmation dialog with changes
        const result = await Swal.fire({
            title: 'Review Changes',
            html: changesHtml,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, save changes',
            cancelButtonText: 'Cancel',
            width: '600px'
        });

        if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/dhcp/subnets/${formData.subnet_id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                const modal = document.getElementById('editSubnetModal');
                if (modal) {
                    modal.classList.add('hidden');
                }
                
                // Show success message before reload
                await Swal.fire({
                    title: 'Success!',
                    text: 'Changes have been saved successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });
                
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to update subnet configuration.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }
}


    async function checkDuplicateSubnet(subnet, subnetId = null) {
    const response = await fetch('/api/dhcp/subnets/check-duplicate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            subnet: subnet,
            subnet_id: subnetId
        })
    });
    return await response.json();
}

    
    // Delete Function
    async function deleteSubnet(subnetId, subnetData) {
        try {
            const subnet = typeof subnetData === 'string' ? JSON.parse(subnetData) : subnetData;
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete the subnet ${subnet}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            });

            if (result.isConfirmed) {
                const response = await fetch(`/api/dhcp/subnets/${subnetId}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        title: 'Success!',
                        text: 'DHCP Subnet have been removed successfully.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to delete subnet.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }

    // Search Function
    function performSearch(searchTerm) {
        const rows = document.querySelectorAll('tbody tr');
        const searchTermLower = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTermLower) ? '' : 'none';
        });
    }

    // Event Listeners
    const ipv6Fields = ['create_subnet', 'create_pool_start', 'create_pool_end', 'create_ccap_core_address',
                       'edit_subnet', 'edit_pool_start', 'edit_pool_end', 'edit_ccap_core_address'];
    
    ipv6Fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', (e) => debouncedValidation(e.target));
        }
    });

    const createForm = document.getElementById('createSubnetForm');
    if (createForm) {
        createForm.addEventListener('submit', handleCreateSubmit);
    }

    const editForm = document.getElementById('editSubnetForm');
    if (editForm) {
        editForm.addEventListener('submit', handleEditSubmit);
    }

    // Make functions available globally
    window.validateIPv6Address = validateIPv6Address;
    window.showCreateSubnetModal = showCreateSubnetModal;
    window.showEditSubnetModal = showEditSubnetModal;
    window.handleCreateSubmit = handleCreateSubmit;
    window.handleEditSubmit = handleEditSubmit;
    window.deleteSubnet = deleteSubnet;
    window.performSearch = performSearch;
});
</script>

</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>