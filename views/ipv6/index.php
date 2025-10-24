<?php
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">IPv6 Subnet Management</h1>
        <button onclick="showCreateSubnetModal()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add IPv6 Subnet
        </button>
    </div>

    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Prefix
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        BVI Interface
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Pool Range
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="subnets-table-body">
                <!-- Subnets will be loaded here dynamically -->
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSubnets();
    loadBVIInterfaces();
});

let bviInterfaces = [];

function loadBVIInterfaces() {
    fetch('/api/switches')
        .then(response => response.json())
        .then(data => {
            bviInterfaces = data.switches;
        })
        .catch(error => {
            console.error('Error loading BVI interfaces:', error);
        });
}

function loadSubnets() {
    fetch('/api/ipv6/subnets')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('subnets-table-body');
            tbody.innerHTML = '';
            
            if (!data.subnets || data.subnets.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-5 py-5 text-center text-gray-500">No subnets found</td></tr>';
                return;
            }
            
            data.subnets.forEach(subnet => {
                const tr = document.createElement('tr');
                const bviInfo = subnet.bvi_interface ? `BVI${subnet.bvi_interface}` : 'N/A';
                const ipv6Info = subnet.ipv6_address ? `(${subnet.ipv6_address})` : '';
                
                tr.innerHTML = `
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">${subnet.ccap_core || 'N/A'}</p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">${subnet.subnet || 'N/A'}</p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">${bviInfo} ${ipv6Info}</p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">
                            ${subnet.pool && subnet.pool.start ? `${subnet.pool.start} - ${subnet.pool.end}` : 'N/A'}
                        </p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight">
                            <span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"></span>
                            <span class="relative">Active</span>
                        </span>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <button onclick="editSubnet(${subnet.id})" class="text-blue-600 hover:text-blue-900 mr-4">Edit</button>
                        <button onclick="deleteSubnet(${subnet.id})" class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to load IPv6 subnets: ' + error.message, 'error');
            console.error('Error:', error);
        });
}

function showCreateSubnetModal() {
    const bviOptions = bviInterfaces.map(bvi => 
        `<option value="${bvi.id}">${bvi.name} (${bvi.ipv6_address})</option>`
    ).join('');

    Swal.fire({
        title: 'Create New IPv6 Subnet',
        html: `
            <input id="name" class="swal2-input" placeholder="Subnet Name">
            <input id="prefix" class="swal2-input" placeholder="IPv6 Prefix (e.g., 2001:db8::/64)">
            <select id="bvi_id" class="swal2-select">
                <option value="">Select BVI Interface</option>
                ${bviOptions}
            </select>
            <textarea id="description" class="swal2-textarea" placeholder="Description"></textarea>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Create',
        preConfirm: () => {
            return {
                name: document.getElementById('name').value,
                prefix: document.getElementById('prefix').value,
                bvi_id: document.getElementById('bvi_id').value,
                description: document.getElementById('description').value
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            createSubnet(result.value);
        }
    });
}

function createSubnet(subnetData) {
    fetch('/api/ipv6/subnets', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(subnetData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        Swal.fire('Success', 'IPv6 subnet created successfully', 'success');
        loadSubnets();
    })
    .catch(error => {
        Swal.fire('Error', error.message, 'error');
    });
}

function editSubnet(subnetId) {
    // First, fetch the current subnet data
    fetch(`/api/ipv6/subnets/${subnetId}`)
        .then(response => response.json())
        .then(subnet => {
            const bviOptions = bviInterfaces.map(bvi => 
                `<option value="${bvi.id}" ${bvi.id === subnet.bvi_id ? 'selected' : ''}>
                    ${bvi.name} (${bvi.ipv6_address})
                </option>`
            ).join('');

            Swal.fire({
                title: 'Edit IPv6 Subnet',
                html: `
                    <input id="name" class="swal2-input" value="${subnet.name}" placeholder="Subnet Name">
                    <input id="prefix" class="swal2-input" value="${subnet.prefix}" placeholder="IPv6 Prefix">
                    <select id="bvi_id" class="swal2-select">
                        ${bviOptions}
                    </select>
                    <textarea id="description" class="swal2-textarea" placeholder="Description">${subnet.description || ''}</textarea>
                    <div class="flex items-center mt-4">
                        <input id="active" type="checkbox" class="form-checkbox" ${subnet.active ? 'checked' : ''}>
                        <label for="active" class="ml-2">Active</label>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Update',
                preConfirm: () => {
                    return {
                        name: document.getElementById('name').value,
                        prefix: document.getElementById('prefix').value,
                        bvi_id: document.getElementById('bvi_id').value,
                        description: document.getElementById('description').value,
                        active: document.getElementById('active').checked
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateSubnet(subnetId, result.value);
                }
            });
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to load subnet data', 'error');
        });
}

function updateSubnet(subnetId, subnetData) {
    fetch(`/api/ipv6/subnets/${subnetId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(subnetData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        Swal.fire('Success', 'IPv6 subnet updated successfully', 'success');
        loadSubnets();
    })
    .catch(error => {
        Swal.fire('Error', error.message, 'error');
    });
}

function deleteSubnet(subnetId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/ipv6/subnets/${subnetId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                Swal.fire('Deleted!', 'IPv6 subnet has been deleted.', 'success');
                loadSubnets();
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>