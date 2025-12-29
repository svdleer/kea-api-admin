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
$subPage = 'subnets';

// Page title
$pageTitle = 'DHCP Subnets Configuration';

try {
    $db = Database::getInstance();
    $cinSwitch = new CinSwitch($db);
    $subnetModel = new DHCP($db); 
    $switches = $cinSwitch->getAllSwitches();
    
    // Add DHCP subnets
    $dhcp = new DHCP($db);
    error_log("====== DHCP View: About to call DHCPModel->getEnrichedSubnets() ======");
    $subnets = $subnetModel->getEnrichedSubnets();
    error_log("getEnrichedSubnets returned " . count($subnets) . " subnets");
    error_log("Subnets data: " . json_encode($subnets));
    error_log("====== DHCP View: Returned from DHCPModel->getEnrichedSubnets() ======");

    // Create an expanded list of switches with their BVI interfaces
    $expandedSwitches = [];
    foreach ($switches as $switch) {
        $bviInterfaces = $cinSwitch->getBVIInterfaces($switch['id']);

        if (!empty($bviInterfaces)) {
            foreach ($bviInterfaces as $bvi) {
                $expandedSwitch = $switch;
                $expandedSwitch['switch_id'] = $switch['id'] ?? '';
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
            $expandedSwitch['switch_id'] = '';
            $expandedSwitch['bvi_interface'] = '';
            $expandedSwitch['bvi_interface_id'] = '';
            $expandedSwitch['ipv6_address'] = '';
            $expandedSwitch['subnet'] = '';
            $expandedSwitches[] = $expandedSwitch;
        }
    }
    
    $switches = $expandedSwitches;
    error_log('Expanded switches: ' . json_encode($switches));
} catch (\Exception $e) {
    $subnets = [];
    $switches = [];
    $error = $e->getMessage();
    error_log('DHCP View Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

// Start output buffering
ob_start();

// Include the DCHP menu
require BASE_PATH . '/views/dhcp-menu.php';



?>



<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">DHCP Subnet Configuration</h1>
                <p class="mt-1 text-sm text-gray-600">Configure and manage DHCP subnets for switches and interfaces</p>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input type="text" 
                   id="searchInput"
                   placeholder="Search by hostname, BVI, IPv6 or subnet..." 
                   onkeyup="performSearch(this.value)"
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden w-fit">
        <?php 
        // Debug: Log the switches array
        error_log("DEBUG - Total switches in array: " . count($switches));
        foreach ($switches as $idx => $switch) {
            error_log("DEBUG - Switch $idx: bvi_interface = '" . ($switch['bvi_interface'] ?? 'NOT SET') . "' (type: " . gettype($switch['bvi_interface'] ?? null) . ")");
        }
        
        // Check if there are any BVI interfaces at all
        $hasBviInterfaces = false;
        foreach ($switches as $switch) {
            if (isset($switch['bvi_interface']) && $switch['bvi_interface'] !== '') {
                $hasBviInterfaces = true;
                break;
            }
        }
        error_log("DEBUG - hasBviInterfaces: " . ($hasBviInterfaces ? 'true' : 'false'));
        ?>
        <?php if (!$hasBviInterfaces): ?>
            <!-- Empty state - no BVI interfaces -->
            <div class="px-6 py-12 text-center text-sm text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No BVI interfaces configured</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Get started by adding BVI interfaces to your switches, or import from existing configuration.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <a href="/bvi" 
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add BVI Interface
                    </a>
                    <a href="/admin/import-wizard" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Import Configuration
                    </a>
                </div>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-auto divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Switch Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BVI Interface</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DHCP Subnet</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pool Range</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CCAP Core</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($switches as $switch): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($switch['hostname'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= isset($switch['bvi_interface']) && $switch['bvi_interface'] !== '' ? 'BVI' . (100 + intval($switch['bvi_interface'])) : '' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                    <?= htmlspecialchars($switch['subnet']['subnet']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not Configured</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-normal text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet']) && isset($switch['subnet']['pool'])): ?>
                                    <div class="flex flex-col">
                                        <span class="mb-1"><?= htmlspecialchars($switch['subnet']['pool']['start']) ?></span>
                                        <span><?= htmlspecialchars($switch['subnet']['pool']['end']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">Not configured</span>
                                <?php endif; ?>
                            </td>


                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                    <?= htmlspecialchars($switch['subnet']['ccap_core'] ?? 'Not set') ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Not configured</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <?php if (!empty($switch['subnet']) && is_array($switch['subnet'])): ?>
                                        <button onclick="showEditSubnetModal('<?= htmlspecialchars(json_encode($switch['subnet']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($switch['ipv6_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        <button onclick="deleteSubnet('<?= $switch['subnet']['id'] ?>', '<?= htmlspecialchars(json_encode($switch['subnet']['subnet']), ENT_QUOTES, 'UTF-8') ?>')"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <button onclick="showCreateSubnetModal('<?= htmlspecialchars($switch['switch_id'] ?? '') ?>','<?= htmlspecialchars($switch['bvi_interface'] ?? '') ?>', '<?= htmlspecialchars($switch['ipv6_address'] ?? '') ?>', '<?= htmlspecialchars($switch['bvi_interface_id'] ?? '') ?>')" 
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            Configure
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php
    // Get dedicated subnet IDs from database
    $stmt = $db->prepare("SELECT kea_subnet_id FROM dedicated_subnets");
    $stmt->execute();
    $dedicatedSubnetIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'kea_subnet_id');
    
    // Find orphaned subnets (subnets that don't have a matching BVI and are NOT dedicated)
    $assignedBviIds = array_map(function($switch) {
        return $switch['bvi_interface_id'] ?? null;
    }, $switches);
    $assignedBviIds = array_filter($assignedBviIds);
    
    error_log("Assigned BVI IDs: " . json_encode($assignedBviIds));
    error_log("Dedicated subnet IDs: " . json_encode($dedicatedSubnetIds));
    
    $orphanedSubnets = array_filter($subnets, function($subnet) use ($assignedBviIds, $dedicatedSubnetIds) {
        // Skip dedicated subnets - they are not orphaned
        if (in_array($subnet['id'], $dedicatedSubnetIds)) {
            return false;
        }
        
        // A subnet is orphaned if:
        // 1. It has a bvi_interface_id that doesn't match any current BVI
        // 2. OR it has a null bvi_interface_id (subnet exists in Kea but not in our database)
        $hasBviId = isset($subnet['bvi_interface_id']) && $subnet['bvi_interface_id'] !== null;
        
        if ($hasBviId) {
            // Has a BVI ID but it doesn't match any current BVI
            return !in_array($subnet['bvi_interface_id'], $assignedBviIds);
        } else {
            // No BVI ID means it's an orphaned subnet (exists in Kea but not tracked)
            return true;
        }
    });
    
    error_log("Found " . count($orphanedSubnets) . " orphaned subnets");
    error_log("Orphaned subnets: " . json_encode($orphanedSubnets));
    ?>

    <?php if (!empty($orphanedSubnets)): ?>
    <!-- Orphaned Subnets Section -->
    <div class="mt-8 bg-red-50 border border-red-200 rounded-lg p-4">
        <h2 class="text-lg font-semibold text-red-800 mb-4">
            ⚠️ Orphaned Subnets (BVI Deleted)
        </h2>
        <p class="text-sm text-red-600 mb-4">
            These subnets exist in Kea but their associated BVI interfaces have been deleted. You should delete these orphaned subnets.
        </p>
        <?php if (count($orphanedSubnets) === 0): ?>
        <div class="bg-white p-4 rounded text-gray-600">
            No orphaned subnets detected, but this section is showing because the array is not empty.
            Debug: <?= htmlspecialchars(json_encode($orphanedSubnets)) ?>
        </div>
        <?php else: ?>
        <div class="bg-white shadow-md rounded-lg overflow-hidden w-fit">
            <table class="w-auto divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subnet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pools</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($orphanedSubnets as $orphan): ?>
                        <tr class="bg-red-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($orphan['subnet'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php if (!empty($orphan['pools'])): ?>
                                    <?php foreach ($orphan['pools'] as $pool): ?>
                                        <div><?= htmlspecialchars($pool['pool'] ?? 'N/A') ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    No pools
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button onclick="linkOrphanedSubnet('<?= $orphan['id'] ?>', '<?= htmlspecialchars($orphan['subnet'], ENT_QUOTES, 'UTF-8') ?>')"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Link to BVI
                                </button>
                                <button onclick="deleteOrphanedSubnet('<?= $orphan['id'] ?>', '<?= htmlspecialchars(json_encode($orphan['subnet']), ENT_QUOTES, 'UTF-8') ?>')"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
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
    <?php endif; ?>
</div>

    <!-- Create Modal -->
<div id="createSubnetModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-4">Create New DHCPv6 Subnet</h3>
            <form id="createSubnetForm" class="mt-2">
                <input type="hidden" id="create_switch_id" name="switch_id">
                <input type="hidden" id="create_subnet_id" name="subnet_id">
                <input type="hidden" id="create_interface" name="interface">
                <input type="hidden" id="create_interface_id" name="interface_id">


                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_subnet">
                            Subnet Prefix
                        </label>
                        <input type="text" id="create_subnet" name="subnet" required 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            onchange="validateIPv6Address(this)">
                        <span id="create_subnetError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_mask">
                            Prefix Length
                        </label>
                        <input type="text" id="create_mask" name="mask" value="64" readonly
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_pool_start">
                            Pool Start
                        </label>
                        <input type="text" id="create_pool_start" name="pool_start" readonly disabled
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                        <span id="create_pool_startError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_pool_end">
                            Pool End
                        </label>
                        <input type="text" id="create_pool_end" name="pool_end" readonly disabled
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                        <span id="create_pool_endError" class="text-red-500 text-xs hidden"></span>
                    </div>


                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_ccap_core_address">
                            CCAP Core Address
                        </label>
                        <input type="text" id="create_ccap_core_address" name="ccap_core_address" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               onchange="validateIPv6Address(this)">
                        <span id="create_ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="create_relay_address">
                            Relay Address
                        </label>
                        <input type="text" id="create_relay_address" name="relay_address" required readonly
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                    </div>
                </div>

                <!-- Lease Timers Section -->
                <div class="mt-6 mb-4">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Lease Timers (seconds)</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="create_valid_lifetime">
                                Valid Lifetime
                            </label>
                            <input type="number" id="create_valid_lifetime" name="valid_lifetime" value="7200"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="create_preferred_lifetime">
                                Preferred Lifetime
                            </label>
                            <input type="number" id="create_preferred_lifetime" name="preferred_lifetime" value="3600"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="create_renew_timer">
                                Renew Timer (T1)
                            </label>
                            <input type="number" id="create_renew_timer" name="renew_timer" value="1000"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="create_rebind_timer">
                                Rebind Timer (T2)
                            </label>
                            <input type="number" id="create_rebind_timer" name="rebind_timer" value="2000"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                            onclick="document.getElementById('createSubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Create Subnet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="rounded-md shadow-sm -space-y-px">
<!-- Edit Modal - Dynamically generated by showEditSubnetModal() -->
<div id="editSubnetModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 hidden">
</div>
<script>

let originalFormData = {};

function checkFormChanges() {
    console.debug('Checking form changes');
    const ccapCoreField = document.getElementById('edit_ccap_core_address');
    const validLifetimeField = document.getElementById('edit_valid_lifetime');
    const preferredLifetimeField = document.getElementById('edit_preferred_lifetime');
    const renewTimerField = document.getElementById('edit_renew_timer');
    const rebindTimerField = document.getElementById('edit_rebind_timer');
    const saveButton = document.getElementById('editSubnetSaveButton');
    
    if (ccapCoreField && saveButton) {
        const currentCcapCore = ccapCoreField.value;
        const ccapCoreChanged = currentCcapCore !== originalFormData.ccap_core_address;
        
        // Check if any lifetime fields have changed
        const validLifetimeChanged = validLifetimeField && (parseInt(validLifetimeField.value) || 7200) !== (originalFormData.valid_lifetime || 7200);
        const preferredLifetimeChanged = preferredLifetimeField && (parseInt(preferredLifetimeField.value) || 3600) !== (originalFormData.preferred_lifetime || 3600);
        const renewTimerChanged = renewTimerField && (parseInt(renewTimerField.value) || 1000) !== (originalFormData.renew_timer || 1000);
        const rebindTimerChanged = rebindTimerField && (parseInt(rebindTimerField.value) || 2000) !== (originalFormData.rebind_timer || 2000);
        
        const anyFieldChanged = ccapCoreChanged || validLifetimeChanged || preferredLifetimeChanged || renewTimerChanged || rebindTimerChanged;
        
        console.debug('CCAP Core changed:', ccapCoreChanged);
        console.debug('Any field changed:', anyFieldChanged);
        
        // Enable button if any field changed AND IPv6 is valid
        saveButton.disabled = !anyFieldChanged || !validateIPv6Address(ccapCoreField);
    }
}



document.addEventListener('DOMContentLoaded', function() {
    // Helper Functions
    function setInputValueWithoutValidation(elementId, value, readonly = false) {
        const element = document.getElementById(elementId);
        if (element) {
            element.value = value || '';
            if (readonly) {
                element.setAttribute('readonly', 'readonly');
                element.classList.add('bg-gray-100');
            } else {
                element.removeAttribute('readonly');
                element.classList.remove('bg-gray-100');
            }
        }
    }

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

    // Validation Functions
    function isValidIPv6OrCommaSeparated(value) {
        if (!value) return true; // Allow empty
        
        const ipv6Regex = /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;
        
        // Check if contains comma (multiple addresses)
        if (value.includes(',')) {
            const addresses = value.split(',').map(addr => addr.trim());
            return addresses.every(addr => ipv6Regex.test(addr));
        }
        
        // Single address
        return ipv6Regex.test(value);
    }

    function validateIPv6Address(input) {
        if (!input.value) {
            const errorSpan = document.getElementById(input.id + 'Error');
            if (errorSpan) {
                errorSpan.classList.add('hidden');
            }
            input.classList.remove('border-red-500');
            return true;
        }

        const errorSpan = document.getElementById(input.id + 'Error');
        if (!errorSpan) return true;

        const ipv6Regex = /^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/;

        // Check if input contains comma (multiple addresses)
        if (input.value.includes(',')) {
            const addresses = input.value.split(',').map(addr => addr.trim());
            const allValid = addresses.every(addr => ipv6Regex.test(addr));
            
            if (!allValid) {
                errorSpan.textContent = 'One or more IPv6 addresses are invalid';
                errorSpan.classList.remove('hidden');
                input.classList.add('border-red-500');
                return false;
            } else {
                errorSpan.classList.add('hidden');
                input.classList.remove('border-red-500');
                return true;
            }
        }

        // Single address validation
        const isValid = ipv6Regex.test(input.value);
        
        if (!isValid) {
            errorSpan.textContent = 'Please enter a valid IPv6 address';
            errorSpan.classList.remove('hidden');
            input.classList.add('border-red-500');
            return false;
        } else {
            errorSpan.classList.add('hidden');
            input.classList.remove('border-red-500');
            return true;
        }
    }

    // Create debounced version of validation
    const debouncedValidation = debounce((input) => {
        validateIPv6Address(input);
    }, 300);

    // Modal Functions
    function showCreateSubnetModal(Switchid = null, bviInterface = null, ipv6Address = null, bviInterfaceId = null) {
        const modal = document.getElementById('createSubnetModal');
        if (!modal) return;

        // Reset form and clear any previous error states
        const errorSpans = modal.querySelectorAll('[id$="Error"]');
        errorSpans.forEach(span => span.classList.add('hidden'));
        
        const inputFields = modal.querySelectorAll('input');
        inputFields.forEach(input => input.classList.remove('border-red-500'));

        // Set initial values without triggering validation
        setInputValueWithoutValidation('create_switch_id', Switchid || '' );
        setInputValueWithoutValidation('create_interface', bviInterface || '');
        setInputValueWithoutValidation('create_interface_id', bviInterfaceId || '');

        if (ipv6Address) {
            const prefix = ipv6Address.split('::')[0];
            if (prefix) {
                setInputValueWithoutValidation('create_subnet', prefix + '::', true);
                setInputValueWithoutValidation('create_relay_address', ipv6Address, true);
                setInputValueWithoutValidation('create_pool_start', prefix + '::2');
                setInputValueWithoutValidation('create_pool_end', prefix + '::fffe');
            }
        }

        
        modal.classList.remove('hidden');
    }

    async function handleEditSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Validate CCAP Core IPv6 address before submission
    const ccapCore = formData.get('ccap_core_address');
    if (!isValidIPv6OrCommaSeparated(ccapCore)) {
        await Swal.fire({
            title: 'Validation Error!',
            text: 'Please enter valid IPv6 address(es) for CCAP Core',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    // Get the subnet ID from the hidden input
    const subnetId = formData.get('subnet_id');

    // Create the data object with all required fields
    const data = {
        id: subnetId,
        subnet: formData.get('subnet'),
        pool: {
            start: formData.get('pool_start'),
            end: formData.get('pool_end')
        },
        bvi_interface_id: formData.get('interface_id'),
        ccap_core: ccapCore,
        relay_address: formData.get('relay_address')
    };

    try {
        const response = await fetch(`/api/subnets/${subnetId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        console.log('Success:', result);

        await Swal.fire({
            title: 'Success!',
            text: 'Subnet updated successfully',
            icon: 'success',
            confirmButtonColor: '#3085d6'
        });

        closeModal('editSubnetModal');
        await loadSubnets();
    } catch (error) {
        console.error('Error:', error);
        await Swal.fire({
            title: 'Error!',
            text: 'Failed to update subnet',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }
}

function showEditSubnetModal(subnetData, relay) {


    
  console.log('Modal data:', subnetData);

  const modal = document.getElementById('editSubnetModal');
    if (!modal) return;

    try {
        const subnet = typeof subnetData === 'string' ? JSON.parse(subnetData) : subnetData;


        // Reset form and clear any previous error states
        const errorSpans = modal.querySelectorAll('[id$="Error"]');
        errorSpans.forEach(span => span.classList.add('hidden'));
        
        const inputFields = modal.querySelectorAll('input');
        inputFields.forEach(input => input.classList.remove('border-red-500'));

        // Update modal content with smaller width
        const modalContent = `
            <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white">
                <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mb-4">Edit DHCPv6 Subnet</h3>
                <div class="mb-4 text-center">
                    <p class="text-gray-600">DHCP Prefix: <span class="font-semibold">${subnet.subnet}</span></p>
                    <p class="text-gray-600 mt-2">Subnet Pool: <span class="font-semibold">${subnet.pool.start} - ${subnet.pool.end}</span></p>
                </div>
                <form id="editSubnetForm">
                    <input type="hidden" id="edit_subnet_id" name="subnet_id">
                    <input type="hidden" id="edit_interface" name="interface">
                    <input type="hidden" id="edit_interface_id" name="interface_id">
                    <input type="hidden" id="edit_switch_id" name="switch_id">
                    <input type="hidden" id="edit_subnet" name="subnet">
                    <input type="hidden" id="edit_pool_start" name="pool_start">
                    <input type="hidden" id="edit_pool_end" name="pool_end">
                    <input type="hidden" id="edit_relay_address" name="relay_address">



                    <div class="mb-4">
                        <label for="edit_ccap_core_address" class="block text-gray-700 text-sm font-bold mb-2">CCAP Core Address</label>
                        <input type="text" 
                               id="edit_ccap_core_address" 
                               name="ccap_core_address" 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               oninput="validateIPv6Address(this)">
                        <span id="edit_ccap_core_addressError" class="text-red-500 text-xs hidden"></span>
                    </div>

                    <!-- Lease Timers Section -->
                    <div class="mt-4 mb-4">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Lease Timers (seconds)</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit_valid_lifetime" class="block text-gray-700 text-xs font-bold mb-1">Valid Lifetime</label>
                                <input type="number" id="edit_valid_lifetime" name="valid_lifetime"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 text-sm leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label for="edit_preferred_lifetime" class="block text-gray-700 text-xs font-bold mb-1">Preferred Lifetime</label>
                                <input type="number" id="edit_preferred_lifetime" name="preferred_lifetime"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 text-sm leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label for="edit_renew_timer" class="block text-gray-700 text-xs font-bold mb-1">Renew Timer (T1)</label>
                                <input type="number" id="edit_renew_timer" name="renew_timer"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 text-sm leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label for="edit_rebind_timer" class="block text-gray-700 text-xs font-bold mb-1">Rebind Timer (T2)</label>
                                <input type="number" id="edit_rebind_timer" name="rebind_timer"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 text-sm leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                    <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                            onclick="document.getElementById('editSubnetModal').classList.add('hidden')">
                        Cancel
                    </button>
                        <button type="submit" 
                                id="editSubnetSaveButton" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                disabled>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>`;

        modal.innerHTML = modalContent;

        // Set form values
        setInputValueWithoutValidation('edit_subnet_id', subnet.id);
        setInputValueWithoutValidation('edit_switch_id', subnet.switch_id);
        setInputValueWithoutValidation('edit_interface_id', subnet.bvi_interface_id);
        setInputValueWithoutValidation('edit_interface', subnet.bvi_interface);
        setInputValueWithoutValidation('edit_subnet', subnet.subnet, true);
        setInputValueWithoutValidation('edit_pool_start', subnet.pool.start, true);
        setInputValueWithoutValidation('edit_pool_end', subnet.pool.end, true);
        setInputValueWithoutValidation('edit_ccap_core_address', subnet.ccap_core);
        setInputValueWithoutValidation('edit_relay_address', relay, true);
        
        // Set lifetime timer values (editable)
        setInputValueWithoutValidation('edit_valid_lifetime', subnet.valid_lifetime || 7200, false);
        setInputValueWithoutValidation('edit_preferred_lifetime', subnet.preferred_lifetime || 3600, false);
        setInputValueWithoutValidation('edit_renew_timer', subnet.renew_timer || 1000, false);
        setInputValueWithoutValidation('edit_rebind_timer', subnet.rebind_timer || 2000, false);

        // Set original form data
        originalFormData = {
            ccap_core_address: subnet.ccap_core,
            valid_lifetime: subnet.valid_lifetime || 7200,
            preferred_lifetime: subnet.preferred_lifetime || 3600,
            renew_timer: subnet.renew_timer || 1000,
            rebind_timer: subnet.rebind_timer || 2000
        };

        const saveButton = document.getElementById('editSubnetSaveButton');
        if (saveButton) {
            saveButton.disabled = true;
        }

        // Add event listeners
        const ccapCoreField = document.getElementById('edit_ccap_core_address');
        if (ccapCoreField) {
            console.debug('Setting up CCAP Core field listeners'); 
            ccapCoreField.addEventListener('input', function(e) {
                validateIPv6Address(this);  
                checkFormChanges();   
            });    
            ccapCoreField.addEventListener('keyup', function(e) {
                validateIPv6Address(this);
                checkFormChanges();
            });
        }
        
        // Add event listeners to lifetime fields
        ['edit_valid_lifetime', 'edit_preferred_lifetime', 'edit_renew_timer', 'edit_rebind_timer'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', checkFormChanges);
                field.addEventListener('change', checkFormChanges);
            }
        });




        const form = document.getElementById('editSubnetForm');
        if (form) {
            form.addEventListener('submit', handleEditSubmit);
        }

        modal.classList.remove('hidden');
    } catch (error) {
        console.error('Error parsing subnet data:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Failed to load subnet data',
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }
}


    // Form Handlers
    async function handleCreateSubmit(e) {
        e.preventDefault();

        // Validate all IPv6 addresses before submitting
        const ipv6Fields = ['create_subnet', 'create_pool_start', 'create_pool_end', 'create_ccap_core_address', 'relay'];
        let isValid = true;

        ipv6Fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !validateIPv6Address(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please correct the IPv6 addresses before submitting.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const subnet = document.getElementById('create_subnet').value + '/64';
            try {
                const duplicateCheck = await checkDuplicateSubnet(subnet);
                if (duplicateCheck.exists) {
                    Swal.fire({
                        title: 'Duplicate Subnet!',
                        text: 'This subnet already exists in the database.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
            } catch (error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to check for duplicate subnet.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

        const formData = {
            switch_id: document.getElementById('create_switch_id').value,
            bvi_interface: document.getElementById('create_interface').value,
            bvi_interface_id: document.getElementById('create_interface_id').value,
            subnet: document.getElementById('create_subnet').value + '/64',
            pool_start: document.getElementById('create_pool_start').value,
            pool_end: document.getElementById('create_pool_end').value,
            ccap_core_address: document.getElementById('create_ccap_core_address').value,
            relay_address: document.getElementById('create_relay_address').value,
            ipv6_address: document.getElementById('create_relay_address').value,
            valid_lifetime: parseInt(document.getElementById('create_valid_lifetime').value) || 7200,
            preferred_lifetime: parseInt(document.getElementById('create_preferred_lifetime').value) || 3600,
            renew_timer: parseInt(document.getElementById('create_renew_timer').value) || 1000,
            rebind_timer: parseInt(document.getElementById('create_rebind_timer').value) || 2000

            
        };

        const confirmHtml = `
            <div class="text-left">
                <p class="font-bold mb-2">Please confirm the following subnet configuration:</p>
                <p><span class="font-semibold">Subnet:</span> 
                    <span class="text-blue-600">${formData.subnet}</span></p>
                <p><span class="font-semibold">Pool Start:</span> 
                    <span class="text-blue-600">${formData.pool_start}</span></p>
                <p><span class="font-semibold">Pool End:</span> 
                    <span class="text-blue-600">${formData.pool_end}</span></p>
                <p><span class="font-semibold">CCAP Core Address:</span> 
                    <span class="text-blue-600">${formData.ccap_core_address}</span></p>
                <p><span class="font-semibold">Relay Address:</span> 
                    <span class="text-blue-600">${formData.relay_address}</span></p>
            </div>
        `;

        const result = await Swal.fire({
            title: 'Confirm Subnet Creation',
            html: confirmHtml,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, create subnet',
            cancelButtonText: 'Cancel',
            width: '600px'
        });

        if (result.isConfirmed) {
        try {
            const response = await fetch('/api/dhcp/subnets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                const modal = document.getElementById('createSubnetModal');
                if (modal) {
                    modal.classList.add('hidden');
                }

                // Show success message before reload
                await Swal.fire({
                    title: 'Success!',
                    text: 'Subnet has been created successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });

                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.error || data.message || 'Failed to save subnet configuration.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }
}

    async function handleEditSubmit(e) {
        e.preventDefault();
        
        // Validate all IPv6 addresses before submitting
        const ipv6Fields = ['edit_pool_start', 'edit_pool_end', 'edit_ccap_core_address'];
        let isValid = true;

        ipv6Fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !validateIPv6Address(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            Swal.fire({
                title: 'Validation Error!',
                text: 'Please correct the IPv6 addresses before submitting.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
    const subnet_id = document.getElementById('edit_subnet_id').value;
        console.log('edit_subnet_id element:', document.getElementById('edit_subnet_id'));
        console.log('edit_switch_id element:', document.getElementById('edit_switch_id'));
        console.log('edit_interface_id element:', document.getElementById('edit_interface_id'));
        console.log('edit_subnet:', document.getElementById('edit_subnet'));

        console.log(document.getElementById('edit_subnet').value);
        const formData = {
            subnet_id: subnet_id,
            switch_id: document.getElementById('edit_switch_id').value,
            subnet: document.getElementById('edit_subnet').value,
            bvi_interface_id: document.getElementById('edit_interface_id').value,
            bvi_interface: document.getElementById('edit_interface').value,
            pool_start: document.getElementById('edit_pool_start').value,
            pool_end: document.getElementById('edit_pool_end').value,
            ccap_core_address: document.getElementById('edit_ccap_core_address').value,
            relay_address: document.getElementById('edit_relay_address').value,
            ipv6_address: document.getElementById('edit_relay_address').value,
            valid_lifetime: parseInt(document.getElementById('edit_valid_lifetime').value) || 7200,
            preferred_lifetime: parseInt(document.getElementById('edit_preferred_lifetime').value) || 3600,
            renew_timer: parseInt(document.getElementById('edit_renew_timer').value) || 1000,
            rebind_timer: parseInt(document.getElementById('edit_rebind_timer').value) || 2000
        };

        console.log('Sending data:', formData); // Add this debug log

        // Create changes summary
        let changesHtml = '<div class="text-left">';
        if (formData.ccap_core_address !== originalFormData.ccap_core_address) {
            changesHtml += `<p><strong>CCAP Core Address:</strong><br>
                <span class="text-red-500">${originalFormData.ccap_core_address}</span> → 
                <span class="text-green-500">${formData.ccap_core_address}</span></p>`;
        }
        if (formData.valid_lifetime !== originalFormData.valid_lifetime) {
            changesHtml += `<p><strong>Valid Lifetime:</strong><br>
                <span class="text-red-500">${originalFormData.valid_lifetime}s</span> → 
                <span class="text-green-500">${formData.valid_lifetime}s</span></p>`;
        }
        if (formData.preferred_lifetime !== originalFormData.preferred_lifetime) {
            changesHtml += `<p><strong>Preferred Lifetime:</strong><br>
                <span class="text-red-500">${originalFormData.preferred_lifetime}s</span> → 
                <span class="text-green-500">${formData.preferred_lifetime}s</span></p>`;
        }
        if (formData.renew_timer !== originalFormData.renew_timer) {
            changesHtml += `<p><strong>Renew Timer (T1):</strong><br>
                <span class="text-red-500">${originalFormData.renew_timer}s</span> → 
                <span class="text-green-500">${formData.renew_timer}s</span></p>`;
        }
        if (formData.rebind_timer !== originalFormData.rebind_timer) {
            changesHtml += `<p><strong>Rebind Timer (T2):</strong><br>
                <span class="text-red-500">${originalFormData.rebind_timer}s</span> → 
                <span class="text-green-500">${formData.rebind_timer}s</span></p>`;
        }
        changesHtml += '</div>';

        // Add confirmation dialog with changes
        const result = await Swal.fire({
            title: 'Review Changes',
            html: changesHtml,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, save changes',
            cancelButtonText: 'Cancel',
            width: '600px'
        });

        if (result.isConfirmed) {
        try {
            const response = await fetch(`/api/dhcp/subnets/${formData.subnet_id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                const modal = document.getElementById('editSubnetModal');
                if (modal) {
                    modal.classList.add('hidden');
                }
                
                // Show success message before reload
                await Swal.fire({
                    title: 'Success!',
                    text: 'Changes have been saved successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                });
                
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to update subnet configuration.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }
}


    async function checkDuplicateSubnet(subnet, subnetId = null) {
    const response = await fetch('/api/dhcp/subnets/check-duplicate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            subnet: subnet,
            subnet_id: subnetId
        })
    });
    return await response.json();
}

    
    // Delete Function
    async function deleteSubnet(subnetId, subnetData) {
        try {
            const subnet = typeof subnetData === 'string' ? JSON.parse(subnetData) : subnetData;
            const result = await Swal.fire({
                title: 'Delete DHCP Subnet?',
                html: `
                    <div class="text-left">
                        <p class="mb-3">You are about to delete subnet: <strong>${subnet}</strong></p>
                        <p class="mb-3 text-red-600 font-semibold">⚠️ This will also delete:</p>
                        <ul class="list-disc list-inside mb-3 text-sm">
                            <li>All pool configurations</li>
                            <li>All DHCP options</li>
                            <li>All active leases</li>
                            <li>All reservations</li>
                        </ul>
                        <p class="mb-3">Type <strong class="text-red-600">I AM SURE!</strong> to confirm:</p>
                        <input type="text" id="delete-subnet-confirmation" class="swal2-input" placeholder="Type: I AM SURE!">
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Delete Subnet',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const confirmation = document.getElementById('delete-subnet-confirmation').value;
                    if (confirmation !== 'I AM SURE!') {
                        Swal.showValidationMessage('Please type "I AM SURE!" exactly to confirm');
                        return false;
                    }
                    return true;
                }
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch(`/api/dhcp/subnets/${subnetId}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                console.log('Delete response:', data);
                console.log('Response status:', response.status);
                
                // Close loading dialog
                Swal.close();
                
                if (data.success) {
                    const result = await Swal.fire({
                        title: 'Success!',
                        text: 'DHCP Subnet and all associated data have been removed successfully.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                    
                    if (result.isConfirmed || result.isDismissed) {
                        window.location.reload();
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || data.error || 'Failed to delete subnet.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            }
        } catch (error) {
            console.error('Delete subnet error:', error);
            Swal.close(); // Close loading dialog on error
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred: ' + error.message,
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }

    async function linkOrphanedSubnet(keaSubnetId, subnetPrefix) {
        try {
            // First, ask user what they want to do
            const { value: action } = await Swal.fire({
                title: 'Link Orphaned Subnet',
                html: `
                    <div class="text-left">
                        <p class="mb-3">Subnet: <strong>${subnetPrefix}</strong></p>
                        <p class="text-sm text-gray-600 mb-4">Choose how to link this subnet:</p>
                    </div>
                `,
                input: 'radio',
                inputOptions: {
                    'link': 'Link to existing BVI interface',
                    'create': 'Create new CIN switch + BVI100'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'You must choose an option!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#3B82F6',
                confirmButtonText: 'Continue'
            });

            if (!action) return;

            if (action === 'link') {
                // Link to existing BVI
                await linkToExistingBVI(keaSubnetId, subnetPrefix);
            } else if (action === 'create') {
                // Create new CIN + BVI
                await createNewCINAndBVI(keaSubnetId, subnetPrefix);
            }

        } catch (error) {
            console.error('Error linking orphaned subnet:', error);
            Swal.fire({
                title: 'Error',
                text: error.message || 'An unexpected error occurred',
                icon: 'error'
            });
        }
    }

    async function linkToExistingBVI(keaSubnetId, subnetPrefix) {
        // Get all BVI interfaces to show in dropdown
        const bviResponse = await fetch('/api/switches/bvi-interfaces');
        const bviData = await bviResponse.json();
        
        if (!bviData.success || !bviData.data || !Array.isArray(bviData.data) || bviData.data.length === 0) {
            Swal.fire({
                title: 'No BVI Interfaces',
                text: 'No BVI interfaces found. Please create a CIN switch with BVI interface first, or use "Create new CIN switch + BVI100" option.',
                icon: 'warning'
            });
            return;
        }

        // Create dropdown options
        const options = {};
        bviData.data.forEach(bvi => {
            const label = `${bvi.switch_name} - BVI ${bvi.interface_number} (${bvi.ipv6_address})`;
            options[bvi.id] = label;
        });

        const { value: bviId } = await Swal.fire({
            title: 'Link to Existing BVI',
            html: `
                <div class="text-left mb-4">
                    <p class="mb-2">Subnet: <strong>${subnetPrefix}</strong></p>
                    <p class="mb-3 text-sm text-gray-600">Select the BVI interface to link this subnet to:</p>
                </div>
            `,
            input: 'select',
            inputOptions: options,
            inputPlaceholder: 'Select a BVI interface',
            showCancelButton: true,
            confirmButtonColor: '#3B82F6',
            confirmButtonText: 'Link Subnet',
            inputValidator: (value) => {
                if (!value) {
                    return 'You must select a BVI interface!';
                }
            }
        });

        if (bviId) {
            // Show loading
            Swal.fire({
                title: 'Linking Subnet...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Link the subnet via API
            const response = await fetch(`/api/dhcp/link-orphaned-subnet`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    kea_subnet_id: keaSubnetId,
                    bvi_interface_id: bviId
                })
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Success!',
                    text: 'Subnet has been linked to BVI interface successfully.',
                    icon: 'success',
                    confirmButtonColor: '#10B981'
                });
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to link subnet',
                    icon: 'error'
                });
            }
        }
    }

    async function createNewCINAndBVI(keaSubnetId, subnetPrefix) {
        const { value: formData } = await Swal.fire({
            title: 'Create New CIN + BVI100',
            html: `
                <div class="text-left">
                    <p class="mb-3">Subnet: <strong>${subnetPrefix}</strong></p>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">CIN Switch Name</label>
                        <input id="cin-name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                               placeholder="e.g., ASD-GT0004-CCAP202">
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">BVI100 IPv6 Address</label>
                        <input id="bvi-ipv6" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                               placeholder="e.g., 2001:b88:8005:f007::1">
                        <p class="text-xs text-gray-500 mt-1">Usually the relay/gateway address from the subnet</p>
                    </div>
                    
                    <div class="bg-blue-50 p-3 rounded mt-3">
                        <p class="text-xs text-gray-600">
                            <strong>Note:</strong> This will create a new CIN switch with BVI100 interface and link the subnet to it.
                        </p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#3B82F6',
            confirmButtonText: 'Create & Link',
            width: '600px',
            preConfirm: () => {
                const cinName = document.getElementById('cin-name').value;
                const bviIpv6 = document.getElementById('bvi-ipv6').value;
                
                if (!cinName) {
                    Swal.showValidationMessage('Please enter a CIN switch name');
                    return false;
                }
                
                if (!bviIpv6) {
                    Swal.showValidationMessage('Please enter a BVI100 IPv6 address');
                    return false;
                }
                
                // Basic IPv6 validation
                if (!/^[0-9a-f:]+$/i.test(bviIpv6)) {
                    Swal.showValidationMessage('Invalid IPv6 address format');
                    return false;
                }
                
                return { cinName, bviIpv6 };
            }
        });

        if (formData) {
            // Show loading
            Swal.fire({
                title: 'Creating CIN + BVI100...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Create CIN + BVI and link subnet via API
            const response = await fetch(`/api/dhcp/create-cin-and-link`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    kea_subnet_id: keaSubnetId,
                    cin_name: formData.cinName,
                    bvi_ipv6: formData.bviIpv6
                })
            });

            const data = await response.json();

            if (data.success) {
                await Swal.fire({
                    title: 'Success!',
                    html: `
                        <p class="mb-2">CIN switch and BVI100 created successfully!</p>
                        <div class="text-sm text-left bg-gray-50 p-3 rounded mt-3">
                            <p><strong>CIN Switch:</strong> ${formData.cinName}</p>
                            <p><strong>BVI100:</strong> ${formData.bviIpv6}</p>
                            <p><strong>Subnet:</strong> ${subnetPrefix}</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#10B981'
                });
                window.location.reload();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to create CIN + BVI',
                    icon: 'error'
                });
            }
        }
    }

    async function deleteOrphanedSubnet(keaSubnetId, subnetData) {
        try {
            const subnet = typeof subnetData === 'string' ? JSON.parse(subnetData) : subnetData;
            const result = await Swal.fire({
                title: 'Delete Orphaned Subnet?',
                html: `
                    <div class="text-left">
                        <p class="mb-3">You are about to delete orphaned subnet: <strong>${subnet}</strong></p>
                        <p class="mb-3 text-red-600 font-semibold">⚠️ This will delete the subnet from Kea.</p>
                        <p class="mb-3">Type <strong class="text-red-600">I AM SURE!</strong> to confirm:</p>
                        <input type="text" id="delete-orphaned-confirmation" class="swal2-input" placeholder="Type: I AM SURE!">
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Delete Subnet',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const confirmation = document.getElementById('delete-orphaned-confirmation').value;
                    if (confirmation !== 'I AM SURE!') {
                        Swal.showValidationMessage('Please type "I AM SURE!" exactly to confirm');
                        return false;
                    }
                    return true;
                }
            });

            if (result.isConfirmed) {
                const response = await fetch(`/api/dhcp/orphaned-subnets/${keaSubnetId}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        title: 'Success!',
                        text: 'Orphaned subnet has been removed successfully from Kea.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.error || 'Failed to delete orphaned subnet.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                }
            }
        } catch (error) {
            Swal.fire({
                title: 'Error!',
                text: 'An unexpected error occurred.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            });
        }
    }

    // Search Function
    function performSearch(searchTerm) {
        const rows = document.querySelectorAll('tbody tr');
        const searchTermLower = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTermLower) ? '' : 'none';
        });
    }

    // Event Listeners
    const ipv6Fields = ['create_subnet', 'create_pool_start', 'create_pool_end', 'create_ccap_core_address',
                       'edit_subnet', 'edit_pool_start', 'edit_pool_end', 'edit_ccap_core_address'];
    
    ipv6Fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', (e) => debouncedValidation(e.target));
        }
    });

    const createForm = document.getElementById('createSubnetForm');
    if (createForm) {
        createForm.addEventListener('submit', handleCreateSubmit);
    }

    const editForm = document.getElementById('editSubnetForm');
    if (editForm) {
        editForm.addEventListener('submit', handleEditSubmit);
    }

    // Make functions available globally
    window.validateIPv6Address = validateIPv6Address;
    window.showCreateSubnetModal = showCreateSubnetModal;
    window.showEditSubnetModal = showEditSubnetModal;
    window.handleCreateSubmit = handleCreateSubmit;
    window.handleEditSubmit = handleEditSubmit;
    window.deleteSubnet = deleteSubnet;
    window.deleteOrphanedSubnet = deleteOrphanedSubnet;
    window.linkOrphanedSubnet = linkOrphanedSubnet;
    window.linkToExistingBVI = linkToExistingBVI;
    window.createNewCINAndBVI = createNewCINAndBVI;
    window.performSearch = performSearch;
});
</script>

</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>