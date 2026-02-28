<?php
class Response {
    public static function success(array $data = [], int $code = 200) {
        http_response_code($code);
        echo json_encode(['success' => true, ...$data]);
        exit;
    }

    public static function error(string $message, int $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
}