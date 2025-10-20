<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Network\Prefix;
use App\Database\Database;

$db = Database::getInstance();
$prefix = new Prefix($db);

$prefixes = $prefix->getAllPrefixes();

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
    <title>Prefix Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/forms.css">
    <style>
        .prefix-card {
            transition: all 0.3s ease;
        }
        .prefix-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .delete-animation {
            animation: fadeOut 0.3s ease-in-out;
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-20px); }
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #DEF7EC;
            color: #03543F;
        }
        .status-inactive {
            background-color: #FDE8E8;
            color: #9B1C1C;
        }
        .search-highlight {
            background-color: #FEFCE8;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <p class="mt-4 text-gray-700">Processing...</p>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                <h3 class="text-lg font-bold mb-4">Confirm Deletion</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this prefix? This action cannot be undone.</p>
                <div class="flex justify-end space-x-4">
                    <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                    <button id="confirmDelete" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div class="mb-4 md:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900">Prefix Management</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Manage and monitor your network prefixes
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search prefixes..." 
                               class="form-input pl-10">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <a href="/prefixes/add" class="btn btn-primary">
                        Add New Prefix
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($error || $success): ?>
                <div id="alertMessage" 
                     class="mb-6 p-4 rounded-lg <?= $error ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                    <?= htmlspecialchars($error ?? $success) ?>
                </div>
            <?php endif; ?>

            <!-- Prefix List -->
            <div class="grid gap-6 mb-8">
                <?php if (empty($prefixes)): ?>
                    <div class="bg-white p-6 rounded-lg shadow text-center">
                        <p class="text-gray-600">No prefixes found.</p>
                        <a href="/prefixes/add" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                            Add your first prefix
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($prefixes as $prefix): ?>
                        <div class="prefix-card bg-white rounded-lg shadow-sm" data-prefix-id="<?= $prefix['id'] ?>">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div class="mb-4 md:mb-0">
                                        <div class="flex items-center space-x-3">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($prefix['prefix']) ?>
                                            </h3>
                                            <span class="status-badge <?= $prefix['active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $prefix['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-600">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <p><span class="font-medium">Subnet:</span> <?= htmlspecialchars($prefix['subnet_name']) ?></p>
                                                    <p><span class="font-medium">Description:</span> <?= htmlspecialchars($prefix['description'] ?: 'N/A') ?></p>
                                                </div>
                                                <div>
                                                    <p><span class="font-medium">Created:</span> <?= date('Y-m-d H:i', strtotime($prefix['created_at'])) ?></p>
                                                    <p><span class="font-medium">Last Updated:</span> <?= date('Y-m-d H:i', strtotime($prefix['updated_at'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="/prefixes/edit?id=<?= $prefix['id'] ?>" 
                                           class="btn btn-secondary">
                                            Edit
                                        </a>
                                        <button onclick="showDeleteModal(<?= $prefix['id'] ?>)"
                                                class="btn btn-danger">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let prefixToDelete = null;

        function showDeleteModal(prefixId) {
            prefixToDelete = prefixId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            prefixToDelete = null;
            document.getElementById('deleteModal').classList.add('hidden');
        }

        document.getElementById('confirmDelete').addEventListener('click', async function() {
            if (!prefixToDelete) return;

            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.remove('hidden');
            
            try {
                const response = await fetch('/api/prefixes/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ prefix_id: prefixToDelete })
                });

                const data = await response.json();

                if (data.success) {
                    const element = document.querySelector(`[data-prefix-id="${prefixToDelete}"]`);
                    element.classList.add('delete-animation');
                    setTimeout(() => {
                        element.remove();
                        if (document.querySelectorAll('.prefix-card').length === 0) {
                            location.reload();
                        }
                    }, 300);
                    closeDeleteModal();
                } else {
                    throw new Error(data.message || 'Error deleting prefix');
                }
            } catch (error) {
                alert(error.message);
            } finally {
                loadingOverlay.classList.add('hidden');
            }
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const prefixes = document.querySelectorAll('.prefix-card');

            prefixes.forEach(prefix => {
                const text = prefix.textContent.toLowerCase();
                const match = text.includes(searchTerm);
                prefix.style.display = match ? '' : 'none';

                if (match && searchTerm) {
                    highlightText(prefix, searchTerm);
                } else {
                    removeHighlight(prefix);
                }
            });
        });

        function highlightText(element, term) {
            const regex = new RegExp(`(${term})`, 'gi');
            const textNodes = getTextNodes(element);

            textNodes.forEach(node => {
                const parent = node.parentNode;
                const text = node.textContent;
                const highlightedText = text.replace(regex, '<span class="search-highlight">$1</span>');
                
                if (text !== highlightedText) {
                    const span = document.createElement('span');
                    span.innerHTML = highlightedText;
                    parent.replaceChild(span, node);
                }
            });
        }

        function removeHighlight(element) {
            const highlights = element.querySelectorAll('.search-highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                const text = document.createTextNode(highlight.textContent);
                parent.replaceChild(text, highlight);
            });
        }

        function getTextNodes(element) {
            const textNodes = [];
            const walk = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
            let node;
            while (node = walk.nextNode()) {
                textNodes.push(node);
            }
            return textNodes;
        }

        // Click outside modal to close
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
                closeDeleteModal();
            }
            if (e.key === 'f' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Auto-hide alerts after 5 seconds
        const alertMessage = document.getElementById('alertMessage');
        if (alertMessage) {
            setTimeout(() => {
                alertMessage.style.opacity = '0';
                setTimeout(() => alertMessage.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>
