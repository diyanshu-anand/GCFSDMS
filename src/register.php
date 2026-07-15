<?php
// register.php

// 1. Force the response headers to return clean JSON
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

// Include our database connection
require_once 'db.php';

// 2. Check if the request method is actually a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit;
}

// 3. Read the raw inbound JSON data from the client request
$input = json_decode(file_get_contents('php://input'), true);

$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';

// 4. Validate the inputs
if (!$email) {
    http_response_code(400);
    echo json_encode(["error" => "A valid email address is required."]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "Password must be at least 8 characters long."]);
    exit;
}

// 5. Cryptographically Hash the password using Argon2id (or Bcrypt)
// Never pass a plain text password into your database!
$passwordHash = password_hash($password, PASSWORD_ARGON2ID);

try {
    // 6. Use a Prepared Statement to prevent SQL Injection
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
    $stmt->execute([
        'email' => $email,
        'password_hash' => $passwordHash
    ]);

    http_response_code(201);
    echo json_encode(["message" => "User registered successfully!"]);

} catch (\PDOException $e) {
    // Check if the error code means the email already exists (Duplicate entry)
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(["error" => "This email address is already registered."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "An internal server error occurred."]);
    }
}