<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication();
$auth->requireLogin();

// Set active navigation item
$currentPage = 'DHCP';
$subPage = 'leases';

// Page title
$pageTitle = 'DHCPv6 Leases';

ob_start();
require_once BASE_PATH . '/views/dhcp/dhcp-menu.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">DHCPv6 Leases</h1>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <div class="py-4">
            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <!-- Content here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>
