<?php
$pageTitle = 'Import RADIUS Clients';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Import RADIUS Clients
            </h2>
            <p class="mt-1 text-sm text-gray-500">Upload a FreeRADIUS clients.conf file to import NAS clients</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="/admin/radius" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Back to Clients
            </a>
        </div>
    </div>

    <!-- Import Form -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900">Upload clients.conf File</h3>
                <p class="mt-2 text-sm text-gray-500">
                    Select a FreeRADIUS clients.conf file to import. The file should contain client blocks with ipaddr, secret, and optional shortname fields.
                </p>
                <p class="mt-1 text-sm text-gray-600">
                    <strong>Note:</strong> Since NAS client IPs are BVI interface addresses, this will also create corresponding BVI interface entries.
                </p>
            </div>

            <form id="importForm" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="clients_conf" class="block text-sm font-medium text-gray-700">
                        clients.conf File
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="clients_conf" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                    <span>Upload a file</span>
                                    <input id="clients_conf" name="clients_conf" type="file" class="sr-only" accept=".conf" required>
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">clients.conf file</p>
                        </div>
                    </div>
                    <p id="fileName" class="mt-2 text-sm text-gray-500"></p>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="/admin/radius" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" id="importBtn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Import Clients
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Example Format -->
    <div class="mt-6 bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Expected File Format</h3>
            <pre class="bg-gray-50 border border-gray-200 rounded-md p-4 text-sm overflow-x-auto"><code>client switch1 {
    ipaddr = 192.168.1.10
    secret = mysecret123
    shortname = Office Switch
    nastype = other
}

client switch2 {
    ipaddr = 192.168.1.20
    secret = anothersecret
    shortname = Warehouse Switch
}</code></pre>
        </div>
    </div>

    <!-- Result Message -->
    <div id="resultMessage" class="mt-6 hidden">
        <div class="rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg id="successIcon" class="h-5 w-5 text-green-400 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <svg id="errorIcon" class="h-5 w-5 text-red-400 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p id="resultText" class="text-sm font-medium"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="hidden fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
            <div>
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                    <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Confirm Import
                    </h3>
                    <div class="mt-2">
                        <p id="modalMessage" class="text-sm text-gray-500"></p>
                    </div>
                </div>
            </div>
            
            <div class="mt-5">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Review clients (uncheck to skip, click names to edit):</h4>
                <div class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto">
                    <div id="clientsList" class="space-y-2"></div>
                </div>
            </div>
            
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                <button type="button" onclick="saveEdits()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                    Confirm & Import
                </button>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let importedClientsData = [];

function closeModal() {
    document.getElementById('successModal').classList.add('hidden');
    document.getElementById('importForm').reset();
    document.getElementById('fileName').textContent = '';
    importedClientsData = [];
}

async function saveEdits() {
    const selectedClients = [];
    
    importedClientsData.forEach((client, index) => {
        const checkbox = document.getElementById(`client-keep-${index}`);
        const inputElement = document.getElementById(`client-name-${index}`);
        
        // Only include checked clients
        if (checkbox.checked) {
            selectedClients.push({
                name: inputElement.value.trim(),
                ip: client.ip,
                switch: client.switch,
                secret: client.secret
            });
        }
    });
    
    if (selectedClients.length === 0) {
        alert('Please select at least one client to import');
        return;
    }
    
    // Perform the actual import with selected clients
    try {
        const response = await fetch('/radius/confirm-import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ clients: selectedClients })
        });
        
        let result;
        try {
            result = await response.json();
        } catch (jsonError) {
            const text = await response.text();
            console.error('JSON parse error:', jsonError);
            console.error('Response text:', text);
            alert('Invalid response from server. Check console for details.');
            return;
        }
        
        if (!result.success) {
            alert('Import failed: ' + result.message);
            return;
        }
        
        // Show success and redirect
        alert(result.message || 'Import completed successfully');
        window.location.href = '/radius';
        
    } catch (error) {
        console.error('Import error:', error);
        alert('Error during import: ' + error.message);
    }
}

document.getElementById('clients_conf').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    if (fileName) {
        document.getElementById('fileName').textContent = 'Selected: ' + fileName;
    }
});

document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const importBtn = document.getElementById('importBtn');
    const resultMessage = document.getElementById('resultMessage');
    const resultText = document.getElementById('resultText');
    const successIcon = document.getElementById('successIcon');
    const errorIcon = document.getElementById('errorIcon');
    
    importBtn.disabled = true;
    importBtn.textContent = 'Importing...';
    resultMessage.classList.add('hidden');
    
    try {
        const response = await fetch('/radius/import', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success modal with client details
            const modalMessage = document.getElementById('modalMessage');
            const clientsList = document.getElementById('clientsList');
            
            modalMessage.textContent = result.message;
            
            // Store client data for editing
            importedClientsData = result.clients || [];
            
            // Clear and populate clients list with editable names
            clientsList.innerHTML = '';
            if (importedClientsData.length > 0) {
                importedClientsData.forEach((client, index) => {
                    const clientDiv = document.createElement('div');
                    clientDiv.className = 'flex items-center justify-between p-3 bg-white rounded border border-gray-200';
                    clientDiv.innerHTML = `
                        <div class="flex items-center flex-1">
                            <input 
                                type="checkbox" 
                                id="client-keep-${index}"
                                checked
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mr-3"
                            />
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <input 
                                        type="text" 
                                        id="client-name-${index}"
                                        value="${client.name}" 
                                        class="font-medium text-gray-900 border-0 border-b-2 border-transparent hover:border-indigo-300 focus:border-indigo-500 focus:ring-0 bg-transparent px-2 py-1 -ml-2 flex-1"
                                        placeholder="Client name"
                                    />
                                </div>
                                <div class="mt-1 text-sm text-gray-500 ml-7">
                                    Switch: ${client.switch} | IP: ${client.ip}
                                </div>
                            </div>
                        </div>
                    `;
                    clientsList.appendChild(clientDiv);
                });
            }
            
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            resultMessage.classList.remove('hidden');
            successIcon.classList.add('hidden');
            errorIcon.classList.remove('hidden');
            resultMessage.querySelector('div').className = 'rounded-md bg-red-50 p-4';
            resultText.className = 'text-sm font-medium text-red-800';
            resultText.textContent = result.message;
            
            if (result.errors && result.errors.length > 0) {
                resultText.textContent += '\n\nErrors:\n' + result.errors.join('\n');
            }
        }
    } catch (error) {
        resultMessage.classList.remove('hidden');
        successIcon.classList.add('hidden');
        errorIcon.classList.remove('hidden');
        resultMessage.querySelector('div').className = 'rounded-md bg-red-50 p-4';
        resultText.className = 'text-sm font-medium text-red-800';
        resultText.textContent = 'Error: ' + error.message;
    } finally {
        importBtn.disabled = false;
        importBtn.textContent = 'Import Clients';
    }
});
</script>
