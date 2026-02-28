<?php
class ActivityLogger {
    public static function log(int $userId, string $type, string $description) {
        global $con;
        $stmt = $con->prepare("INSERT INTO activities 
                              (user_id, type, description) 
                              VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $type, $description);
        $stmt->execute();
    }

    public static function getRecent(int $userId, int $limit = 5): array {
        global $con;
        $stmt = $con->prepare("SELECT * FROM activities 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC 
                              LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $activities = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['time_ago'] = time_elapsed_string($row['created_at']);
            $activities[] = $row;
        }
        
        return $activities;
    }
}