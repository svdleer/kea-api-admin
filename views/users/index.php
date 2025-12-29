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

$currentPage = 'Users';
$title = 'Edit Users';

ob_start();
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
        <button onclick="createUser()" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            Add User
        </button>
    </div>

    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Username
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Email
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Role
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <!-- Users will be loaded here dynamically -->
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
});

function loadUsers() {
    // Debug point 1: Starting fetch
    console.log('Starting to fetch users');
    
    fetch('/api/users')
        .then(response => {
            // Debug point 2: Check response status
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            console.log(response)
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                // Debug point 3: Check raw response
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response');
                }
            });
        })
        .then(data => {
            // Debug point 4: Check parsed data
            console.log('Parsed data:', data);

            const tbody = document.getElementById('users-table-body');
            if (!tbody) {
                throw new Error('users-table-body element not found');
            }

            tbody.innerHTML = '';
            
            if (!data || !data.users || !Array.isArray(data.users)) {
                throw new Error('Invalid data structure received');
            }

            // Debug point 5: Check users array
            console.log('Number of users:', data.users.length);
            
            data.users.forEach(user => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-gray-900">
                        ${user.username || ''}
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-gray-900">
                        ${user.email || ''}
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-gray-900">
                        ${user.is_admin ? 'Admin' : 'User'}
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <span class="relative inline-block px-3 py-1 font-semibold ${user.active ? 'text-green-900' : 'text-red-900'} leading-tight">
                            <span aria-hidden class="absolute inset-0 ${user.active ? 'bg-green-200' : 'bg-red-200'} opacity-50 rounded-full"></span>
                            <span class="relative">${user.active ? 'Active' : 'Inactive'}</span>
                        </span>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <button onclick="editUser(${user.id || 0})" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </button>
                        <button onclick="deleteUser(${user.id || 0})" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Debug point 6: Table updated
            console.log('Table updated successfully');
        })
        .catch(error => {
            // Debug point 7: Error handling
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            
            Swal.fire({
                title: 'Error',
                text: `Failed to load users: ${error.message}`,
                icon: 'error'
            });
        });
}

