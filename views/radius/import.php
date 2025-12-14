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

<script>
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
        
        resultMessage.classList.remove('hidden');
        
        if (result.success) {
            successIcon.classList.remove('hidden');
            errorIcon.classList.add('hidden');
            resultMessage.querySelector('div').className = 'rounded-md bg-green-50 p-4';
            resultText.className = 'text-sm font-medium text-green-800';
            resultText.textContent = result.message;
            
            // Redirect after 2 seconds
            setTimeout(() => {
                window.location.href = '/admin/radius';
            }, 2000);
        } else {
            successIcon.classList.add('hidden');
            errorIcon.classList.remove('hidden');
            resultMessage.querySelector('div').className = 'rounded-md bg-red-50 p-4';
            resultText.className = 'text-sm font-medium text-red-800';
            resultText.textContent = result.message;
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
