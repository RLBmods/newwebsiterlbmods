<?php
class Validator {
    public static function validateJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input', 400);
        }
        return $input;
    }

    public static function validateRequiredFields(array $input, array $required) {
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field", 400);
            }
        }
    }
}