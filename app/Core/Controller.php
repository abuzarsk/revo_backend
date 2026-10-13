<?php

namespace App\Core;

class Controller
{
    protected $params = [];

    public function __construct($params = [])
    {
        $this->params = $params;
    }

    protected function model($model)
    {
        $modelClass = 'App\Models\\' . $model;
        
        if (class_exists($modelClass)) {
            $db = \App\Core\Database::getInstance()->getConnection();
            return new $modelClass($db);
        } else {
            throw new \Exception("Model class $modelClass not found");
        }
    }

    /**
     * Send JSON response
     */
    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send success response
     */
    protected function success($data = null, $message = 'Success', $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        $this->json($response, $statusCode);
    }

    /**
     * Send error response
     */
    protected function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }

    /**
     * Send not found response
     */
    protected function notFound($message = 'Resource not found')
    {
        $this->error($message, 404);
    }

    /**
     * Get request method
     */
    protected function getMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get request body as array
     */
    protected function getRequestBody()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Get Authorization header token
     */
    protected function getAuthToken()
    {
        // Try to get headers using getallheaders() (available in Apache/mod_php)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        } else {
            // Fallback for environments where getallheaders() is not available (e.g., FastCGI)
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }
        
        if (!$authHeader) {
            return null;
        }

        // Extract token from "Bearer {token}" format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Verify JWT token and return decoded payload
     * Returns null if token is invalid or missing
     */
    protected function verifyAuth()
    {
        $token = $this->getAuthToken();

        if (!$token) {
            return null;
        }

        try {
            $decoded = \App\Core\Jwt::decode($token);
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Require authentication - returns user data if authenticated, otherwise sends error response
     */
    protected function requireAuth()
    {
        $user = $this->verifyAuth();

        if (!$user) {
            $this->error('Authentication required. Please provide a valid token.', 401);
            return null;
        }

        return $user;
    }
}

