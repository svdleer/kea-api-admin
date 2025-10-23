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

// Page title
$pageTitle = 'DHCP Subnets Configuration';

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $switches = $cinSwitch->getAllSwitches();
    
    // Add DHCP subnets
    $dhcp = new DHCP($db);
    $subnets = $dhcp->getAllSubnets();
    
    // Create an expanded list of switches with their BVI interfaces
    $expandedSwitches = [];
    foreach ($switches as $switch) {
        $bviInterfaces = $cinSwitch->getBVIInterfaces($switch['id']);
        
        if (!empty($bviInterfaces)) {
            foreach ($bviInterfaces as $bvi) {
                $expandedSwitch = $switch;
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
                $expandedSwitch['bvi_interface'] = '';
                $expandedSwitch['bvi_interface_id'] = '';
                $expandedSwitch['ipv6_address'] = '';
                $expandedSwitch['subnet'] = null;
                $expandedSwitches[] = $expandedSwitch;
            }
       }
    
    $switches = $expandedSwitches;
} catch (\Exception $e) {
    $error = $e->getMessage();
}


ob_start();
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
    <div class="bg-white shadow-md rounded my-6">
        <table class="min-w-full table-auto">
            <thead>
<thead>
    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
        <th class="py-2 px-4 text-left">Switch Name</th>
        <th class="py-2 px-4 text-left">BVI Interface</th>
        <th class="py-2 px-4 text-left">IPv6 Address</th>
        <th class="py-2 px-4 text-left">DHCP Scope</th>
        <th class="py-2 px-4 text-left">Pool Range</th>
        <th class="py-2 px-4 text-left">CCAP Core</th>
        <th class="py-2 px-4 text-center">Actions</th>
    </tr>
</thead>

            </thead>
            <tbody class="text-gray-600 text-sm">
                <?php foreach ($switches as $switch): ?>
                   <tr class="border-b border-gray-200 hover:bg-gray-100 text-sm">
                       <td class="py-2 px-4 text-left"><?= htmlspecialchars($switch['hostname'] ?? '') ?></td>
                       <td class="py-2 px-4 text-left"><?= htmlspecialchars($switch['bvi_interface'] ?? '') ?></td>
                       <td class="py-2 px-4 text-left"><?= htmlspecialchars($switch['ipv6_address'] ?? '') ?></td>
                       <td class="py-2 px-4 text-left">
                           <?php if (isset($switch['subnet'])): ?>
                               <?= htmlspecialchars($switch['subnet']['subnet_prefix']) ?>
                           <?php else: ?>
                               Not Configured
                           <?php endif; ?>
                       </td>
                       <td class="py-2 px-4 text-left">
                           <?php if (isset($switch['subnet'])): ?>
                               <?= htmlspecialchars($switch['subnet']['pool_start'] . ' - ' . $switch['subnet']['pool_end']) ?>
                           <?php else: ?>
                               Not configured
                           <?php endif; ?>
                       </td>
                       <td class="py-2 px-4 text-left">
                           <?php if (isset($switch['subnet'])): ?>
                               <?= htmlspecialchars($switch['subnet']['ccap_core_address'] ?? 'Not set') ?>
                           <?php else: ?>
                               Not configured
                           <?php endif; ?>
                       </td>
                       <td class="py-2 px-4 text-center">
                           <?php if (isset($switch['subnet'])): ?>
                                <button onclick="showEditSubnetModal('<?= htmlspecialchars(json_encode($switch['subnet']), ENT_QUOTES, 'UTF-8') ?>')"
                                        class="bg-yellow-500 hover:bg-yellow-700 text-white text-sm font-bold py-1 px-2 rounded mr-1">
                                    Edit
                                </button>
                               <button onclick='deleteSubnet(<?= $switch['subnet']['subnet_id'] ?>, <?= htmlspecialchars(json_encode($switch['subnet']), ENT_QUOTES, 'UTF-8') ?>)' 
                                        class="bg-red-500 hover:bg-red-700 text-white text-sm font-bold py-1 px-2 rounded">
                                    Delete
                                </button>
                           <?php else: ?>
                               <button onclick="showCreateSubnetModal('<?= htmlspecialchars($switch['bvi_interface'] ?? '') ?>', '<?= htmlspecialchars($switch['ipv6_address'] ?? '') ?>', '<?= htmlspecialchars($switch['bvi_interface_id'] ?? '') ?>')" 
                                       class="bg-green-500 hover:bg-green-700 text-white text-sm font-bold py-1 px-2 rounded">
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

