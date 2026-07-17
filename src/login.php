<?php
// login.php

// 1. Load Composer and Imports at the VERY TOP
require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;

// 2. Set response headers
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

// Include database
require_once __DIR__ . '/db.php';

// 3. Guard against non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit;
}

// 4. Read inbound JSON payload
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Email and password are required."]);
    exit;
}

try {
    // 5. Fetch the user by email using a prepared statement
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // 6. Verify the password against the stored hash
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid email or password."]);
        exit;
    }

    // Read the secret key from the Docker environment
    $secretKey = getenv('JWT_SECRET'); 
    
    if (!$secretKey) {
        http_response_code(500);
        echo json_encode(["error" => "Critical Server Error: Missing JWT Secret."]);
        exit;
    }

    $payload = [
        'iss' => 'http://localhost:8000', // Issuer
        'aud' => 'http://localhost:8000', // Audience
        'iat' => time(),                  // Issued at
        'nbf' => time(),                  // Not before
        'exp' => time() + 3600,           // Expiration time (1 hour)
        'data' => [
            'user_id' => $user['id']
        ]
    ];

    // Cryptographically sign the token using the HS256 algorithm
    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    // 8. Deliver the token
    http_response_code(200);
    echo json_encode([
        "message" => "Login successful!",
        "token" => $jwt
    ]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "An internal server error occurred."]);
}