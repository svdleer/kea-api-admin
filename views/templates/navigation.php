<?php if ($auth->isLoggedIn()): ?>
<nav class="bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <img class="h-8 w-8" src="/assets/images/logo.png" alt="Logo">
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="/" class="<?php echo $currentPage === 'home' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        
                        <a href="/switches" class="<?php echo $currentPage === 'switches' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">Switches</a>
                        
                        <a href="/ipv6" class="<?php echo $currentPage === 'ipv6' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">IPv6 Subnets</a>
                        
                        <a href="/networks" class="<?php echo $currentPage === 'networks' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">Networks</a>
                        
                        <?php if ($auth->isAdmin()): ?>
                        <a href="/api-keys" class="<?php echo $currentPage === 'api-keys' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">API Keys</a>
                        <a href="/admin" class="<?php echo $currentPage === 'admin' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-md text-sm font-medium">Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="ml-4 flex items-center md:ml-6">
                    <span class="text-gray-300 px-3 py-2 text-sm"><?php echo htmlspecialchars($auth->getCurrentUsername()); ?></span>
                    <a href="/logout" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>