function createUser() {
    Swal.fire({
        title: 'Create New User',
        html: `
            <div class="grid grid-cols-[120px_1fr] gap-6 items-center text-left">
                <label for="username" class="text-sm font-medium">Username:</label>
                <div class="relative w-full mb-4">
                    <input id="username" 
                        class="swal2-input h-9 text-sm w-full" 
                        placeholder="Username">
                    <span id="username-validation" class="text-xs text-red-500 absolute -bottom-6 left-0"></span>
                </div>
                
                <label for="email" class="text-sm font-medium">Email:</label>
                <div class="relative w-full mb-4">
                    <input id="email" 
                        class="swal2-input h-9 text-sm w-full" 
                        placeholder="Email">
                    <span id="email-validation" class="text-xs text-red-500 absolute -bottom-6 left-0"></span>
                </div>
                
                <label for="password" class="text-sm font-medium">Password:</label>
                <div class="relative w-full mb-4">
                    <input id="password" 
                        type="password" 
                        class="swal2-input h-9 text-sm w-full" 
                        placeholder="Password">
                    <span id="password-validation" class="text-xs text-red-500 absolute -bottom-6 left-0"></span>
                </div>

                <label for="password_confirm" class="text-sm font-medium">Confirm Password:</label>
                <div class="relative w-full mb-4">
                    <input id="password_confirm" 
                        type="password" 
                        class="swal2-input h-9 text-sm w-full" 
                        placeholder="Confirm Password">
                    <span id="password-match-validation" class="text-xs text-red-500 absolute -bottom-6 left-0"></span>
                </div>
                
                <label for="is_admin" class="text-sm font-medium">Role:</label>
                <select id="is_admin" 
                    class="swal2-select h-9 text-sm">
                    <option value="0">User</option>
                    <option value="1">Admin</option>
                </select>

                <div class="col-span-2 text-xs text-gray-600 mt-4">
                    Password must contain:
                    <ul class="list-disc pl-5 space-y-1 mt-1">
                        <li id="length-check">At least 8 characters</li>
                        <li id="uppercase-check">One uppercase letter</li>
                        <li id="lowercase-check">One lowercase letter</li>
                        <li id="number-check">One number</li>
                        <li id="special-check">One special character</li>
                    </ul>
                </div>
            </div>
        `,

        customClass: {
            popup: 'swal2-popup-custom w-[500px]',
            input: 'swal2-input-custom',
            confirmButton: 'swal2-confirm-button-custom'
        },
        showCancelButton: true,
        confirmButtonText: 'Create',
        showLoaderOnConfirm: true,
        didOpen: () => {
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirm');
            const confirmButton = Swal.getConfirmButton();

            let usernameValid = false;
            let emailValid = false;
            let passwordValid = false;
            let passwordsMatch = false;

            // Debounce function
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

            // Password validation function
            function validatePassword(password) {
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                document.getElementById('length-check').className = checks.length ? 'text-green-600' : 'text-red-500';
                document.getElementById('uppercase-check').className = checks.uppercase ? 'text-green-600' : 'text-red-500';
                document.getElementById('lowercase-check').className = checks.lowercase ? 'text-green-600' : 'text-red-500';
                document.getElementById('number-check').className = checks.number ? 'text-green-600' : 'text-red-500';
                document.getElementById('special-check').className = checks.special ? 'text-green-600' : 'text-red-500';

                return Object.values(checks).every(check => check);
            }

            // Check username availability
            const checkUsername = debounce((username) => {
                if (username.length < 3) {
                    document.getElementById('username-validation').textContent = 'Username must be at least 3 characters';
                    usernameValid = false;
                    return;
                }

                fetch(`/api/users/check-username/${username}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            document.getElementById('username-validation').textContent = '✓ Username available';
                            document.getElementById('username-validation').className = 'text-xs text-green-600 absolute -bottom-5 left-0';
                            usernameValid = true;
                        } else {
                            document.getElementById('username-validation').textContent = 'Username already taken';
                            document.getElementById('username-validation').className = 'text-xs text-red-500 absolute -bottom-5 left-0';
                            usernameValid = false;
                        }
                    });
            }, 500);

            // Check email availability
            const checkEmail = debounce((email) => {
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    document.getElementById('email-validation').textContent = 'Invalid email format';
                    emailValid = false;
                    return;
                }

                fetch(`/api/users/check-email/${email}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            document.getElementById('email-validation').textContent = '✓ Email available';
                            document.getElementById('email-validation').className = 'text-xs text-green-600 absolute -bottom-5 left-0';
                            emailValid = true;
                        } else {
                            document.getElementById('email-validation').textContent = 'Email already registered';
                            document.getElementById('email-validation').className = 'text-xs text-red-500 absolute -bottom-5 left-0';
                            emailValid = false;
                        }
                    });
            }, 500);

            // Event listeners
            usernameInput.addEventListener('input', (e) => checkUsername(e.target.value));
            emailInput.addEventListener('input', (e) => checkEmail(e.target.value));
            
            passwordInput.addEventListener('input', (e) => {
                passwordValid = validatePassword(e.target.value);
                if (passwordConfirmInput.value) {
                    passwordsMatch = e.target.value === passwordConfirmInput.value;
                    document.getElementById('password-match-validation').textContent = 
                        passwordsMatch ? '✓ Passwords match' : 'Passwords do not match';
                    document.getElementById('password-match-validation').className = 
                        `text-xs ${passwordsMatch ? 'text-green-600' : 'text-red-500'} absolute -bottom-5 left-0`;
                }
                updateConfirmButton();
            });

            passwordConfirmInput.addEventListener('input', (e) => {
                passwordsMatch = e.target.value === passwordInput.value;
                document.getElementById('password-match-validation').textContent = 
                    passwordsMatch ? '✓ Passwords match' : 'Passwords do not match';
                document.getElementById('password-match-validation').className = 
                    `text-xs ${passwordsMatch ? 'text-green-600' : 'text-red-500'} absolute -bottom-5 left-0`;
                updateConfirmButton();
            });

            function updateConfirmButton() {
                confirmButton.disabled = !(usernameValid && emailValid && passwordValid && passwordsMatch);
            }
        },
        preConfirm: () => {
            const userData = {
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                is_admin: document.getElementById('is_admin').value === '1'
            };

            return fetch('/api/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(userData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    loadUsers();
                    return data;
                }
                throw new Error(data.message || 'Failed to create user');
            });
        }
    }).then((result) => {
        if (result.value) {
            Swal.fire('Success', 'User created successfully', 'success');
        }
    }).catch(error => {
        Swal.fire('Error', error.message, 'error');
        console.error('Error:', error);
    });
}





