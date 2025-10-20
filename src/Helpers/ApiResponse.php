<?php

namespace App\Helpers;

class ApiResponse
{
    public static function send($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        return;
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
