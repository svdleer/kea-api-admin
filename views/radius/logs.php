<?php
$pageTitle = 'RADIUS Authentication Logs';
$currentSearch = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$currentNas = isset($_GET['nas']) ? htmlspecialchars($_GET['nas']) : '';
$currentResult = isset($_GET['result']) ? htmlspecialchars($_GET['result']) : '';
$currentHours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
$currentPerPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                RADIUS Authentication Logs
            </h2>
            <p class="mt-1 text-sm text-gray-500">Authentication attempts from the last <?= $currentHours ?> hours (<?= number_format($totalRecords) ?> total)</p>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
        <form method="GET" action="/radius/logs" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Search Username -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">Search MAC Address</label>
                <input type="text" 
                       name="search" 
                       id="search" 
                       value="<?= $currentSearch ?>"
                       placeholder="MAC address..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            <!-- Filter by NAS -->
            <div>
                <label for="nas" class="block text-sm font-medium text-gray-700">NAS Device</label>
                <select name="nas" 
                        id="nas"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">All NAS</option>
                    <?php foreach ($availableNas as $ip => $name): ?>
                        <option value="<?= htmlspecialchars($ip) ?>" <?= $currentNas === $ip ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($ip) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter by Result -->
            <div>
                <label for="result" class="block text-sm font-medium text-gray-700">Result</label>
                <select name="result" 
                        id="result"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">All Results</option>
                    <option value="Access-Accept" <?= $currentResult === 'Access-Accept' ? 'selected' : '' ?>>Success</option>
                    <option value="Access-Reject" <?= $currentResult === 'Access-Reject' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>

            <!-- Time Range -->
            <div>
                <label for="hours" class="block text-sm font-medium text-gray-700">Time Range</label>
                <select name="hours" 
                        id="hours"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="1" <?= $currentHours === 1 ? 'selected' : '' ?>>Last 1 hour</option>
                    <option value="6" <?= $currentHours === 6 ? 'selected' : '' ?>>Last 6 hours</option>
                    <option value="24" <?= $currentHours === 24 ? 'selected' : '' ?>>Last 24 hours</option>
                    <option value="48" <?= $currentHours === 48 ? 'selected' : '' ?>>Last 48 hours</option>
                    <option value="168" <?= $currentHours === 168 ? 'selected' : '' ?>>Last 7 days</option>
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label for="per_page" class="block text-sm font-medium text-gray-700">Per Page</label>
                <select name="per_page" 
                        id="per_page"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="10" <?= $currentPerPage === 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $currentPerPage === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $currentPerPage === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $currentPerPage === 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $currentPerPage === 200 ? 'selected' : '' ?>>200</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="sm:col-span-2 lg:col-span-5 flex justify-end space-x-3">
                <a href="/radius/logs" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Clear Filters
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- NAS Statistics -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Authentication Summary by NAS
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Success and failure rates grouped by network access server
            </p>
        </div>
        <div class="border-t border-gray-200">
            <table class="w-auto divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            NAS Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            IP Address
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Successful
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Failed
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Success Rate
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($nasStats)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No authentication data available
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nasStats as $stat): ?>
                            <?php 
                                $successRate = $stat['total_count'] > 0 
                                    ? round(($stat['success_count'] / $stat['total_count']) * 100, 1) 
                                    : 0;
                                $rateColor = $successRate >= 80 ? 'text-green-600' : ($successRate >= 50 ? 'text-yellow-600' : 'text-red-600');
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($stat['nas_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($stat['nas_ip']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                    <?= number_format($stat['success_count']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                    <?= number_format($stat['failed_count']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($stat['total_count']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm <?= $rateColor ?>">
                                    <?= $successRate ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Authentication Logs -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Recent Authentication Attempts
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Last 200 authentication attempts (most recent first)
            </p>
        </div>
        <div class="border-t border-gray-200 overflow-x-auto">
            <table class="w-auto divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Timestamp
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            MAC Address
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            NAS
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            NAS IP
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Port/Interface
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Result
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Server
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No authentication logs available
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $isSuccess = $log['reply'] === 'Access-Accept';
                                $statusBadge = $isSuccess 
                                    ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Success</span>'
                                    : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>';
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('Y-m-d H:i:s', strtotime($log['authdate'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($log['username']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($log['nas_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($log['nas_ip']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($log['nas_port'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?= $statusBadge ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($log['server']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4 rounded-lg shadow">
            <div class="sm:hidden">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing
                        <span class="font-medium"><?= number_format(($page - 1) * $currentPerPage + 1) ?></span>
                        to
                        <span class="font-medium"><?= number_format(min($page * $currentPerPage, $totalRecords)) ?></span>
                        of
                        <span class="font-medium"><?= number_format($totalRecords) ?></span>
                        results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1): ?>
                            <a href="?page=1&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                1
                            </a>
                            <?php if ($start > 2): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    ...
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i === $page ? 'bg-indigo-50 border-indigo-500 text-indigo-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50' ?> text-sm font-medium">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    ...
                                </span>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $totalPages ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($currentSearch) ?>&nas=<?= urlencode($currentNas) ?>&result=<?= urlencode($currentResult) ?>&hours=<?= $currentHours ?>&per_page=<?= $currentPerPage ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