function editUser(userId) {
    fetch('/api/users')
        .then(response => response.json())
        .then(data => {
            const adminUsers = data.users.filter(user => user.is_admin);
            const isLastAdmin = adminUsers.length === 1 && adminUsers[0].id === userId;

            return fetch(`/api/users/${userId}`)
                .then(response => response.json())
                .then(userData => {
                    let originalData = {
                        username: userData.user.username,
                        email: userData.user.email,
                        is_admin: userData.user.is_admin
                    };

                    Swal.fire({
                        title: 'Edit User',
                        html: `
                            <div class="grid grid-cols-[120px_1fr] gap-4 items-center text-left">
                                <label for="username" class="text-sm font-medium">Username:</label>
                                <input id="username" 
                                    class="swal2-input h-9 text-sm" 
                                    placeholder="Username" 
                                    value="${userData.user.username}">
                                
                                <label for="email" class="text-sm font-medium">Email:</label>
                                <input id="email" 
                                    class="swal2-input h-9 text-sm" 
                                    placeholder="Email" 
                                    value="${userData.user.email}">
                                
                                <label for="is_admin" class="text-sm font-medium">Role:</label>
                                <select id="is_admin" 
                                    class="swal2-select h-9 text-sm" 
                                    ${isLastAdmin ? 'disabled' : ''}>
                                    <option value="0" ${!userData.user.is_admin ? 'selected' : ''}>User</option>
                                    <option value="1" ${userData.user.is_admin ? 'selected' : ''}>Admin</option>
                                </select>
                                ${isLastAdmin ? 
                                    '<div class="col-span-2 text-xs text-red-500 mt-1">Cannot change role: This is the last admin user.</div>' 
                                    : ''}
                            </div>
                        `,
                        customClass: {
                            popup: 'swal2-popup-custom',
                            input: 'swal2-input-custom',
                            confirmButton: 'swal2-confirm-button-custom'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Update',
                        showLoaderOnConfirm: true,
                        didOpen: () => {
                            // Get the confirm button
                            const confirmButton = Swal.getConfirmButton();
                            confirmButton.disabled = true;
                            
                            // Add event listeners to all inputs
                            const inputs = ['username', 'email', 'is_admin'];
                            inputs.forEach(id => {
                                const element = document.getElementById(id);
                                if (element) {
                                    element.addEventListener('input', checkChanges);
                                    element.addEventListener('change', checkChanges);
                                }
                            });

                            function checkChanges() {
                                const currentData = {
                                    username: document.getElementById('username').value,
                                    email: document.getElementById('email').value,
                                    is_admin: document.getElementById('is_admin').value === '1'
                                };

                                // Check if any values have changed
                                const hasChanges = 
                                    currentData.username !== originalData.username ||
                                    currentData.email !== originalData.email ||
                                    currentData.is_admin !== originalData.is_admin;

                                // Enable/disable the confirm button
                                confirmButton.disabled = !hasChanges;
                            }
                        },
                        preConfirm: () => {
                            const userData = {
                                username: document.getElementById('username').value,
                                email: document.getElementById('email').value,
                                is_admin: isLastAdmin ? true : document.getElementById('is_admin').value === '1'
                            };

                            // Validation
                            if (!userData.username.trim()) {
                                Swal.showValidationMessage('Username is required');
                                return false;
                            }
                            if (!userData.email.trim()) {
                                Swal.showValidationMessage('Email is required');
                                return false;
                            }
                            if (!userData.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                                Swal.showValidationMessage('Please enter a valid email address');
                                return false;
                            }

                            return updateUser(userId, userData);
                        }
                    });
                });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to load user data', 'error');
        });
}


function updateUser(userId, userData) {
    return fetch(`/api/users/${userId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Success', data.message, 'success');
            loadUsers(); // Reload the users table
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        Swal.fire('Error', error.message, 'error');
        console.error('Error:', error);
    });
}

function deleteUser(userId) {
    // First check if this is the last admin
    fetch('/api/users')
        .then(response => response.json())
        .then(data => {
            const adminUsers = data.users.filter(user => user.is_admin);
            const targetUser = data.users.find(user => user.id === userId);
            
            // If trying to delete the last admin user
            if (targetUser.is_admin && adminUsers.length === 1) {
                Swal.fire({
                    title: 'Cannot Delete User',
                    text: 'This is the last admin user and cannot be deleted.',
                    icon: 'warning'
                });
                return;
            }

            // Otherwise proceed with delete confirmation
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6366f1',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/api/users/${userId}`, {
                        method: 'DELETE'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Deleted!', data.message, 'success');
                            loadUsers(); // Reload the users table
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message || 'Failed to delete user', 'error');
                        console.error('Error:', error);
                    });
                }
            });
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to check user status', 'error');
            console.error('Error:', error);
        });
}

</script>

<?php
$content = ob_get_clean();
require_once BASE_PATH . '/views/layout.php';
?>
