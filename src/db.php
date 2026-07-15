<?php
// db.php
$host = 'db'; 
$db   = 'auth_api';
$user = 'api_user';
$pass = 'apipassword';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In production, don't expose raw error messages to the client
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}