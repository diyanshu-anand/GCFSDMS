<?php
// profile.php
header("Content-Type: application/json");

require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// 1. Extract Authorization Header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(["error" => "Access denied. Token missing."]);
    exit;
}

$jwt = substr($authHeader, 7);
$secretKey = getenv('JWT_SECRET');

if (!$secretKey) {
    http_response_code(500);
    echo json_encode(["error" => "Critical Server Error: Missing JWT Secret."]);
    exit;
}

try {
    // 2. Decode and cryptographically verify the token
    $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    
    // Access the user data inside the token
    $userId = $decoded->data->user_id;

    // 3. Token is fully valid!
    http_response_code(200);
    echo json_encode([
        "message" => "Access granted via standard JWT!",
        "user_data" => [
            "user_id" => $userId,
            "issuer" => $decoded->iss
        ]
    ]);

} catch (\Exception $e) {
    // Catches expired tokens, signature mismatches, malformed structures, etc.
    http_response_code(401);
    echo json_encode([
        "error" => "Authentication failed.",
        "details" => $e->getMessage()
    ]);
    exit;
}