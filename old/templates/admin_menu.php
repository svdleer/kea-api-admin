<?php if (isset($auth) && $auth->isAdmin()): ?>
<div class="bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="/users" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">User Management</a>
                        <a href="/api-keys" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">API Keys</a>
                        <a href="/ipv6" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">IPv6 Subnets</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>