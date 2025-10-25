<?php
error_reporting(E_ALL);
// Remove or comment out the line to prevent displaying errors in production
// ini_set('display_errors', 1);

require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Models\CinSwitch;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$currentPage = 'switches';
$title = 'Add BVI Interface';

// Get switch ID from URL parameter
$switchId = isset($_GET['switchId']) ? $_GET['switchId'] : null;
if ($switchId === null || $switchId === '') {
    throw new Exception('Invalid switch ID');
}

// Get switch details
try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $switch = $cinSwitch->getSwitchById($switchId);
    
    if (!$switch) {
        throw new \Exception('Switch not found');
    }
} catch (\Exception $e) {
    $_SESSION['error'] = 'Switch not found';
    header('Location: /switches');
    return;
}

// Get next available BVI number
$nextBVINumber = $cinSwitch->getNextAvailableBVINumber($switchId);

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Add BVI Interface</h1>
        <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi" class="text-blue-500 hover:text-blue-700">
            ← Back to BVI Interfaces
        </a>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <div id="switchInfo" class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h2 class="text-lg font-semibold mb-2">Switch Information</h2>
            <p class="text-gray-600">Hostname: <span id="switchHostname" class="font-medium text-gray-900"><?php echo htmlspecialchars($switch['hostname']); ?></span></p>
        </div>

        <form id="addBviForm" class="space-y-6" novalidate>
            <input type="hidden" id="switchId" value="<?php echo htmlspecialchars($switchId); ?>">
            
            <div>
                <label for="interface_number" class="block text-lg font-semibold mb-2">
                    BVI Interface Number
                    <span class="text-sm font-normal text-gray-600 ml-1">(0 = BVI100, 1 = BVI101, etc.)</span>
                </label>
                <input type="number" 
                       id="interface_number" 
                       name="interface_number" 
                       min="0"
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       value="<?php echo htmlspecialchars($nextBVINumber); ?>"
                       placeholder="0"
                       required>
                <div class="validation-message mt-1 text-sm"></div>
            </div>

            <div>
                <label for="ipv6_address" class="block text-lg font-semibold mb-2">
                    IPv6 Address
                </label>
                <input type="text" 
                       id="ipv6_address" 
                       name="ipv6_address" 
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="2001:db8::1"
                       required>
                <div class="validation-message mt-1 text-sm"></div>
            </div>

            <div class="flex justify-end space-x-4 pt-6 border-t">
                <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi" 
                   class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" 
                        id="submitButton"
                        disabled
                        class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Add BVI Interface
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // DOM Elements
    const form = $('#addBviForm');
    const bviInput = $('#interface_number');
    const ipv6Input = $('#ipv6_address');
    const submitButton = $('#submitButton');
    const switchId = $('#switchId').val();
    let isFirstLoad = true;

    // Validation state
    let validations = {
        interface_number: true,
        ipv6: false
    };

    // Validation Functions
    function isValidBVI(bvi) {
        return /^BVI\d+$/.test(bvi);
    }

    function isValidIPv6(address) {
        return /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/i.test(address);
    }

    function updateValidationUI(input, isValid, message) {
        const validationMessage = input.siblings('.validation-message');
        validationMessage
            .removeClass('hidden')
            .text(message)
            .removeClass('text-red-500 text-green-500')
            .addClass(isValid ? 'text-green-500' : 'text-red-500');
    }

    function validateForm() {
        submitButton.prop('disabled', !(validations.interface_number && validations.ipv6));
    }

    // BVI Input Handler
    let bviCheckTimeout;
    bviInput.on('input', function() {
        const input = $(this);
        const interfaceNumber = parseInt(input.val());

        if (isNaN(interfaceNumber) || interfaceNumber < 0) {
            updateValidationUI(input, false, 'Interface number must be 0 or greater');
            validations.interface_number = false;
            validateForm();
            return;
        }

        clearTimeout(bviCheckTimeout);
        bviCheckTimeout = setTimeout(() => {
            fetch(`/api/switches/${switchId}/bvi/check-exists?interface_number=${encodeURIComponent(interfaceNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        updateValidationUI(input, false, 'This BVI interface already exists');
                        validations.interface_number = false;
                    } else {
                        updateValidationUI(input, true, `Valid - Will display as BVI${100 + interfaceNumber}`);
                        validations.interface_number = true;
                    }
                    validateForm();
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateValidationUI(input, false, 'Error checking BVI interface');
                    validations.interface_number = false;
                    validateForm();
                });
        }, 500);
    });

    // IPv6 Input Handler
    let ipv6CheckTimeout;
    ipv6Input.on('input', function() {
        const input = $(this);
        const ipv6Address = input.val();

        if (!isValidIPv6(ipv6Address)) {
            updateValidationUI(input, false, 'Invalid IPv6 address format');
            validations.ipv6 = false;
            validateForm();
            return;
        }

        clearTimeout(ipv6CheckTimeout);
        ipv6CheckTimeout = setTimeout(() => {
            fetch(`/api/switches/bvi/check-ipv6?ipv6=${encodeURIComponent(ipv6Address)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        updateValidationUI(input, false, 'This IPv6 address is already in use');
                        validations.ipv6 = false;
                    } else {
                        updateValidationUI(input, true, 'Valid IPv6 address');
                        validations.ipv6 = true;
                    }
                    validateForm();
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateValidationUI(input, false, 'Error checking IPv6 address');
                    validations.ipv6 = false;
                    validateForm();
                });
        }, 500);
    });

    // Form Submit Handler
    form.on('submit', function(e) {
        e.preventDefault();
        
        if (!validations.interface_number || !validations.ipv6) {
            return;
        }

        submitButton.prop('disabled', true);

        const formData = {
            interface_number: bviInput.val(),
            ipv6_address: ipv6Input.val()
        };

        fetch(`/api/switches/${switchId}/bvi`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                Swal.fire({
                    title: 'BVI Interface Created',
                    html: `
                        <div class="text-left">
                            <p><strong>Switch:</strong> ${$('#switchHostname').text()}</p>
                            <p><strong>BVI Interface:</strong> ${formData.interface_number}</p>
                            <p><strong>IPv6 Address:</strong> ${formData.ipv6_address}</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3B82F6' // blue-600 in Tailwind
                }).then((result) => {
                    // Redirect after user clicks OK
                    window.location.href = `/switches/${switchId}/bvi`;
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Error adding BVI interface',
                    icon: 'error',
                    confirmButtonColor: '#3B82F6'
                });
                submitButton.prop('disabled', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Error adding BVI interface',
                icon: 'error',
                confirmButtonColor: '#3B82F6'
            });
            submitButton.prop('disabled', false);
        });
    });

});
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
