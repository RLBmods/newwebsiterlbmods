<?php
class Stream {
    public static function hasActiveStream(int $userId): bool {
        global $con;
        $stmt = $con->prepare("SELECT id FROM streams WHERE user_id = ? AND status = 'live'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public static function create(array $data): int {
        global $con;
        $stmt = $con->prepare("INSERT INTO streams 
                              (user_id, title, description, platform, stream_url, status, started_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssss", 
            $data['user_id'],
            $data['title'],
            $data['description'],
            $data['platform'],
            $data['stream_url'],
            $data['status']
        );
        $stmt->execute();
        return $stmt->insert_id;
    }

    public static function end(int $streamId, int $userId): bool {
        global $con;
        $stmt = $con->prepare("UPDATE streams 
                              SET status = 'ended', ended_at = NOW() 
                              WHERE id = ? AND user_id = ? AND status = 'live'");
        $stmt->bind_param("ii", $streamId, $userId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public static function getCurrent(int $userId): ?array {
        global $con;
        $stmt = $con->prepare("SELECT * FROM streams 
                              WHERE user_id = ? AND status = 'live' 
                              ORDER BY started_at DESC 
                              LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
}