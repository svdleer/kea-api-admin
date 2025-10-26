<?php
$title = 'Kea Configuration Import Wizard';
$currentPage = 'admin-tools';
ob_start();
?>

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Kea Configuration Import Wizard</h1>
        <p class="mt-2 text-sm text-gray-600">Review and configure subnet import from kea-dhcp6.conf</p>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center flex-1">
                <div class="flex items-center text-blue-600">
                    <div class="rounded-full h-12 w-12 flex items-center justify-center border-2 border-blue-600 bg-blue-50">
                        <span class="text-xl font-semibold">1</span>
                    </div>
                    <span class="ml-3 text-sm font-medium">Upload & Parse</span>
                </div>
                <div class="flex-1 h-1 mx-4 bg-gray-200">
                    <div id="progress-bar-1" class="h-full bg-blue-600 transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
            <div class="flex items-center flex-1">
                <div id="step-2-indicator" class="flex items-center text-gray-400">
                    <div class="rounded-full h-12 w-12 flex items-center justify-center border-2 border-gray-300 bg-white">
                        <span class="text-xl font-semibold">2</span>
                    </div>
                    <span class="ml-3 text-sm font-medium">Review & Configure</span>
                </div>
                <div class="flex-1 h-1 mx-4 bg-gray-200">
                    <div id="progress-bar-2" class="h-full bg-blue-600 transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
            <div class="flex items-center">
                <div id="step-3-indicator" class="flex items-center text-gray-400">
                    <div class="rounded-full h-12 w-12 flex items-center justify-center border-2 border-gray-300 bg-white">
                        <span class="text-xl font-semibold">3</span>
                    </div>
                    <span class="ml-3 text-sm font-medium">Import Complete</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 1: Upload -->
    <div id="step-1" class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Step 1: Upload Configuration File</h2>
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
            <input type="file" id="config-file-input" accept=".conf,.json" class="hidden">
            <button onclick="document.getElementById('config-file-input').click()" 
                    class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                Choose File
            </button>
            <p class="mt-4 text-sm text-gray-600">Upload your kea-dhcp6.conf file</p>
            <div id="file-info" class="mt-4 hidden">
                <p class="text-sm font-medium text-gray-900" id="file-name"></p>
                <p class="text-xs text-gray-500" id="file-size"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button id="parse-btn" onclick="parseConfig()" disabled
                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                Parse Configuration
            </button>
        </div>
    </div>

    <!-- Step 2: Review & Configure (Hidden Initially) -->
    <div id="step-2" class="bg-white shadow rounded-lg p-6 hidden">
        <h2 class="text-xl font-semibold mb-4">Step 2: Review & Configure Subnets</h2>
        
        <div class="mb-4 flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Found <span id="subnet-count" class="font-semibold">0</span> subnets</p>
            </div>
            <div class="space-x-2">
                <button onclick="selectAllSubnets()" class="text-sm text-blue-600 hover:text-blue-800">Select All</button>
                <button onclick="deselectAllSubnets()" class="text-sm text-blue-600 hover:text-blue-800">Deselect All</button>
            </div>
        </div>

        <div id="subnets-table" class="overflow-x-auto">
            <!-- Table will be populated by JavaScript -->
        </div>

        <div class="mt-6 flex justify-between">
            <button onclick="showStep(1)" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400">
                Back
            </button>
            <button onclick="executeImport()" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                Execute Import
            </button>
        </div>
    </div>

    <!-- Step 3: Complete (Hidden Initially) -->
    <div id="step-3" class="bg-white shadow rounded-lg p-6 hidden">
        <div class="text-center py-8">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Import Complete!</h2>
            <div id="import-results" class="mt-6 text-left max-w-2xl mx-auto">
                <!-- Results will be populated by JavaScript -->
            </div>
            <div class="mt-8">
                <a href="/dhcp" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 inline-block">
                    View DHCPv6 Subnets
                </a>
                <a href="/admin/tools" class="ml-4 bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 inline-block">
                    Back to Admin Tools
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let parsedConfig = null;
let selectedFile = null;

// File input handler
document.getElementById('config-file-input').addEventListener('change', function(e) {
    selectedFile = e.target.files[0];
    if (selectedFile) {
        document.getElementById('file-name').textContent = selectedFile.name;
        document.getElementById('file-size').textContent = (selectedFile.size / 1024).toFixed(2) + ' KB';
        document.getElementById('file-info').classList.remove('hidden');
        document.getElementById('parse-btn').disabled = false;
    }
});

