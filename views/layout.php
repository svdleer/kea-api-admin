<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'VFZ RPD Infrastructure Management'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="h-full flex flex-col bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
        <nav class="bg-white">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-gray-800">VFZ RPD Infrastructure Management</h1>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="/dashboard" class="<?php echo $currentPage === 'dashboard' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="/switches" class="<?php echo $currentPage === 'switches' ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Switches
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <a href="/logout" class="bg-red-500 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content (Scrollable) -->
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php echo $content ?? ''; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> VFZ RPD Infrastructure Management
                </div>
                <div class="text-sm text-gray-500">
                    Version 0.1
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
