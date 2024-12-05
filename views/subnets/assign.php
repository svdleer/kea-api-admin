<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Authentication;
use App\Database\Database;
use App\Network\IPv6Subnet;
use App\Network\CinSwitch;
use App\Network\SubnetPrefixRelation;

$auth = new Authentication(Database::getInstance());

if (!$auth->isLoggedIn()) {
    header('Location: /');
    throw new Exception('User not logged in');
}

$db = Database::getInstance();
$ipv6Subnet = new IPv6Subnet($db);
$cinSwitch = new CinSwitch($db);
$subnetPrefixRelation = new SubnetPrefixRelation($db);

$subnetId = $_GET['id'] ?? null;
if (!$subnetId) {
    header('Location: /subnets');
    throw new Exception('Invalid subnet ID');
}

$subnet = $ipv6Subnet->getById($subnetId);
if (!$subnet) {
    header('Location: /subnets');
    throw new Exception('Subnet not found');
}

$switches = $cinSwitch->getAll();
$assignedPrefixes = $subnetPrefixRelation->getBySubnetId($subnetId);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_prefix':
                if ($subnetPrefixRelation->create($subnetId, (int)$_POST['prefix_id'])) {
                    $message = "Prefix assigned successfully";
                    $assignedPrefixes = $subnetPrefixRelation->getBySubnetId($subnetId);
                } else {
                    $error = "Failed to assign prefix";
                }
                break;

            case 'remove_prefix':
                if ($subnetPrefixRelation->delete($subnetId, (int)$_POST['prefix_id'])) {
                    $message = "Prefix removed successfully";
                    $assignedPrefixes = $subnetPrefixRelation->getBySubnetId($subnetId);
                } else {
                    $error = "Failed to remove prefix";
                }
                break;
        }
    }
}

require __DIR__ . '/../../templates/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">
            Assign Prefixes to Subnet: <?php echo htmlspecialchars($subnet['subnet'] . '/' . $subnet['prefix_len']); ?>
        </h1>
        <a href="/subnets" class="text-blue-500 hover:text-blue-700">← Back to Subnets</a>
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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Assigned Prefixes -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Assigned Prefixes</h2>
            <div class="space-y-4">
                <?php if (empty($assignedPrefixes)): ?>
                    <p class="text-gray-500 italic">No prefixes assigned</p>
                <?php else: ?>
                    <?php foreach ($assignedPrefixes as $prefix): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                            <span><?php echo htmlspecialchars($prefix['prefix'] . '/' . $prefix['prefix_length']); ?></span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="remove_prefix">
                                <input type="hidden" name="prefix_id" value="<?php echo htmlspecialchars($prefix['id']); ?>">
                                <button type="submit" 
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to remove this prefix?')">
                                    Remove
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Prefixes -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Available Prefixes</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="assign_prefix">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Select Prefix</label>
                    <select name="prefix_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choose a prefix...</option>
                        <?php 
                        $availablePrefixes = array_udiff(
                            $ipv6Prefix->getAll(),
                            $assignedPrefixes,
                            function($a, $b) { return $a['id'] - $b['id']; }
                        );
                        foreach ($availablePrefixes as $prefix): 
                        ?>
                            <option value="<?php echo htmlspecialchars($prefix['id']); ?>">
                                <?php echo htmlspecialchars($prefix['prefix'] . '/' . $prefix['prefix_length']); ?>
                                <?php if ($prefix['description']): ?>
                                    - <?php echo htmlspecialchars($prefix['description']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Assign Prefix
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require __DIR__ . '/../../templates/footer.php'; ?>
