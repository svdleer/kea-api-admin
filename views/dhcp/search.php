<?php
require_once BASE_PATH . '/vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;

// Check if user is logged in
$auth = new Authentication(Database::getInstance());
if (!$auth->isLoggedIn()) {
    header('Location: /');
    exit;
}

$currentPage = 'dhcp';
$subPage = 'search';
$title = 'Advanced Lease Search - RPD Infrastructure Management';
$username = $_SESSION['user_name'] ?? 'User';
$isAdmin = $_SESSION['is_admin'] ?? false;

ob_start();
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="px-4 py-6 sm:px-0">
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                Advanced Lease Search
            </h2>
            <p class="text-gray-600">
                Search and filter DHCPv6 leases using multiple criteria
            </p>
        </div>
    </div>

    <!-- Search Filters -->
    <div class="px-4 sm:px-0 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Search Filters</h3>
            
            <form id="searchForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- IPv6 Address -->
                    <div>
                        <label for="ipv6_address" class="block text-sm font-medium text-gray-700 mb-2">
                            IPv6 Address
                        </label>
                        <input type="text" 
                               id="ipv6_address" 
                               name="ipv6_address"
                               placeholder="2001:db8::1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- MAC Address / DUID -->
                    <div>
                        <label for="duid" class="block text-sm font-medium text-gray-700 mb-2">
                            MAC Address / DUID
                        </label>
                        <input type="text" 
                               id="duid" 
                               name="duid"
                               placeholder="00:11:22:33:44:55 or 00:03:00:01:00:11:22:33:44:55"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p id="duid_validation" class="mt-1 text-sm hidden"></p>
                    </div>

                    <!-- Hostname -->
                    <div>
                        <label for="hostname" class="block text-sm font-medium text-gray-700 mb-2">
                            Hostname
                        </label>
                        <input type="text" 
                               id="hostname" 
                               name="hostname"
                               placeholder="device-name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Switch -->
                    <div>
                        <label for="switch_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Switch
                        </label>
                        <select id="switch_id" 
                                name="switch_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Switches</option>
                        </select>
                    </div>

                    <!-- BVI Interface -->
                    <div>
                        <label for="bvi_id" class="block text-sm font-medium text-gray-700 mb-2">
                            BVI Interface
                        </label>
                        <select id="bvi_id" 
                                name="bvi_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All BVIs</option>
                        </select>
                    </div>

                    <!-- Subnet -->
                    <div>
                        <label for="subnet_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Subnet
                        </label>
                        <select id="subnet_id" 
                                name="subnet_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Subnets</option>
                        </select>
                    </div>

                    <!-- Lease Type -->
                    <div>
                        <label for="lease_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Lease Type
                        </label>
                        <select id="lease_type" 
                                name="lease_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Types</option>
                            <option value="0">Dynamic (IA_NA)</option>
                            <option value="2">Prefix Delegation (IA_PD)</option>
                        </select>
                    </div>

                    <!-- State -->
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700 mb-2">
                            State
                        </label>
                        <select id="state" 
                                name="state"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">All States</option>
                            <option value="0">Active</option>
                            <option value="1">Declined</option>
                            <option value="2">Expired</option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">
                            Valid From
                        </label>
                        <input type="datetime-local" 
                               id="date_from" 
                               name="date_from"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Date To -->
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">
                            Valid Until
                        </label>
                        <input type="datetime-local" 
                               id="date_to" 
                               name="date_to"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center pt-4">
                    <button type="button" 
                            id="clearFilters"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Clear Filters
                    </button>
                    <div class="flex space-x-3">
                        <button type="button" 
                                id="exportResults"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Export CSV
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="hidden text-center py-8">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-2 text-gray-600">Searching...</p>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" class="hidden px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    Search Results: <span id="resultCount" class="text-indigo-600">0</span> leases found
                </h3>
                <div class="text-sm text-gray-500">
                    Search time: <span id="searchTime" class="font-medium">0ms</span>
                </div>
            </div>

            <!-- Results Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IPv6 Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DUID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Switch / BVI</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Results will be inserted here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pagination" class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-700">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalResults">0</span> results
                </div>
                <div class="flex space-x-2">
                    <button id="prevPage" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50" disabled>
                        Previous
                    </button>
                    <button id="nextPage" class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50" disabled>
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- No Results Message -->
    <div id="noResults" class="hidden px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-sm p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No leases found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your search filters</p>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let pageSize = 50;
