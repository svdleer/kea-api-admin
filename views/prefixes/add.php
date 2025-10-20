<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Network\Subnet;
use App\Database\Database;

$db = Database::getInstance();
$subnet = new Subnet($db);

$subnets = $subnet->getAllSubnets();

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
    <title>Add New Prefix</title>
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
        .subnet-select {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <p class="mt-4 text-gray-700">Creating prefix...</p>
            </div>
        </div>
    </div>

    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Add New Prefix</h1>
                    <p class="mt-2 text-sm text-gray-600">Create a new network prefix</p>
                </div>
                <a href="/prefixes" class="btn btn-secondary flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Back to Prefixes</span>
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
                <form id="addPrefixForm" method="POST" class="p-6 space-y-6">
                    <!-- Subnet Selection -->
                    <div class="form-group">
                        <label for="subnet_id" class="form-label required">Subnet</label>
                        <div class="relative">
                            <select id="subnet_id" 
                                    name="subnet_id" 
                                    class="form-input subnet-select"
                                    required>
                                <option value="">Select a subnet</option>
                                <?php foreach ($subnets as $subnet): ?>
                                    <option value="<?= $subnet['id'] ?>">
                                        <?= htmlspecialchars($subnet['name']) ?> (<?= htmlspecialchars($subnet['network']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Select the subnet this prefix belongs to
                            </div>
                        </div>
                        <div class="validation-message"></div>
                    </div>

                    <!-- Prefix Field -->
                    <div class="form-group">
                        <label for="prefix" class="form-label required">Prefix</label>
                        <div class="relative">
                            <input type="text" 
                                   id="prefix" 
                                   name="prefix" 
                                   class="form-input"
                                   required 
                                   placeholder="e.g., 2001:db8::/32"
                                   autocomplete="off">
                            <div class="validation-indicator"></div>
                            <div class="help-tooltip">
                                Enter a valid IPv6 prefix with CIDR notation
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
                                      placeholder="Enter prefix description (optional)"
                                      maxlength="255"></textarea>
                            <div class="validation-indicator"></div>
                        </div>
                    </div>

                    <!-- Active Status -->
                    <div class="form-group">
                        <label class="inline-flex items-center">
                            <input type="checkbox" 
                                   name="active" 
                                   class="form-checkbox"
                                   checked>
                            <span class="ml-2">Active</span>
                        </label>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                        <button type="button" 
                                onclick="window.location.href='/prefixes'"
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn btn-primary"
                                id="submitButton">
                            Create Prefix
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addPrefixForm');
            const inputs = {
                subnet_id: {
                    element: document.getElementById('subnet_id'),
                    validate: (value) => value !== '',
                    message: 'Please select a subnet'
                },
                prefix: {
                    element: document.getElementById('prefix'),
                    pattern: /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}(\/\d{1,3})?$|^::$/,
                    message: 'Invalid IPv6 prefix format'
                }
            };

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

                indicator.innerHTML = isValid ? 
                    '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' :
                    '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';

                messageElement.textContent = isValid ? '' : message;
                messageElement.className = `validation-message ${isValid ? 'text-green-600' : 'text-red-600'}`;
            }

            // Real-time validation
            Object.entries(inputs).forEach(([key, input]) => {
                input.element.addEventListener('input', () => validateField(input, input));
                input.element.addEventListener('blur', () => validateField(input, input));
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
                    const response = await fetch('/api/prefixes/create', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.href = '/prefixes?success=Prefix created successfully';
                    } else {
                        throw new Error(data.message || 'Error creating prefix');
                    }
                } catch (error) {
                    alert(error.message);
                    loadingOverlay.classList.add('hidden');
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
                if (e.key === 'Escape') {
                    window.location.href = '/prefixes';
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
