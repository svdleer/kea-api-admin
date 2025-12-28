<?php
error_reporting(E_ALL);
// No direct fix is possible for this code snippet.
// The vulnerability should be addressed by removing this line entirely
// or by setting it to 0 in a production environment.

// Debug information
error_log("=== Login Page Access ===");
error_log("Session Status: " . session_status());
error_log("Session ID: " . session_id());
error_log("Current Session Data: " . print_r($_SESSION, true));

// Check if already logged in
$auth = new \App\Auth\Authentication(\App\Database\Database::getInstance());
if ($auth->isLoggedIn()) {
    header('Location: /dashboard');
    throw new Exception('Redirecting to dashboard');
}

// Get error message if any
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']); // Clear the error message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RPD Infrastructure Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php if (getenv('APP_ENV') === 'development'): ?>
    <div class="bg-yellow-100 p-4 mb-4">
        <h3 class="font-bold">Debug Information:</h3>
        <pre class="text-xs">
Session ID: <?php echo session_id(); ?>
Session Status: <?php echo session_status(); ?>
Session Data: 
<?php print_r($_SESSION); ?>
        </pre>
    </div>
    <?php endif; ?>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    RPD Infrastructure Management
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Please sign in to continue
                </p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" action="/login" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="username" class="sr-only">Username</label>
                        <input id="username" 
                               name="username" 
                               type="text" 
                               required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm" 
                               placeholder="Username">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" 
                               name="password" 
                               type="password" 
                               required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm" 
                               placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Sign in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="absolute bottom-0 w-full bg-white shadow-inner">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">
                &copy; <?php echo date('Y'); ?> VFZ RPD Infrastructure Management. All rights reserved.
            </p>
        </div>
    </footer>

    <?php if (getenv('APP_ENV') === 'development'): ?>
    <script>
        console.log('Session ID:', <?php echo json_encode(session_id()); ?>);
        console.log('Session Data:', <?php echo json_encode($_SESSION); ?>);
    </script>
    <?php endif; ?>
</body>
</html>
