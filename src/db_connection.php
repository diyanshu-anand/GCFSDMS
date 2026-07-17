<?php
// src/db_connection.php
$host = "localhost";
$db_name = "fsdms"; 
$username = "root";
$password = "harsh"; 

try {
    // We use $conn as the variable name to maintain consistency across your API
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    
    // Crucial for debugging: Force PDO to throw exceptions on errors
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database Connection Failed: " . $exception->getMessage()
    ]);
    exit();
}
?>