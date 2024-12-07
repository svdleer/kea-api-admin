<?php
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

$error = null;
$success = null;
try {
    $db = Database::getInstance();
    
    // Get the switch ID and BVI ID from URL
    $switchId = isset($_GET['switchId']) ? $_GET['switchId'] : null;
    $bviId = isset($_GET['bviId']) ? $_GET['bviId'] : null;
    
    if (!$switchId || !$bviId) {
        throw new Exception('Invalid switch or BVI ID');
    }
    
    $cinSwitch = new CinSwitch($db);
    
    // Get switch data
    $switch = $cinSwitch->getSwitchById($switchId);
    if (!$switch) {
        header('Location: /switches');
        exit;
    }

    // Get BVI interface data
    $bvi = $cinSwitch->getBviInterface($switchId, $bviId);
    if (!$bvi) {
        header('Location: /switches/edit/' . $switchId);
        exit;
    }

} catch (\Exception $e) {
    error_log("Error in BVI edit.php: " . $e->getMessage());
    $error = $e->getMessage();
}

$currentPage = 'switches';
$title = 'Edit BVI Interface';

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Edit BVI Interface</h1>
            <a href="/switches/edit/<?php echo htmlspecialchars($switchId); ?>" 
               class="text-blue-500 hover:text-blue-700">
                ← Back to Switch
            </a>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <form id="editBviForm" class="space-y-6" method="POST">
                <input type="hidden" id="switchId" value="<?php echo htmlspecialchars($switchId); ?>">
                <input type="hidden" id="bviId" value="<?php echo htmlspecialchars($bviId); ?>">

                <div class="form-group">
                    <label for="interface_number" class="block text-sm font-medium text-gray-700 mb-2">
                        Interface Number
                        <span class="text-xs text-gray-500 ml-1">(Format: BVIxxx)</span>
                    </label>
                    <input type="text" 
                           id="interface_number" 
                           name="interface_number" 
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo htmlspecialchars($bvi['interface_number']); ?>"
                           required>
                    <div class="validation-message mt-1 text-sm hidden"></div>
                </div>

                <div class="form-group">
                    <label for="ipv6_address" class="block text-sm font-medium text-gray-700 mb-2">
                        IPv6 Address
                    </label>
                    <input type="text" 
                           id="ipv6_address" 
                           name="ipv6_address" 
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo htmlspecialchars($bvi['ipv6_address']); ?>"
                           required>
                    <div class="validation-message mt-1 text-sm hidden"></div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="/switches/edit/<?php echo htmlspecialchars($switchId); ?>" 
                       class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            id="submitButton" 
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        Update BVI Interface
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // DOM Elements
        const form = $('#editBviForm');
        const bviInput = $('#interface_number');
        const ipv6Input = $('#ipv6_address');
        const submitButton = $('#submitButton');
        const switchId = $('#switchId').val();
        const bviId = $('#bviId').val();
        const originalBVI = bviInput.val();
        const originalIPv6 = ipv6Input.val();
        let isFirstLoad = true;

        // Validation state
        let validations = {
            interface_number: true,
            ipv6: true
        };

        function isValidBVI(bvi) {
            return /^BVI\d+$/.test(bvi);
        }

        function isValidIPv6(address) {
            return /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/i.test(address);
        }

        function updateValidationUI(input, isValid, message) {
            const validationMessage = input.siblings('.validation-message');
            if (isFirstLoad) {
                validationMessage.removeClass('hidden').text('');
                return;
            }
            validationMessage
                .removeClass('hidden')
                .text(message)
                .removeClass('text-red-500 text-green-500')
                .addClass(isValid ? 'text-green-500' : 'text-red-500');
        }

        function validateForm() {
            if (isFirstLoad) {
                isFirstLoad = false;
                return;
            }
            submitButton.prop('disabled', !(validations.interface_number && validations.ipv6));
        }

        // BVI Input Handler
        let bviCheckTimeout;
        bviInput.on('input', function() {
            const input = $(this);
            const interfaceNumber = input.val().toUpperCase();
            input.val(interfaceNumber);

            if (!isValidBVI(interfaceNumber)) {
                updateValidationUI(input, false, 'Invalid format. Must be BVI followed by numbers (e.g., BVI100)');
                validations.interface_number = false;
                validateForm();
                return;
            }

            if (interfaceNumber === originalBVI) {
                updateValidationUI(input, true, 'Valid BVI interface');
                validations.interface_number = true;
                validateForm();
                return;
            }

            clearTimeout(bviCheckTimeout);
            bviCheckTimeout = setTimeout(() => {
                fetch(`/api/switches/${switchId}/bvi/check-exists?interface_number=${encodeURIComponent(interfaceNumber)}&exclude_id=${bviId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            updateValidationUI(input, false, 'This BVI interface already exists');
                            validations.interface_number = false;
                        } else {
                            updateValidationUI(input, true, 'Valid BVI interface');
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

            if (ipv6Address === originalIPv6) {
                updateValidationUI(input, true, 'Valid IPv6 address');
                validations.ipv6 = true;
                validateForm();
                return;
            }

            clearTimeout(ipv6CheckTimeout);
            ipv6CheckTimeout = setTimeout(() => {
                fetch(`/api/switches/check-ipv6?ipv6=${encodeURIComponent(ipv6Address)}`)
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

            fetch(`/api/switches/${switchId}/bvi/${bviId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `/switches/${switchId}/bvi`;
                } else {
                    alert(data.message || 'Error updating BVI interface');
                    submitButton.prop('disabled', false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                console.log('Response:', error.response);
                alert('Error updating BVI interface');
                submitButton.prop('disabled', false);
            });
        });

        // Initial validation
        if (originalBVI) {
            updateValidationUI(bviInput, true, 'Valid BVI interface');
        }
        if (originalIPv6) {
            updateValidationUI(ipv6Input, true, 'Valid IPv6 address');
        }
    });
    </script>



<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
