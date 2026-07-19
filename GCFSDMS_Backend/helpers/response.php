<?php

/**
 * ------------------------------------------------------------
 * FSDMS Response Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Standardized JSON API responses
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/constants.php";

/**
 * Send JSON Response
 */
function sendResponse(bool $success, string $message, $data = null, int $statusCode = HTTP_OK)
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=UTF-8");

    $response = [
        "success" => $success,
        "message" => $message
    ];

    if ($data !== null) {
        $response["data"] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    exit;
}


/**
 * Success Response
 */
function returnSuccess(string $message = MSG_SUCCESS, $data = null, int $statusCode = HTTP_OK)
{
    sendResponse(true, $message, $data, $statusCode);
}


/**
 * Error Response
 */
function returnError(string $message = MSG_FAILED, int $statusCode = HTTP_BAD_REQUEST)
{
    sendResponse(false, $message, null, $statusCode);
}


/**
 * Validation Error
 */
function validationError(string $message = MSG_REQUIRED_FIELDS)
{
    returnError($message, HTTP_BAD_REQUEST);
}


/**
 * Unauthorized
 */
function unauthorized(string $message = MSG_UNAUTHORIZED)
{
    returnError($message, HTTP_UNAUTHORIZED);
}


/**
 * Forbidden
 */
function forbidden(string $message = "Access denied")
{
    returnError($message, HTTP_FORBIDDEN);
}


/**
 * Resource Not Found
 */
function notFound(string $message = "Resource not found")
{
    returnError($message, HTTP_NOT_FOUND);
}


/**
 * Conflict
 */
function conflict(string $message = "Resource already exists")
{
    returnError($message, HTTP_CONFLICT);
}


/**
 * Method Not Allowed
 */
function methodNotAllowed()
{
    returnError(MSG_METHOD_NOT_ALLOWED, HTTP_METHOD_NOT_ALLOWED);
}


/**
 * Internal Server Error
 */
function serverError(string $message = MSG_INTERNAL_ERROR)
{
    returnError($message, HTTP_INTERNAL_SERVER_ERROR);
}