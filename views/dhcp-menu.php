<div class="bg-white shadow-sm">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-start h-16 space-x-8">
            <a href="/dhcp/subnets" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'subnets' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                DHCPv6 Subnets
            </a>
            <a href="/dhcp/dedicated" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'dedicated' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                Dedicated DHCP Subnets
            </a>
            <a href="/dhcp/optionsdef" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'optionsdef' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                DHCPv6 Option Definitions
            </a>
            <a href="/dhcp/options" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'options' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                DHCPv6 Options 
            </a>
            <a href="/dhcp/leases" 
                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'leases' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                IPv6 Leases
            </a>
            <a href="/dhcp/search" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium <?php echo $subPage === 'search' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?>">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Advanced Search
            </a>
        </div>
    </div>
</div>