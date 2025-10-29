<?php

namespace App\Helpers;

class ApiResponse
{
    public static function send($data, $statusCode = 200)
    {
        // Clear any output buffers before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit; // Stop execution to prevent any further output
    }

    public static function error($message, $statusCode = 400)
    {
        self::send([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }

    public static function success($data = null, $message = null, $statusCode = 200)
    {
        $response = ['success' => true];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($message !== null) {
            $response['message'] = $message;
        }

        self::send($response, $statusCode);
    }
}
