<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Standardized API Success Response
     */
    protected function apiSuccess($data = null, string $message = '', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    /**
     * Standardized API Error Response
     */
    protected function apiError(string $message = 'An error occurred', int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
