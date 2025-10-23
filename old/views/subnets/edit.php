<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Network\Subnet;
use App\Database\Database;

$db = Database::getInstance();
$subnet = new Subnet($db);

$subnetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$subnetData = $subnet->getSubnet($subnetId);

if (!$subnetData) {
    header('Location: /subnets?error=Subnet not found');
    throw new Exception('Subnet not found');
}

session_start();
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subnet - <?= htmlspecialchars($subnetData['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/forms.css">
    <style>
        .validation-indicator {
            position: absolute;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }
        .form-group {
            position: relative;
            transition: all 0.3s ease;
        }
        .form-group:focus-within {
            transform: translateX(5px);
        }
        .form-valid { border-color: #10b981; }
        .form-invalid { border-color: #ef4444; }
        .form-dirty { border-color: #f59e0b; }
        .validation-message {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            transition: all 0.3s ease;
        }
        .help-tooltip {
            position: absolute;
            right: -220px;
            top: 0;
            width: 200px;
            padding: 0.5rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            pointer-events: none;
        }
        .form-group:hover .help-tooltip {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <p class="mt-4 text-gray-700">Updating subnet...</p>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
    <div id="unsavedModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                <h3 class="text-lg font-bold mb-4">Unsaved Changes</h3>
                <p class="text-gray-600 mb-6">You have unsaved changes. Are you sure you want to leave?</p>
                <div class="flex justify-end space-x-4">
                    <button onclick="closeUnsavedModal()" class="btn btn-secondary">Stay</button>
                    <button onclick="discardChanges()" class="btn btn-danger">Discard Changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Subnet</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Editing: <?= htmlspecialchars($subnetData['name']) ?>
                    </p>
                </div>
                <a href="/subnets" 
                   class="btn btn-secondary flex items-center space-x-2"
                   id="backButton">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Back to Subnets</span>
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if ($error || $success): ?>
                <div id="alertMessage" 
                     class="mb-6 p-4 rounded-lg <?= $error ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                    <?= htmlspecialchars($error ?? $success) ?>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <div class="bg-white shadow-lg rounded-lg">
                <form id="editSubnetForm" method="POST" class="p-6 space-y-6">
<input type="hidden" name="subnet_id" value="<?= htmlspecialchars($subnetId, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Name Field -->
                    <div class="form-group">
                        <label for="name" class="form-label required">Subnet Name</label>
                        <div class="relative">
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input"
                                   required 
                                   pattern="^[a-zA-Z0-9][a-zA-Z0-9\-_\.]*[a-zA-Z0-9]$"
                                   value="<?= htmlspecialchars($subnetData['name']) ?>"
                                   data-original-value="<?= htmlspecialchars($subnetData['name']) ?>"
                                   autocomplete="off">
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Use letters, numbers, hyphens, and underscores
                            </div>
                        </div>
                        <div class="validation-message"></div>
                    </div>

                    <!-- Network Field -->
                    <div class="form-group">
                        <label for="network" class="form-label required">Network Address</label>
                        <div class="relative">
                            <input type="text" 
                                   id="network" 
                                   name="network" 
                                   class="form-input"
                                   required 
                                   value="<?= htmlspecialchars($subnetData['network']) ?>"
                                   data-original-value="<?= htmlspecialchars($subnetData['network']) ?>"
                                   autocomplete="off">
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Enter a valid IPv4 network address
                            </div>
                        </div>
                        <div class="validation-message"></div>
                    </div>

                    <!-- Mask Field -->
                    <div class="form-group">
                        <label for="mask" class="form-label required">Network Mask</label>
                        <div class="relative">
                            <input type="text" 
                                   id="mask" 
                                   name="mask" 
                                   class="form-input"
                                   required 
                                   value="<?= htmlspecialchars($subnetData['mask']) ?>"
                                   data-original-value="<?= htmlspecialchars($subnetData['mask']) ?>"
                                   autocomplete="off">
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Enter a valid IPv4 subnet mask
                            </div>
                        </div>
                        <div class="validation-message"></div>
                    </div>

                    <!-- VLAN Field -->
                    <div class="form-group">
                        <label for="vlan" class="form-label required">VLAN ID</label>
                        <div class="relative">
                            <input type="number" 
                                   id="vlan" 
                                   name="vlan" 
                                   class="form-input"
                                   required 
                                   min="1"
                                   max="4094"
                                   value="<?= htmlspecialchars($subnetData['vlan']) ?>"
                                   data-original-value="<?= htmlspecialchars($subnetData['vlan']) ?>"
                                   autocomplete="off">
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Enter a VLAN ID between 1 and 4094
                            </div>
                        </div>
                        <div class="validation-message"></div>
                    </div>

                    <!-- Description Field -->
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <div class="relative">
                            <textarea id="description" 
                                      name="description" 
                                      class="form-input h-24"
                                      data-original-value="<?= htmlspecialchars($subnetData['description'] ?? '') ?>"
                                      maxlength="255"><?= htmlspecialchars($subnetData['description'] ?? '') ?></textarea>
                            <div class="validation-indicator"></div>
                        </div>
                    </div>

                    <!-- Active Status -->
                    <div class="form-group">
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="active" 
                                   class="form-checkbox"
                                   <?= $subnetData['active'] ? 'checked' : '' ?>
                                   data-original-value="<?= $subnetData['active'] ? '1' : '0' ?>">
                            <span class="ml-2">Active</span>
                        </label>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                        <div id="saveIndicator" class="text-sm text-gray-500 hidden">
                            Changes detected
                        </div>
                        <button type="button" 
                                onclick="handleCancel()"
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn btn-primary"
                                id="submitButton">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editSubnetForm');
            const inputs = {
                name: {
                    element: document.getElementById('name'),
                    pattern: /^[a-zA-Z0-9][a-zA-Z0-9\-_\.]*[a-zA-Z0-9]$/,
                    message: 'Invalid name format'
                },
                network: {
                    element: document.getElementById('network'),
                    pattern: /^(\d{1,3}\.){3}\d{1,3}$/,
                    message: 'Invalid network address'
                },
                mask: {
                    element: document.getElementById('mask'),
                    pattern: /^(\d{1,3}\.){3}\d{1,3}$/,
                    message: 'Invalid network mask'
                },
                vlan: {
                    element: document.getElementById('vlan'),
                    validate: (value) => {
                        const num = parseInt(value);
                        return num >= 1 && num <= 4094;
                    },
                    message: 'VLAN ID must be between 1 and 4094'
                }
            };

            let formChanged = false;
            const saveIndicator = document.getElementById('saveIndicator');

            function validateField(input, validation) {
                const value = input.element.value;
                let isValid;
                
                if (validation.pattern) {
                    isValid = validation.pattern.test(value);
                } else if (validation.validate) {
                    isValid = validation.validate(value);
                }

                updateValidationUI(input.element, isValid, validation.message);
                return isValid;
            }

            function updateValidationUI(input, isValid, message) {
                const container = input.closest('.form-group');
                const indicator = container.querySelector('.validation-indicator');
                const messageElement = container.querySelector('.validation-message');

                input.classList.remove('form-valid', 'form-invalid');
                input.classList.add(isValid ? 'form-valid' : 'form-invalid');

                if (input.value !== input.dataset.originalValue) {
                    input.classList.add('form-dirty');
                } else {
                    input.classList.remove('form-dirty');
                }

                indicator.innerHTML = isValid ? 
                    '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' :
                    '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';

                messageElement.textContent = isValid ? '' : message;
                messageElement.className = `validation-message ${isValid ? 'text-green-600' : 'text-red-600'}`;
            }

            function checkFormChanges() {
                const hasChanges = Object.values(inputs).some(input => 
                    input.element.value !== input.element.dataset.originalValue
                );
                
                formChanged = hasChanges;
                saveIndicator.classList.toggle('hidden', !hasChanges);
                return hasChanges;
            }

            // Real-time validation
            Object.entries(inputs).forEach(([key, input]) => {
                input.element.addEventListener('input', () => {
                    validateField(input, input);
                    checkFormChanges();
                });
                input.element.addEventListener('blur', () => {
                    validateField(input, input);
                    checkFormChanges();
                });
            });

            // Form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Validate all fields
                let isValid = true;
                Object.values(inputs).forEach(input => {
                    if (!validateField(input, input)) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    alert('Please correct the errors before submitting');
                    return;
                }

                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.classList.remove('hidden');

                try {
                    const formData = new FormData(form);
                    const response = await fetch('/api/subnets/update', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = '/subnets?success=Subnet updated successfully';
                    } else {
                        throw new Error(data.message || 'Error updating subnet');
                    }
                } catch (error) {
                    alert(error.message);
                    loadingOverlay.classList.add('hidden');
                }
            });

            // Handle navigation away from form
            function handleCancel() {
                if (checkFormChanges()) {
                    document.getElementById('unsavedModal').classList.remove('hidden');
                } else {
                    window.location.href = '/subnets';
                }
            }

            window.closeUnsavedModal = function() {
                document.getElementById('unsavedModal').classList.add('hidden');
            };

            window.discardChanges = function() {
                window.location.href = '/subnets';
            };

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
                if (e.key === 'Escape') {
                    handleCancel();
                }
            });

            // Warn about unsaved changes when leaving page
            window.addEventListener('beforeunload', function(e) {
                if (checkFormChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Auto-hide alerts
            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                setTimeout(() => {
                    alertMessage.style.opacity = '0';
                    setTimeout(() => alertMessage.remove(), 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>
