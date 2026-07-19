<?php

/**
 * ------------------------------------------------------------
 * FSDMS Authentication Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Authentication middleware for protected APIs.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/jwt.php";
require_once __DIR__ . "/response.php";
require_once __DIR__ . "/constants.php";

/**
 * Read Authorization Header
 */
function getAuthorizationHeader(): ?string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    if (function_exists('apache_request_headers')) {

        $headers = apache_request_headers();

        foreach ($headers as $key => $value) {

            if (strtolower($key) === 'authorization') {
                return trim($value);
            }

        }

    }

    return null;
}

/**
 * Extract Bearer Token
 */
function getBearerToken(): ?string
{
    $header = getAuthorizationHeader();

    if (!$header) {
        return null;
    }

    if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Authenticate User
 */
function authenticate()
{
    $token = getBearerToken();

    if (!$token) {
        unauthorized("Authorization token missing.");
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        unauthorized(MSG_INVALID_TOKEN);
    }

    return $payload;
}