<?php
error_reporting(E_ALL);
// No direct fix available. Remove or comment out the line to address the vulnerability.

require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$currentPage = 'switches';
$title = 'Add CIN Switch';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Add CIN Switch</h1>
        <a href="/switches" class="text-blue-500 hover:text-blue-700">← Back to CIN Switches</a>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <form id="addSwitchForm" class="space-y-6" novalidate>
            <div>
                <label for="hostname" class="block text-lg font-semibold mb-2">
                    CIN Switch Hostname
                </label>
                <input type="text" 
                       id="hostname" 
                       name="hostname" 
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="XXXX-LC0000-CIN000"
                       required>
                <div class="validation-message mt-1 text-sm"></div>
            </div>

            <div>
                <label for="interface_number" class="block text-lg font-semibold mb-2">
                    BVI Interface
                    <span class="text-sm font-normal text-gray-600 ml-1">(Format: BVI followed by 3 digits)</span>
                </label>
                <input type="text" 
                       id="interface_number" 
                       name="interface_number" 
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="BVI100"
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
                <a href="/switches" 
                   class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" 
                        id="submitButton"
                        disabled
                        class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Add CIN Switch
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    const form = $('#addSwitchForm');
    const submitButton = $('#submitButton');
    let validations = {
        hostname: false,
        interface_number: false,
        ipv6: false
    };

    function isValidIPv6(address) {
        try {
            return address.length > 0 && !!address.match(/^(?:(?:[a-fA-F\d]{1,4}:){7}[a-fA-F\d]{1,4}|(?=(?:[a-fA-F\d]{0,4}:){0,7}[a-fA-F\d]{0,4}$)(([0-9a-fA-F]{1,4}:){1,7}|:)((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[a-fA-F\d]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([a-fA-F\d]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/);
        } catch (e) {
            return false;
        }
    }

    function isValidBVIFormat(value) {
        return /^BVI\d{3}$/.test(value);
    }

    function isValidCinHostname(hostname) {
        return /^[A-Z]{2,4}-(LC|RC)\d{4}-CIN\d{3}$/.test(hostname);
    }

    function updateSubmitButton() {
        const isValid = Object.values(validations).every(v => v === true);
        submitButton.prop('disabled', !isValid);
    }

    $('#hostname').on('input', function() {
        const input = $(this);
        const validationMessage = input.siblings('.validation-message');
        const hostname = input.val().toUpperCase();
        input.val(hostname);

        if (!isValidCinHostname(hostname)) {
            input.removeClass('border-gray-300').addClass('border-red-500');
            validationMessage.text('Invalid format (2-4 chars followed by -RC#### or -LC#### and -CIN###)')
                            .removeClass('text-green-600')
                            .addClass('text-red-600');
            validations.hostname = false;
            updateSubmitButton();
            return;
        }

        fetch(`/api/switches/check-exists?hostname=${encodeURIComponent(hostname)}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.removeClass('border-gray-300').addClass('border-red-500');
                    validationMessage.text('Switch already exists')
                                    .removeClass('text-green-600')
                                    .addClass('text-red-600');
                    validations.hostname = false;
                } else {
                    input.removeClass('border-red-500').addClass('border-gray-300');
                    validationMessage.text('Valid hostname')
                                    .removeClass('text-red-600')
                                    .addClass('text-green-600');
                    validations.hostname = true;
                }
                updateSubmitButton();
            })
            .catch(error => {
                console.error('Error:', error);
                input.removeClass('border-gray-300').addClass('border-red-500');
                validationMessage.text('Error checking hostname')
                                .removeClass('text-green-600')
                                .addClass('text-red-600');
                validations.hostname = false;
                updateSubmitButton();
            });
    });

    $('#interface_number').on('input', function() {
        const input = $(this);
        const validationMessage = input.siblings('.validation-message');
        const value = input.val().toUpperCase();
        input.val(value);

        if (!isValidBVIFormat(value)) {
            input.removeClass('border-gray-300').addClass('border-red-500');
            validationMessage.text('Invalid format (should be BVI followed by 3 digits)')
                            .removeClass('text-green-600')
                            .addClass('text-red-600');
            validations.interface_number = false;
            updateSubmitButton();
            return;
        }

        input.removeClass('border-red-500').addClass('border-gray-300');
        validationMessage.text('Valid BVI interface')
                        .removeClass('text-red-600')
                        .addClass('text-green-600');
        validations.interface_number = true;
        updateSubmitButton();
    });

    $('#ipv6_address').on('input', function() {
        const input = $(this);
        const validationMessage = input.siblings('.validation-message');
        const value = input.val();

        if (!isValidIPv6(value)) {
            input.removeClass('border-gray-300').addClass('border-red-500');
            validationMessage.text('Invalid IPv6 address format')
                            .removeClass('text-green-600')
                            .addClass('text-red-600');
            validations.ipv6 = false;
            updateSubmitButton();
            return;
        }

        fetch(`/api/switches/check-ipv6?ipv6=${encodeURIComponent(value)}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.removeClass('border-gray-300').addClass('border-red-500');
                    validationMessage.text('IPv6 address already exists')
                                    .removeClass('text-green-600')
                                    .addClass('text-red-600');
                    validations.ipv6 = false;
                } else {
                    input.removeClass('border-red-500').addClass('border-gray-300');
                    validationMessage.text('Valid IPv6 address')
                                    .removeClass('text-red-600')
                                    .addClass('text-green-600');
                    validations.ipv6 = true;
                }
                updateSubmitButton();
            })
            .catch(error => {
                console.error('Error:', error);
                input.removeClass('border-gray-300').addClass('border-red-500');
                validationMessage.text('Error checking IPv6 address')
                                .removeClass('text-green-600')
                                .addClass('text-red-600');
                validations.ipv6 = false;
                updateSubmitButton();
            });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        
        if (!validations.hostname || !validations.ipv6) {
            return;
        }

        submitButton.prop('disabled', true);

        const formData = {
            hostname: $('#hostname').val(),
            ipv6_address: $('#ipv6_address').val(),
            interface_number: $('#interface_number').val()
        };

        fetch('/api/switches', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Switch Created',
                    html: `
                        <div class="text-left">
                            <p><strong>Hostname:</strong> ${formData.hostname}</p>
                            <p><strong>IPv6 Address:</strong> ${formData.ipv6_address}</p>
                            <p><strong>Interface:</strong> ${formData.interface_number}</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3B82F6'
                }).then((result) => {
                    window.location.href = '/switches';
                });
            } else {
                alert(data.message || 'Error creating switch');
                submitButton.prop('disabled', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error creating switch');
            submitButton.prop('disabled', false);
        });
    });





});
</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