<!-- The Modal -->
<div id="subnetModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-4" id="modalTitle">Create New DHCPv6 Subnet</h3>
            <form id="editsubnetForm" class="mt-2">
                <input type="hidden" id="subnet_id" name="subnet_id">
                <input type="hidden" id="interface" name="interface">
                <input type="hidden" id="interface_id" name="interface_id">

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="subnet">
                            Subnet Prefix
                        </label>
                        <input type="text" id="subnet" name="subnet" required 
                            value=""
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            onchange="validateIPv6Address(this)">
                        <span id="subnetError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="mask">
                            Prefix Length
                        </label>
                        <input type="text" id="mask" name="mask" value="64" readonly
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="pool_start">
                            Pool Start
                        </label>
                        <input type="text" id="pool_start" name="pool_start" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               onchange="validateIPv6Address(this)">
                        <span id="pool_startError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="pool_end">
                            Pool End
                        </label>
                        <input type="text" id="pool_end" name="pool_end" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               onchange="validateIPv6Address(this)">
                        <span id="pool_endError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="ccap_core_address">
                            CCAP Core Address
                        </label>
                        <input type="text" id="ccap_core_address" name="ccap_core_address" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               onchange="validateIPv6Address(this)">
                        <span id="ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="relay_address">
                            Relay Address
                        </label>
                        <input type="text" id="relay_address" name="relay_address" required readonly
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                            <span id="relay_addressError" class="text-red-500 text-xs hidden"></span>
                        </div>

                    
                </div>
                <div class="flex items-center justify-end mt-6">
                <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded" onclick="document.getElementById('editsubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Save Subnet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subnet Modal -->

<div id="editsubnetModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-2" id="modalTitle">Edit DHCPv6 Subnet</h3>
            <div class="text-center mb-4 text-gray-600" id="ipv6PrefixDisplay"></div>
            
            <form id="editsubnetForm" class="mt-2">
                <!-- Hidden fields -->
                <input type="hidden" id="subnet_id" name="subnet_id">
                <input type="hidden" id="edit_interface_id" name="bvi_interface_id">
                <input type="hidden" id="interface" name="interface">
                <input type="hidden" id="subnet" name="subnet">
                <input type="hidden" id="relay_address" name="relay_address">

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="pool_start">
                            Pool Start
                        </label>
                        <input type="text" id="pool_start" name="pool_start" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-black bg-white leading-tight"
                               onchange="validateIPv6Address(this)">
                        <span id="pool_startError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="pool_end">
                            Pool End
                        </label>
                        <input type="text" id="pool_end" name="pool_end" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-black bg-white leading-tight"
                               onchange="validateIPv6Address(this)">
                        <span id="pool_endError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="ccap_core_address">
                            CCAP Core
                        </label>
                        <input type="text" id="ccap_core_address" name="ccap_core_address" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-black bg-white leading-tight"
                               onchange="validateIPv6Address(this)">
                        <span id="ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="button" 
                            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                            onclick="document.getElementById('editsubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>





<script>
let subnetCheckTimeout;


function validateIPv6Address(input) {
    const errorId = input.id + 'Error';
    const errorSpan = document.getElementById(errorId);
    
    // Comprehensive IPv6 regex for addresses
    const ipv6Regex = /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/i;

    // Prefix regex for subnet only
    const prefixRegex = /^([0-9a-fA-F]{1,4}:){1,7}:$/;
    
    let isValid = true;
    let errorMessage = '';

    if (input.id === 'subnet') {
        // Only use prefix regex for subnet field
        if (!prefixRegex.test(input.value)) {
            isValid = false;
            errorMessage = 'Invalid IPv6 prefix format';
        }
    } else {
        // Use full IPv6 regex for all other IPv6 fields (pool_start, pool_end, ccap_core_address)
        if (!ipv6Regex.test(input.value)) {
            isValid = false;
            errorMessage = 'Invalid IPv6 address format';
        }
        
        // Additional pool range check
        if (input.id === 'pool_end') {
            const poolStart = document.getElementById('pool_start').value;
            if (poolStart && !compareIPv6Addresses(poolStart, input.value)) {
                isValid = false;
                errorMessage = 'Pool end must be higher than pool start';
            }
        }
    }

    if (errorSpan) {
        errorSpan.textContent = errorMessage;
        errorSpan.classList.toggle('hidden', isValid);
    }
    return isValid;
}




function compareIPv6Addresses(start, end) {
    // Convert last part after :: to numbers for comparison
    const startNum = parseInt(start.split('::')[1], 16);
    const endNum = parseInt(end.split('::')[1], 16);
    return endNum > startNum;
}


function checkDuplicateSubnet(subnet, subnetId = null) {
    return fetch('/api/dhcp/subnets/check-duplicate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            subnet: subnet,
            subnet_id: subnetId
        })
    })
    .then(response => response.json());

}

