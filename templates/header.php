<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VFZ DAA Infrastructure Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex space-x-8">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold">VFZ DAA Infrastructure Management</h1>
                    </div>
                    <div class="hidden md:flex space-x-8">
                        <a href="/dashboard" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            Dashboard
                        </a>
                        <a href="/switches" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            Switches
                        </a>
                        <a href="/ipv6" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            IPv6 Subnets
                        </a>
                        <a href="/leases" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            Leases
                        </a>
                        <a href="/users" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            Users
                        </a>
                        <a href="/api-keys" class="inline-flex items-center px-1 pt-1 text-gray-500 hover:text-gray-700">
                            API Keys
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 mr-4">
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User'); ?>
                    </span>
                    <a href="/logout" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>