async function parseConfig() {
    if (!selectedFile) return;

    const formData = new FormData();
    formData.append('config', selectedFile);

    try {
        // Show loading
        Swal.fire({
            title: 'Parsing Configuration...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/api/admin/import/kea-config/preview', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            parsedConfig = result.subnets;
            displaySubnets(result.subnets);
            Swal.close();
            showStep(2);
            updateProgress(1, 100);
        } else {
            Swal.fire('Error', result.message || 'Failed to parse configuration', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Failed to parse configuration: ' + error.message, 'error');
    }
}

function displaySubnets(subnets) {
    document.getElementById('subnet-count').textContent = subnets.length;
    
    const table = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        <input type="checkbox" id="select-all" onclick="toggleAllSubnets(this)">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subnet</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pool</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Relay</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CCAP Core</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${subnets.map((subnet, index) => `
                    <tr>
                        <td class="px-4 py-4">
                            <input type="checkbox" class="subnet-checkbox" data-index="${index}" checked>
                        </td>
                        <td class="px-4 py-4 text-sm font-medium text-gray-900">${subnet.subnet}</td>
                        <td class="px-4 py-4 text-sm text-gray-500">${subnet.pool || 'N/A'}</td>
                        <td class="px-4 py-4 text-sm text-gray-500">${subnet.relay || 'N/A'}</td>
                        <td class="px-4 py-4 text-sm text-gray-500">${subnet.ccap_core || 'N/A'}</td>
                        <td class="px-4 py-4">
                            <select class="subnet-action text-sm border-gray-300 rounded-md" data-index="${index}">
                                <option value="create">Create New CIN + BVI</option>
                                <option value="link">Link to Existing BVI</option>
                                <option value="skip">Skip</option>
                            </select>
                            <div class="mt-2 hidden" id="bvi-select-${index}">
                                <select class="text-sm border-gray-300 rounded-md w-full">
                                    <option value="">Select BVI Interface...</option>
                                    <!-- Will be populated -->
                                </select>
                            </div>
                            <div class="mt-2 hidden" id="cin-input-${index}">
                                <input type="text" placeholder="CIN Switch Name" 
                                       class="cin-name text-sm border-gray-300 rounded-md w-full" data-index="${index}">
                                <input type="text" placeholder="BVI Interface #" 
                                       class="bvi-number text-sm border-gray-300 rounded-md w-full mt-1" data-index="${index}">
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    document.getElementById('subnets-table').innerHTML = table;
    
    // Add action change handlers
    document.querySelectorAll('.subnet-action').forEach(select => {
        select.addEventListener('change', function() {
            const index = this.dataset.index;
            const value = this.value;
            
            document.getElementById(`bvi-select-${index}`).classList.add('hidden');
            document.getElementById(`cin-input-${index}`).classList.add('hidden');
            
            if (value === 'link') {
                document.getElementById(`bvi-select-${index}`).classList.remove('hidden');
                loadBVIInterfaces(index);
            } else if (value === 'create') {
                document.getElementById(`cin-input-${index}`).classList.remove('hidden');
            }
        });
    });
}

async function loadBVIInterfaces(index) {
    // TODO: Load BVI interfaces from API
    // For now, placeholder
}

function toggleAllSubnets(checkbox) {
    document.querySelectorAll('.subnet-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function selectAllSubnets() {
    document.getElementById('select-all').checked = true;
    toggleAllSubnets(document.getElementById('select-all'));
}

function deselectAllSubnets() {
    document.getElementById('select-all').checked = false;
    toggleAllSubnets(document.getElementById('select-all'));
}

async function executeImport() {
    const subnetsToImport = [];
    
    document.querySelectorAll('.subnet-checkbox:checked').forEach(checkbox => {
        const index = checkbox.dataset.index;
        const subnet = parsedConfig[index];
        const action = document.querySelector(`.subnet-action[data-index="${index}"]`).value;
        
        const importConfig = {
            subnet: subnet,
            action: action
        };
        
        if (action === 'create') {
            importConfig.cin_name = document.querySelector(`.cin-name[data-index="${index}"]`).value;
            importConfig.bvi_number = document.querySelector(`.bvi-number[data-index="${index}"]`).value;
        } else if (action === 'link') {
            importConfig.bvi_id = document.querySelector(`#bvi-select-${index} select`).value;
        }
        
        subnetsToImport.push(importConfig);
    });
    
    if (subnetsToImport.length === 0) {
        Swal.fire('Warning', 'Please select at least one subnet to import', 'warning');
        return;
    }
    
    try {
        Swal.fire({
            title: 'Importing Subnets...',
            text: `Importing ${subnetsToImport.length} subnet(s)`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const response = await fetch('/api/admin/import/kea-config/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ subnets: subnetsToImport })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayResults(result);
            Swal.close();
            showStep(3);
            updateProgress(2, 100);
        } else {
            Swal.fire('Error', result.message || 'Import failed', 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Import failed: ' + error.message, 'error');
    }
}

function displayResults(result) {
    const html = `
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-3xl font-bold text-green-600">${result.imported || 0}</div>
                    <div class="text-sm text-gray-600">Imported</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-yellow-600">${result.skipped || 0}</div>
                    <div class="text-sm text-gray-600">Skipped</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-red-600">${result.errors || 0}</div>
                    <div class="text-sm text-gray-600">Errors</div>
                </div>
            </div>
            ${result.details ? `
                <div class="mt-4 text-sm text-left">
                    <details>
                        <summary class="cursor-pointer text-gray-600 hover:text-gray-800">View Details</summary>
                        <pre class="mt-2 bg-white p-2 rounded text-xs overflow-auto max-h-64">${JSON.stringify(result.details, null, 2)}</pre>
                    </details>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('import-results').innerHTML = html;
}

function showStep(step) {
    // Hide all steps
    document.getElementById('step-1').classList.add('hidden');
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-3').classList.add('hidden');
    
    // Show selected step
    document.getElementById(`step-${step}`).classList.remove('hidden');
    
    // Update step indicators
    updateStepIndicator(1, step >= 1);
    updateStepIndicator(2, step >= 2);
    updateStepIndicator(3, step >= 3);
}

function updateStepIndicator(stepNum, active) {
    const indicator = document.getElementById(`step-${stepNum}-indicator`);
    if (active) {
        indicator.classList.remove('text-gray-400');
        indicator.classList.add('text-blue-600');
        indicator.querySelector('div').classList.remove('border-gray-300', 'bg-white');
        indicator.querySelector('div').classList.add('border-blue-600', 'bg-blue-50');
    }
}

function updateProgress(barNum, percent) {
    document.getElementById(`progress-bar-${barNum}`).style.width = percent + '%';
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layout.php';
?>