function showCreateSubnetModal(bviInterface = null, ipv6Address = null, bviInterfaceId = null) {
    const modal = document.getElementById('subnetModal');
    if (modal) {
        document.getElementById('subnet_id').value = '';
        document.getElementById('interface').value = bviInterface || '';
        document.getElementById('interface_id').value = bviInterfaceId || '';

                if (ipv6Address) {
            document.getElementById('subnet').value = ipv6Address;
        }
        

        modal.classList.remove('hidden');
    }
    
    // Helper function to safely set input values and readonly state
    const setInputValue = (id, value, readonly = false) => {
        const element = document.getElementById(id);
        if (element) {
            element.value = value || '';
            if (readonly) {
                element.setAttribute('readonly', 'readonly');
                element.classList.add('bg-gray-100'); // Visual indication that it's readonly
            } else {
                element.removeAttribute('readonly');
                element.classList.remove('bg-gray-100');
            }
        }
    };

    // Reset form with null checks
    setInputValue('subnet_id', '');
    setInputValue('interface', bviInterface);
    
    // Handle IPv6 address and subnet prefix
    if (ipv6Address) {
        const prefix = ipv6Address.split('::')[0];
        if (prefix) {
            setInputValue('subnet', prefix + '::', true); // Set as readonly
            setInputValue('relay_address', ipv6Address, true); // Set relay address as readonly
            
            // Prefill pool start and end with modified BVI address
            setInputValue('pool_start', prefix + '::2');
            setInputValue('pool_end', prefix + '::fffe');
        }
    }
    
    setInputValue('ccap_core_address', '');
    
    const titleElement = document.getElementById('modalTitle');
    if (titleElement) {
        titleElement.textContent = 'Create New DHCPv6 Subnet';
    }
}

