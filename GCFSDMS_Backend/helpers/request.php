<!-- 
Verify request method.
Read JSON body.
Validate that JSON is valid.
Return the decoded array. 
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Request Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Handle HTTP requests and JSON input.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/response.php";

/**
 * Validate HTTP Request Method
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        methodNotAllowed();
    }
}

/**
 * Get Raw Request Body
 */
function getRawInput(): string
{
    return file_get_contents("php://input");
}

/**
 * Decode JSON Request
 */
function getJsonInput(?string $method = null): array
{
    if ($method !== null) {
        requireMethod($method);
    }

    $raw = getRawInput();

    if (trim($raw) === '') {
        validationError("Request body is empty.");
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        validationError("Invalid JSON format.");
    }

    return $data;
}

/**
 * Read GET Parameter
 */
function getQueryParam(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Read POST Parameter
 */
function getPostParam(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Check if a parameter exists
 */
function hasParam(array $data, string $key): bool
{
    return array_key_exists($key, $data);
}

/**
 * Get parameter safely
 */
function getParam(array $data, string $key, $default = null)
{
    return $data[$key] ?? $default;
}