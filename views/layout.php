<?php

require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Create new auth instance
$auth = new Authentication(Database::getInstance());

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'VFZ DAA Infrastructure Management'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-minimal@5/minimal.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css" />
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <style>
        body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { 
            padding-right: 0 !important; 
            overflow-y: visible !important;
        }
        
        .swal2-container {
            padding-right: 0 !important;
        }
        
        html.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
            overflow-y: visible !important;
        }
        
        .swal2-shown .footer {
            padding-right: 0 !important;
        }
        .swal2-popup-custom {
            font-size: 0.875rem !important;
            padding: 1.5rem;
        }

        .swal2-popup-custom .swal2-title {
            font-size: 1.25rem;
            padding: 0.5rem 0 1.5rem 0;
        }

        .swal2-popup-custom .swal2-input,
        .swal2-popup-custom .swal2-select {
            margin: 0 !important;
            font-size: 0.875rem !important;
            height: 2.25rem !important;
        }

        .swal2-popup-custom .swal2-input {
            padding: 0 0.75rem !important;
        }

        .swal2-popup-custom .swal2-select {
            padding: 0 0
            .5rem !important;
        }

        .swal2-confirm-button-custom[disabled] {
            cursor: not-allowed !important;
            background-color: #6b7280 !important; 
        }

        .swal2-confirm-button-custom:not([disabled]) {
            background-color: #3085d6 !important; 
        }

        #swagger-ui {
        height: 100%;
        overflow: auto;
    }
    
    .swagger-ui .wrapper {
        padding: 0;
        max-width: 100%;
    }
    
    .swagger-ui .scheme-container {
        position: sticky;
        top: 0;
        z-index: 1;
        background: white;
    }

    .auth-banner code {
        background: rgba(0,0,0,0.05);
        padding: 2px 4px;
        border-radius: 4px;
    }

    .swagger-ui .auth-wrapper .authorize {
        background-color: #4299e1;
        border-color: #4299e1;
        color: white;
        padding: 5px 15px;
        border-radius: 5px;
    }

    .swagger-ui .auth-wrapper .authorize svg {
        fill: white;
    }

    .swagger-ui .try-out__btn {
        background-color: #48bb78 !important;
        color: white !important;
        border-color: #48bb78 !important;
    }

    .swagger-ui .try-out__btn:hover {
        background-color: #38a169 !important;
        border-color: #38a169 !important;
    }       
                
    </style>
    
    <script>
    // Global notification function using SweetAlert2
    function showNotification(message, type = 'info', title = null) {
        const icons = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        const titles = {
            success: title || 'Success',
            error: title || 'Error',
            warning: title || 'Warning',
            info: title || 'Information'
        };
        
        Swal.fire({
            icon: icons[type] || 'info',
            title: titles[type],
            text: message,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
        });
    }
    
    // Toast notification for less intrusive messages
    function showToast(message, type = 'info') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }
    </script>
    
</head>
<body class="h-full flex flex-col bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow sticky top-0 z-50">
        <nav class="bg-white">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-gray-800">VFZ DAA Infrastructure Management</h1>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="/dashboard" class="<?php echo $currentPage === 'dashboard' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="/switches" class="<?php echo $currentPage === 'switches' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Switches
                            </a>
                            <a href="/bvi" class="<?php echo $currentPage === 'bvi' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                BVI Interfaces
                            </a>
                            <a href="/dhcp" class="<?php echo $currentPage === 'dhcp' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                DHCPv6
                            </a>
                            <a href="/radius" class="<?php echo $currentPage === 'radius' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                RADIUS (802.1X)
                            </a>
                            <?php if ($auth->isAdmin()): ?>
                            <a href="/admin/settings" class="<?php echo $currentPage === 'settings' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Settings
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <a href="/logout" class="bg-red-500 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content (Scrollable) -->
    <main class="flex-1 overflow-y-auto pb-16">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php echo $content ?? ''; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow fixed bottom-0 w-full z-40">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> VFZ DAA Admin
                </div>
                <div class="text-sm text-gray-500">
                    Version 0.1
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