function showEditSubnetModal(subnetData) {
    console.log("Received subnet data:", subnetData);
    
    if (typeof subnetData === 'string') {
        try {
            subnetData = JSON.parse(subnetData);
        } catch (e) {
            console.error('Error parsing subnet data:', e);
            return;
        }
    }
    
    const modal = document.getElementById('editsubnetModal');
    if (!modal) {
        console.error('Modal element not found!');
        return;
    }

    try {
        // Display IPv6 prefix in the header
        document.getElementById('ipv6PrefixDisplay').textContent = subnetData.subnet_prefix + '/64';
        
        // Directly set values and log each operation
        const poolStart = document.getElementById('pool_start');
        if (poolStart) {
            poolStart.value = subnetData.pool_start || '';
            console.log('Set pool_start to:', poolStart.value);
        } else {
            console.error('pool_start element not found');
        }

        const poolEnd = document.getElementById('pool_end');
        if (poolEnd) {
            poolEnd.value = subnetData.pool_end || '';
            console.log('Set pool_end to:', poolEnd.value);
        } else {
            console.error('pool_end element not found');
        }

        const ccapCore = document.getElementById('ccap_core_address');
        if (ccapCore) {
            ccapCore.value = subnetData.ccap_core_address || '';
            console.log('Set ccap_core_address to:', ccapCore.value);
        } else {
            console.error('ccap_core_address element not found');
        }

        // Set hidden fields
        document.getElementById('subnet_id').value = subnetData.subnet_id || '';
        document.getElementById('edit_interface_id').value = subnetData.bvi_interface_id || '';
        document.getElementById('subnet').value = subnetData.subnet_prefix || '';
        document.getElementById('relay_address').value = subnetData.relay_address || '';



        // Show modal
        modal.classList.remove('hidden');
        
    } catch (error) {
        console.error("Error in showEditSubnetModal:", error);
        console.error("Error details:", error.message);
    }
}