let searchStartTime = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadSwitches();
    loadSubnets();
    
    // DUID/MAC validation
    const duidInput = document.getElementById('duid');
    const duidValidation = document.getElementById('duid_validation');
    
    duidInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (!value) {
            duidInput.classList.remove('border-red-500', 'border-green-500');
            duidValidation.classList.add('hidden');
            return;
        }
        
        // Normalize: remove dots, dashes, spaces
        const normalized = value.replace(/[.\-\s]/g, ':').toLowerCase();
        const parts = normalized.split(':').filter(p => p.length > 0);
        
        let isValid = false;
        let message = '';
        
        // Check if it's a valid MAC address (6 octets)
        if (parts.length === 6) {
            isValid = parts.every(p => /^[0-9a-f]{2}$/.test(p));
            if (isValid) {
                message = '✓ Valid MAC address (will be converted to DUID)';
                duidInput.classList.remove('border-red-500');
                duidInput.classList.add('border-green-500');
                duidValidation.className = 'mt-1 text-sm text-green-600';
            } else {
                message = '✗ Invalid MAC address format';
                duidInput.classList.remove('border-green-500');
                duidInput.classList.add('border-red-500');
                duidValidation.className = 'mt-1 text-sm text-red-600';
            }
        }
        // Check if it's a valid DUID (10 octets: 00:03:00:01 + MAC)
        else if (parts.length === 10) {
            const isDuidLL = parts[0] === '00' && parts[1] === '03' && parts[2] === '00' && parts[3] === '01';
            const macValid = parts.slice(4).every(p => /^[0-9a-f]{2}$/.test(p));
            isValid = isDuidLL && macValid;
            
            if (isValid) {
                message = '✓ Valid DUID-LL format';
                duidInput.classList.remove('border-red-500');
                duidInput.classList.add('border-green-500');
                duidValidation.className = 'mt-1 text-sm text-green-600';
            } else {
                message = '✗ Invalid DUID format (expected 00:03:00:01:MAC)';
                duidInput.classList.remove('border-green-500');
                duidInput.classList.add('border-red-500');
                duidValidation.className = 'mt-1 text-sm text-red-600';
            }
        }
        else {
            message = '✗ Enter MAC (6 octets) or DUID (10 octets)';
            duidInput.classList.remove('border-green-500');
            duidInput.classList.add('border-red-500');
            duidValidation.className = 'mt-1 text-sm text-red-600';
        }
        
        duidValidation.textContent = message;
        duidValidation.classList.remove('hidden');
    });
    
    // Load BVIs when switch is selected
    document.getElementById('switch_id').addEventListener('change', function() {
        loadBVIs(this.value);
    });
    
    // Search form submit
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        performSearch();
    });
    
    // Clear filters
    document.getElementById('clearFilters').addEventListener('click', function() {
        document.getElementById('searchForm').reset();
        document.getElementById('resultsSection').classList.add('hidden');
        document.getElementById('noResults').classList.add('hidden');
    });
    
    // Export results
    document.getElementById('exportResults').addEventListener('click', function() {
        exportToCSV();
    });
    
    // Pagination
    document.getElementById('prevPage').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            performSearch();
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', function() {
        currentPage++;
        performSearch();
    });
});

