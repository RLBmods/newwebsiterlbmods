<?php
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/get_user_info.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

requireAuth();
requireReseller();

header('Content-Type: application/json');

function calculateLoyaltyDiscount($con, $user_id) {
    $query = "SELECT COUNT(*) as total_purchases FROM reseller_licenses WHERE user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totalPurchases = $stmt->get_result()->fetch_assoc()['total_purchases'] ?? 0;
    
    if ($totalPurchases >= 500) return 20;
    if ($totalPurchases >= 200) return 15;
    if ($totalPurchases >= 100) return 10;
    if ($totalPurchases >= 50) return 5;
    return 0;
}

try {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) throw new Exception('User not authenticated');

    $stmt = $con->prepare("SELECT discount_override FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $discountOverride = $stmt->get_result()->fetch_assoc()['discount_override'] ?? 0;

    $loyaltyDiscount = calculateLoyaltyDiscount($con, $user_id);
    $totalDiscount = (float)$loyaltyDiscount + (float)$discountOverride;

    $tiers = [
        ['threshold' => 0, 'name' => 'Bronze', 'discount' => 0],
        ['threshold' => 50, 'name' => 'Silver', 'discount' => 5],
        ['threshold' => 100, 'name' => 'Gold', 'discount' => 10],
        ['threshold' => 200, 'name' => 'Platinum', 'discount' => 15],
        ['threshold' => 500, 'name' => 'Diamond', 'discount' => 20]
    ];

    $query = "SELECT COUNT(*) as total_purchases FROM reseller_licenses WHERE user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totalPurchases = $stmt->get_result()->fetch_assoc()['total_purchases'] ?? 0;

    $currentTier = $tiers[0];
    $nextTier = null;
    foreach ($tiers as $tier) {
        if ($totalPurchases >= $tier['threshold']) {
            $currentTier = $tier;
        } elseif (!$nextTier) {
            $nextTier = $tier;
        }
    }

    $progressPercentage = 0;
    $purchasesNeeded = 0;
    if ($nextTier) {
        $range = $nextTier['threshold'] - $currentTier['threshold'];
        $progress = $totalPurchases - $currentTier['threshold'];
        $progressPercentage = min(100, ($progress / $range) * 100);
        $purchasesNeeded = $nextTier['threshold'] - $totalPurchases;
    }

    echo json_encode([
        'success' => true,
        'totalPurchases' => $totalPurchases,
        'currentTier' => $currentTier,
        'nextTier' => $nextTier,
        'progressPercentage' => $progressPercentage,
        'purchasesNeeded' => $purchasesNeeded,
        'totalDiscount' => $totalDiscount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}