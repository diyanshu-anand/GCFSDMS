<!-- 
Generate JWT
Verify JWT
Decode JWT
Base64URL encoding/decoding
HMAC SHA-256 signature generation
Expiration validation
Issuer validation
Audience validation

It should not:

Access the database
Return JSON
Read HTTP headers
Handle login logic 
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS JWT Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Pure PHP JWT implementation (HS256)
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../config/config.php";

/**
 * Base64 URL Encode
 */
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL Decode
 */
function base64UrlDecode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Generate JWT
 */
function generateJWT(array $payload): string
{
    $header = [
        "alg" => JWT_ALGORITHM,
        "typ" => "JWT"
    ];

    $issuedAt = time();

    $payload = array_merge([
        "iss" => JWT_ISSUER,
        "aud" => JWT_AUDIENCE,
        "iat" => $issuedAt,
        "nbf" => $issuedAt,
        "exp" => $issuedAt + JWT_EXPIRE_TIME
    ], $payload);

    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac(
        "sha256",
        $headerEncoded . "." . $payloadEncoded,
        JWT_SECRET,
        true
    );

    $signatureEncoded = base64UrlEncode($signature);

    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

/**
 * Verify JWT
 */
function verifyJWT(string $jwt)
{
    $parts = explode(".", $jwt);

    if (count($parts) !== 3) {
        return false;
    }

    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    $expectedSignature = base64UrlEncode(
        hash_hmac(
            "sha256",
            $headerEncoded . "." . $payloadEncoded,
            JWT_SECRET,
            true
        )
    );

    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        return false;
    }

    $payload = json_decode(
        base64UrlDecode($payloadEncoded),
        true
    );

    if (!$payload) {
        return false;
    }

    $now = time();

    if (isset($payload["nbf"]) && $payload["nbf"] > $now) {
        return false;
    }

    if (isset($payload["exp"]) && $payload["exp"] < $now) {
        return false;
    }

    if (($payload["iss"] ?? "") !== JWT_ISSUER) {
        return false;
    }

    if (($payload["aud"] ?? "") !== JWT_AUDIENCE) {
        return false;
    }

    return $payload;
}

/**
 * Decode JWT without verification
 */
function decodeJWT(string $jwt)
{
    $parts = explode(".", $jwt);

    if (count($parts) !== 3) {
        return false;
    }

    return json_decode(
        base64UrlDecode($parts[1]),
        true
    );
}