async function loadSwitches() {
    try {
        const response = await fetch('/api/switches');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('switch_id');
            data.data.forEach(sw => {
                const option = document.createElement('option');
                option.value = sw.id;
                option.textContent = sw.hostname;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading switches:', error);
    }
}

async function loadBVIs(switchId) {
    const bviSelect = document.getElementById('bvi_id');
    bviSelect.innerHTML = '<option value="">All BVIs</option>';
    
    if (!switchId) return;
    
    try {
        const response = await fetch(`/api/switches/${switchId}/bvi`);
        const data = await response.json();
        
        if (data.success) {
            data.data.forEach(bvi => {
                const option = document.createElement('option');
                option.value = bvi.id;
                option.textContent = `BVI${bvi.interface_number} (${bvi.ipv6_address})`;
                bviSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading BVIs:', error);
    }
}

async function loadSubnets() {
    try {
        const response = await fetch('/api/dhcp/subnets');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('subnet_id');
            data.data.forEach(subnet => {
                const option = document.createElement('option');
                option.value = subnet.subnet_id;
                option.textContent = `${subnet.subnet} (${subnet.name || 'Unnamed'})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading subnets:', error);
    }
}

async function performSearch() {
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    // Build query parameters
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    params.append('page', currentPage);
    params.append('page_size', pageSize);
    
    // Show loading
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('resultsSection').classList.add('hidden');
    document.getElementById('noResults').classList.add('hidden');
    
    searchStartTime = performance.now();
    
    try {
        const response = await fetch(`/api/dhcp/search-leases?${params.toString()}`);
        const data = await response.json();
        
        const searchTime = Math.round(performance.now() - searchStartTime);
        
        document.getElementById('loadingSpinner').classList.add('hidden');
        
        if (data.success && data.data && data.data.data && data.data.data.length > 0) {
            displayResults(data.data.data, data.data.total, searchTime);
        } else {
            document.getElementById('noResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Search error:', error);
        document.getElementById('loadingSpinner').classList.add('hidden');
        alert('Search failed: ' + error.message);
    }
}

function displayResults(leases, total, searchTime) {
    const tbody = document.getElementById('resultsTableBody');
    tbody.innerHTML = '';
    
    leases.forEach(lease => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        
        const leaseType = lease.lease_type == 0 ? 'IA_NA' : lease.lease_type == 2 ? 'IA_PD' : 'Unknown';
        const stateText = lease.state == 0 ? 'Active' : lease.state == 1 ? 'Declined' : 'Expired';
        const stateClass = lease.state == 0 ? 'text-green-600' : 'text-red-600';
        
        const macAddress = lease.hwaddr ? lease.hwaddr.replace(/^01:00:00:00:00:00:/, '').toUpperCase() : 'N/A';
        
        const switchBviText = lease.switch_hostname ? 
            `${escapeHtml(lease.switch_hostname)} / BVI${lease.bvi_number}` : 
            `Subnet ${lease.subnet_id}`;
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">${escapeHtml(lease.address)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">${escapeHtml(lease.duid ? lease.duid.substring(0, 20) + '...' : 'N/A')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${switchBviText}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatDate(lease.expire)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${leaseType}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm ${stateClass} font-medium">${stateText}</td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Update stats
    document.getElementById('resultCount').textContent = total;
    document.getElementById('searchTime').textContent = searchTime + 'ms';
    
    const from = (currentPage - 1) * pageSize + 1;
    const to = Math.min(currentPage * pageSize, total);
    document.getElementById('showingFrom').textContent = from;
    document.getElementById('showingTo').textContent = to;
    document.getElementById('totalResults').textContent = total;
    
    // Update pagination buttons
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = to >= total;
    
    document.getElementById('resultsSection').classList.remove('hidden');
}

function formatDate(timestamp) {
    if (!timestamp) return 'N/A';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function exportToCSV() {
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    params.append('export', 'csv');
    
    window.location.href = `/api/dhcp/search-leases?${params.toString()}`;
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout.php';
?>
