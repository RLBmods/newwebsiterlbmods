<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

class LicenseKey {
    public static function checkLimits(int $userId, string $product, int $weeklyLimit, int $monthlyLimit): array {
        global $con;
        
        $currentWeek = date('Y-m-d', strtotime('monday this week'));
        $currentMonth = date('Y-m-01');

        // Weekly count
        $weekly = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                WHERE user_id = ? AND product = ? AND created_at >= ?");
        $weekly->bind_param("iss", $userId, $product, $currentWeek);
        $weekly->execute();
        $weeklyCount = $weekly->get_result()->fetch_row()[0];

        // Monthly count
        $monthly = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                 WHERE user_id = ? AND product = ? AND created_at >= ?");
        $monthly->bind_param("iss", $userId, $product, $currentMonth);
        $monthly->execute();
        $monthlyCount = $monthly->get_result()->fetch_row()[0];

        return [
            'weekly' => (int)$weeklyCount,
            'monthly' => (int)$monthlyCount,
            'weekly_limit' => $weeklyLimit,
            'monthly_limit' => $monthlyLimit
        ];
    }

    public static function create(array $data): array {
        global $con;
        
        // Generate key
        $keyValue = 'RLB-MP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));

        $stmt = $con->prepare("INSERT INTO license_keys 
                              (user_id, key_value, product, purpose, details, status, expires_at) 
                              VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY))");
        $stmt->bind_param("issss", 
            $data['user_id'],
            $keyValue,
            $data['product'],
            $data['purpose'],
            $data['details']
        );
        $stmt->execute();

        return [
            'id' => $stmt->insert_id,
            'key_value' => $keyValue,
            'product' => $data['product'],
            'status' => 'pending',
            'created_at' => date('c')
        ];
    }

    public static function getAll(int $userId, array $filters = []): array {
        global $con;
        
        $query = "SELECT * FROM license_keys WHERE user_id = ?";
        $types = "i";
        $values = [$userId];

        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $types .= "s";
            $values[] = $filters['status'];
        }

        if (!empty($filters['product'])) {
            $query .= " AND product = ?";
            $types .= "s";
            $values[] = $filters['product'];
        }

        $query .= " ORDER BY created_at DESC";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $keys = [];
        
        while ($row = $result->fetch_assoc()) {
            $keys[] = $row;
        }
        
        return $keys;
    }

    public static function getUsageCounts(int $userId, string $product): array {
        global $con;
        
        $currentWeek = date('Y-m-d', strtotime('monday this week'));
        $currentMonth = date('Y-m-01');

        // Weekly count
        $weekly = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                WHERE user_id = ? AND product = ? AND created_at >= ?");
        $weekly->bind_param("iss", $userId, $product, $currentWeek);
        $weekly->execute();
        $weeklyCount = $weekly->get_result()->fetch_row()[0];

        // Monthly count
        $monthly = $con->prepare("SELECT COUNT(*) FROM license_keys 
                                 WHERE user_id = ? AND product = ? AND created_at >= ?");
        $monthly->bind_param("iss", $userId, $product, $currentMonth);
        $monthly->execute();
        $monthlyCount = $monthly->get_result()->fetch_row()[0];

        return [
            'weekly' => (int)$weeklyCount,
            'monthly' => (int)$monthlyCount
        ];
    }
}