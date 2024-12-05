<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Network\IPv6Prefix;

$auth = new Authentication(Database::getInstance());

if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$db = Database::getInstance();
$ipv6Prefix = new IPv6Prefix($db);

$prefixId = $_GET['id'] ?? null;
if (!$prefixId) {
    header('Location: /prefixes');
    throw new Exception('Invalid prefix ID');
}

$prefix = $ipv6Prefix->getById($prefixId);
if (!$prefix) {
    header('Location: /prefixes');
    throw new Exception('Prefix not found');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ipv6Prefix->validateIPv6($_POST['prefix'])) {
        $error = "Invalid IPv6 prefix format";
    } elseif ($ipv6Prefix->update(
        $prefixId,
        $_POST['prefix'],
        (int)$_POST['prefix_length'],
        $_POST['description'] ?? null
    )) {
        $message = "Prefix updated successfully";
        $prefix = $ipv6Prefix->getById($prefixId); // Refresh data
    } else {
        $error = "Failed to update prefix";
    }
}

require __DIR__ . '/../../templates/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Edit IPv6 Prefix</h1>
        <a href="/prefixes" class="text-blue-500 hover:text-blue-700">← Back to Prefixes</a>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg p-6">
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Prefix</label>
                <input type="text" name="prefix" required
                       value="<?php echo htmlspecialchars($prefix['prefix']); ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Prefix Length</label>
                <input type="number" name="prefix_length" required
                       value="<?php echo htmlspecialchars($prefix['prefix_length']); ?>"
                       min="1" max="128"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                ><?php echo htmlspecialchars($prefix['description'] ?? ''); ?></textarea>
            </div>

            <div>
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Update Prefix
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../templates/footer.php'; ?>
