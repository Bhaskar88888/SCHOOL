<?php
/**
 * Standardized API Response Helper
 * School ERP PHP v3.0
 * 
 * Ensures consistent JSON response format across all endpoints
 */

/**
 * Standard JSON response
 * 
 * @param mixed $data Response data
 * @param int $code HTTP status code
 * @param string $message Success message
 */
function api_response($data = null, $code = 200, $message = 'Success')
{
    http_response_code($code);
    header('Content-Type: application/json');

    $response = [
        'success' => $code >= 200 && $code < 300,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    // Add error details if failed
    if ($code >= 400) {
        $response['error'] = true;
        $response['statusCode'] = $code;
    }

    echo json_encode($response);
    exit;
}

/**
 * Success response
 */
function api_success($data = null, $message = 'Success')
{
    api_response($data, 200, $message);
}

/**
 * Created response
 */
function api_created($data = null, $message = 'Created')
{
    api_response($data, 201, $message);
}

/**
 * Error response
 */
function api_error($message = 'Error', $code = 400, $errors = null)
{
    http_response_code($code);
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => $message,
        'error' => true,
        'statusCode' => $code,
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    echo json_encode($response);
    exit;
}

/**
 * Validation error response
 */
function api_validation_error($errors)
{
    api_error('Validation failed', 422, $errors);
}

/**
 * Not found response
 */
function api_not_found($message = 'Not found')
{
    api_error($message, 404);
}

/**
 * Unauthorized response
 */
function api_unauthorized($message = 'Unauthorized')
{
    api_error($message, 401);
}

/**
 * Forbidden response
 */
function api_forbidden($message = 'Forbidden')
{
    api_error($message, 403);
}

/**
 * Server error response
 */
function api_server_error($message = 'Internal server error')
{
    api_error($message, 500);
}

/**
 * Paginated response
 */
function api_paginated($data, $page, $limit, $total, $message = 'Success')
{
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data,
        'pagination' => [
            'page' => (int) $page,
            'limit' => (int) $limit,
            'total' => (int) $total,
            'totalPages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
            'hasNext' => ($page * $limit) < $total,
            'hasPrev' => $page > 1,
        ],
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Safe JSON decode with error handling
 */
function api_get_json()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        api_error('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    return $data ?? [];
}

/**
 * Error handler for uncaught exceptions
 * Only apply in API context to avoid breaking regular PHP pages
 */
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    set_exception_handler(function ($exception) {
        if (defined('APP_DEBUG') && filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN)) {
            api_server_error($exception->getMessage());
        } else {
            api_server_error('Internal server error');
        }
    });

    /**
     * Error handler for PHP errors
     */
    set_error_handler(function ($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            error_log("PHP Error: $message in $file on line $line");

            if (defined('APP_DEBUG') && filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN)) {
                api_server_error("$message in $file:$line");
            }
        }
        return true;
    });
}
