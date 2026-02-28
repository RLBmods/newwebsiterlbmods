<?php
// Enable output buffering
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/session.php'; // This handles session_start()
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

// Ensure the user is authenticated
requireAuth();

// Ensure the user is allowed accessing the page
requireStaff();


// Handle discount updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
    
    if ($userId && $discount !== false && $discount >= 0 && $discount <= 100) {
        $stmt = $pdo->prepare("UPDATE usertable SET discount_override = ? WHERE id = ?");
        $stmt->execute([$discount, $userId]);
        $_SESSION['success'] = "Discount updated successfully";
    } else {
        $_SESSION['error'] = "Invalid discount value";
    }
    header("Location: admin_discounts.php");
    exit();
}

// Get all resellers
$resellers = $pdo->query("
    SELECT u.id, u.username, u.discount_override, COUNT(rl.id) as total_sales
    FROM usertable u 
    LEFT JOIN reseller_licenses rl ON u.id = rl.user_id 
    WHERE u.role IN ('reseller', 'admin')
    GROUP BY u.id
    ORDER BY total_sales DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Reseller Discounts</title>
    <style>
        .discount-table { width: 100%; border-collapse: collapse; }
        .discount-table th, .discount-table td { padding: 10px; border: 1px solid #ddd; }
        .discount-form { display: inline-block; }
        input[type="number"] { width: 60px; }
    </style>
</head>
<body>
    <h1>Manage Reseller Discounts</h1>
    
    <table class="discount-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Total Sales</th>
                <th>Current Discount</th>
                <th>Override Discount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resellers as $reseller): ?>
            <tr>
                <td><?= htmlspecialchars($reseller['username']) ?></td>
                <td><?= $reseller['total_sales'] ?></td>
                <td><?= $reseller['discount_override'] ? $reseller['discount_override'] . '%' : 'Tier-based' ?></td>
                <td>
                    <form method="POST" class="discount-form">
                        <input type="hidden" name="user_id" value="<?= $reseller['id'] ?>">
                        <input type="number" name="discount" min="0" max="100" step="0.1" 
                               value="<?= $reseller['discount_override'] ?>" placeholder="0-100">
                        <button type="submit">Update</button>
                    </form>
                </td>
                <td>
                    <?php if ($reseller['discount_override']): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $reseller['id'] ?>">
                        <input type="hidden" name="discount" value="">
                        <button type="submit">Remove Override</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>