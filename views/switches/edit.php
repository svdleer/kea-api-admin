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
    
    // Get the switch ID from URL
    $switchId = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$switchId) {
        throw new Exception('Switch ID not provided');
    }
    
    $cinSwitch = new CinSwitch($db);
    
    // Get switch data
    $switch = $cinSwitch->getSwitchById($switchId);
    if (!$switch) {
        throw new Exception('Switch not found');
    }

    // Get BVI interfaces for this switch
    $bviInterfaces = $cinSwitch->getBviInterfaces($switchId);

} catch (\Exception $e) {
    error_log("Error in switch edit.php: " . $e->getMessage());
    $error = $e->getMessage();
}

$currentPage = 'switches';
$title = 'Edit Switch';

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
            <h1 class="text-2xl font-bold text-gray-800">Edit Switch</h1>
            <a href="/switches" class="text-blue-500 hover:text-blue-700">← Back to Switches</a>
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
            <form id="editSwitchForm" class="space-y-6">
                <input type="hidden" id="switchId" value="<?php echo htmlspecialchars($switchId); ?>">

                <div class="form-group">
                    <label for="hostname" class="block text-sm font-medium text-gray-700 mb-2">Hostname</label>
                    <input type="text" 
                           id="hostname" 
                           name="hostname" 
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo htmlspecialchars($switch['hostname']); ?>"
                           required>
                    <div class="validation-message mt-1 text-sm hidden"></div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="/switches" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            id="submitButton" 
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        Update Switch
                    </button>
                </div>
            </form>
        </div>

        <!-- BVI Interfaces Section -->
        <div class="mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">BVI Interfaces</h2>
                <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi/add" 
                   class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                    Add BVI Interface
                </a>
            </div>

            <?php if (empty($bviInterfaces)): ?>
                <p class="text-gray-600">No BVI interfaces found.</p>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Interface Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    IPv6 Address
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bviInterfaces as $bvi): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($bvi['interface_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($bvi['ipv6_address']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <a href="/switches/<?php echo htmlspecialchars($switchId); ?>/bvi/<?php echo htmlspecialchars($bvi['id']); ?>/edit" 
                                           class="text-blue-500 hover:text-blue-700 mr-4">
                                            Edit
                                        </a>
                                        <button onclick="deleteBvi(<?php echo htmlspecialchars($bvi['id']); ?>)" 
                                                class="text-red-500 hover:text-red-700">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    $(document).ready(function() {
    const form = $('#editSwitchForm');
    const hostnameInput = $('#hostname');
    const submitButton = $('#submitButton');
    const switchId = $('#switchId').val();
    const originalHostname = hostnameInput.val();
    const bviId = $('#bvi_id').val();


        // Validate hostname format
        function isValidHostname(hostname) {
            return /^[A-Z]{2,4}-(?:RC|LC)\d{4}-CIN\d{3}$/.test(hostname);
        }

        // Validate BVI format
        function isValidBVI(bvi) {
            return /^BVI\d+$/i.test(bvi);
        }

        // Check for changes and validate inputs
        function validateForm() {
            const hostnameChanged = hostnameInput.val() !== originalHostname;
            const isValid = isValidHostname(hostnameInput.val());
            submitButton.prop('disabled', !hostnameChanged || !isValid);
        }

        // Check for duplicate hostname
        let hostnameCheckTimeout;
        hostnameInput.on('input', function() {
            const input = $(this);
            const validationMessage = input.siblings('.validation-message');
            const value = input.val().toUpperCase();
            input.val(value);

            if (!isValidHostname(value)) {
                input.removeClass('is-valid').addClass('is-invalid');
                validationMessage.text('Invalid format. Must be 2-4 chars followed by -RC/LC + 4 digits-CIN + 3 digits (e.g., AMST-RC0001-CIN001)').removeClass('text-green-600').addClass('text-red-600').removeClass('hidden');
                submitButton.prop('disabled', true);
                return;
            }

            if (value === originalHostname) {
                input.removeClass('is-invalid').addClass('is-valid');
                validationMessage.addClass('hidden');
                validateForm();
                return;
            }

            clearTimeout(hostnameCheckTimeout);
            hostnameCheckTimeout = setTimeout(() => {
                fetch(`/api/switches/check-exists?hostname=${value}&exclude_id=${switchId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            input.removeClass('is-valid').addClass('is-invalid');
                            validationMessage.text('This hostname already exists').removeClass('text-green-600').addClass('text-red-600').removeClass('hidden');
                            submitButton.prop('disabled', true);
                        } else {
                            input.removeClass('is-invalid').addClass('is-valid');
                            validationMessage.text('Valid hostname').removeClass('text-red-600').addClass('text-green-600').removeClass('hidden');
                            validateForm();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        submitButton.prop('disabled', true);
                    });
            }, 500);
        });

        // Form submission

        form.on('submit', function(e) {
            e.preventDefault();
            
            submitButton.prop('disabled', true);

            const formData = {
                hostname: $('#hostname').val(),
                ipv6_address: $('#ipv6_address').val(),
                interface_number: $('#interface_number').val()
            };

            fetch(`/api/switches/${switchId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Switch Updated',
                        html: `
                            <div class="text-left">
                                <p><strong>Old Hostname:</strong> ${originalHostname}</p>
                                <p><strong>New Hostname:</strong> ${formData.hostname}</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3B82F6'
                    }).then((result) => {
                        window.location.href = '/switches';
                    });
                } else {
                    alert(data.message || 'Error updating switch');
                    submitButton.prop('disabled', false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating switch');
                submitButton.prop('disabled', false);
            });
});


        // BVI interface validation
        const bviInput = $('#interface_number');
        const bviValidationMessage = bviInput.siblings('.validation-message');
        let bviCheckTimeout;

        bviInput.on('input', function() {
            const input = $(this);
            const interfaceNumber = input.val().toUpperCase();
            input.val(interfaceNumber);

            if (!isValidBVI(interfaceNumber)) {
                input.removeClass('is-valid').addClass('is-invalid');
                bviValidationMessage.text('Invalid format. Must be BVI followed by numbers (e.g., BVI100)')
                    .removeClass('text-green-600')
                    .addClass('text-red-600')
                    .removeClass('hidden');
                return;
            }

            clearTimeout(bviCheckTimeout);
            bviCheckTimeout = setTimeout(() => {
                fetch(`/api/switches/${switchId}/bvi/check-exists?interface_number=${interfaceNumber}&exclude_id=${bviId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            input.removeClass('is-valid').addClass('is-invalid');
                            bviValidationMessage.text('This BVI interface already exists')
                                .removeClass('text-green-600')
                                .addClass('text-red-600')
                                .removeClass('hidden');
                        } else {
                            input.removeClass('is-invalid').addClass('is-valid');
                            bviValidationMessage.text('Valid BVI interface')
                                .removeClass('text-red-600')
                                .addClass('text-green-600')
                                .removeClass('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking BVI:', error);
                        input.removeClass('is-valid').addClass('is-invalid');
                        bviValidationMessage.text('Error checking BVI interface')
                            .removeClass('text-green-600')
                            .addClass('text-red-600')
                            .removeClass('hidden');
                    });
            }, 500);
        });

        // Initial validation
        validateForm();
    });
</script>


<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