function deleteSubnet(subnetId, subnetDetails) {
    Swal.fire({
        title: 'Delete Subnet Configuration',
        html: `
            Are you sure you want to delete this subnet?<br><br>
            <div class="text-left">
                <strong>Subnet:</strong> ${subnetDetails.subnet_prefix}<br>
                <strong>Pool Range:</strong> ${subnetDetails.pool_start} - ${subnetDetails.pool_end}<br>
                <strong>CCAP Core:</strong> ${subnetDetails.ccap_core_address || 'Not set'}
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Perform the delete AJAX request
            fetch(`/api/dhcp/subnets/${subnetId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'The subnet has been deleted.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Reload the page or update the table
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to delete the subnet.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An unexpected error occurred.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                console.error('Error:', error);
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('subnetForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();          
            
            // Debug logging to see what we're working with
            console.log('Form elements:', {
                interface_id: document.getElementById('interface_id'),
                interface: document.getElementById('interface')
            });

            // Get the interface ID with error checking
            const interfaceIdElement = document.getElementById('interface_id');
            if (!interfaceIdElement) {
                console.error('interface_id element not found');
                return;
            }

            const bviInterfaceId = interfaceIdElement.value;
            console.log('BVI Interface ID:', bviInterfaceId);

            if (!bviInterfaceId) {
                console.error('No BVI Interface ID value');
                return;
            }

            const formData = {
                subnet_id: document.getElementById('subnet_id')?.value || '',
                bvi_interface_id: bviInterfaceId,
                subnet: document.getElementById('subnet')?.value + '/64',
                pool_start: document.getElementById('pool_start')?.value,
                pool_end: document.getElementById('pool_end')?.value,
                ccap_core_address: document.getElementById('ccap_core_address')?.value,
                relay_address: document.getElementById('relay_address')?.value
            };

            console.log('Form Data being sent:', formData);


            // Clear any previous error states
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(el => el.remove());
            document.querySelectorAll('.border-red-500').forEach(el => {
                el.classList.remove('border-red-500');
            });

            try {
                const response = await fetch('/api/dhcp/subnets', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    // Hide modal
                    const modal = document.getElementById('subnetModal');
                    if (modal) {
                        modal.classList.add('hidden');
                    }

                    // Show success message with subnet details
                    await Swal.fire({
                        title: 'Subnet Created Successfully',
                        html: `
                            <div class="text-left">
                                <p><strong>Interface:</strong> ${formData.bvi_interface_id}</p>
                                <p><strong>Subnet:</strong> ${formData.subnet}</p>
                                <p><strong>Pool Start:</strong> ${formData.pool_start}</p>
                                <p><strong>Pool End:</strong> ${formData.pool_end}</p>
                                <p><strong>CCAP Core:</strong> ${formData.ccap_core_address}</p>
                                <p><strong>Relay Address:</strong> ${formData.relay_address}</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });

                    // Reload page after showing the success message
                    location.reload();
                } else {
                    // Show error message with details
                    let errorMessage = '<div class="text-left">';
                    if (data.details) {
                        // Handle validation errors
                        Object.keys(data.details).forEach(field => {
                            errorMessage += `<p><strong>${field}:</strong> ${data.details[field]}</p>`;
                            const input = document.getElementById(field);
                            if (input) {
                                input.classList.add('border-red-500');
                            }
                        });
                    } else {
                        errorMessage += `<p>${data.error || 'Unknown error'}</p>`;
                    }
                    errorMessage += '</div>';

                    await Swal.fire({
                        title: 'Error Creating Subnet',
                        html: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                
                await Swal.fire({
                    title: 'Error',
                    html: `
                        <div class="text-left">
                            <p><strong>Error Message:</strong> ${error.message}</p>
                            <p><strong>Subnet:</strong> ${formData.subnet}</p>
                        </div>
                    `,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            }
        });

    }
    const editForm = document.getElementById('editsubnetForm');
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            e.preventDefault();          
            
            const formData = {
                subnet_id: document.getElementById('edit_subnet_id')?.value || '',
                bvi_interface_id: document.getElementById('edit_interface_id')?.value,
                subnet: document.getElementById('edit_subnet')?.value + '/64',
                pool_start: document.getElementById('edit_pool_start')?.value,
                pool_end: document.getElementById('edit_pool_end')?.value,
                ccap_core_address: document.getElementById('edit_ccap_core')?.value,
                relay_address: document.getElementById('edit_relay_address')?.value
            };

            console.log('Edit Form Data being sent:', formData);

            // Clear any previous error states
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(el => el.remove());
            document.querySelectorAll('.border-red-500').forEach(el => {
                el.classList.remove('border-red-500');
            });

            try {
                const response = await fetch(`/api/dhcp/subnets/${formData.subnet_id}`, {
                    method: 'PUT', // Using PUT for updates
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    // Hide modal
                    const modal = document.getElementById('editsubnetModal');
                    if (modal) {
                        modal.classList.add('hidden');
                    }

                    // Show success message with subnet details
                    await Swal.fire({
                        title: 'Subnet Updated Successfully',
                        html: `
                            <div class="text-left">
                                <p><strong>Interface:</strong> ${formData.bvi_interface_id}</p>
                                <p><strong>Subnet:</strong> ${formData.subnet}</p>
                                <p><strong>Pool Start:</strong> ${formData.pool_start}</p>
                                <p><strong>Pool End:</strong> ${formData.pool_end}</p>
                                <p><strong>CCAP Core:</strong> ${formData.ccap_core_address}</p>
                                <p><strong>Relay Address:</strong> ${formData.relay_address}</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });

                    // Reload page after showing the success message
                    location.reload();
                } else {
                    // Show error message with details
                    let errorMessage = '<div class="text-left">';
                    if (data.details) {
                        // Handle validation errors
                        Object.keys(data.details).forEach(field => {
                            errorMessage += `<p><strong>${field}:</strong> ${data.details[field]}</p>`;
                            const input = document.getElementById(`edit_${field}`);
                            if (input) {
                                input.classList.add('border-red-500');
                            }
                        });
                    } else {
                        errorMessage += `<p>${data.error || 'Unknown error'}</p>`;
                    }
                    errorMessage += '</div>';

                    await Swal.fire({
                        title: 'Error Updating Subnet',
                        html: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                
                await Swal.fire({
                    title: 'Error',
                    html: `
                        <div class="text-left">
                            <p><strong>Error Message:</strong> ${error.message}</p>
                            <p><strong>Subnet:</strong> ${formData.subnet}</p>
                        </div>
                    `,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            }
        });

    } else {
        console.error('Form not found - subnetForm element is missing');
    }
})



function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}


function performSearch(query) {
    const rows = document.querySelectorAll('tbody tr');
    query = query.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

// Close modal when clicking outside
document.getElementById('subnetModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>