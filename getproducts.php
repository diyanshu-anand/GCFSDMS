<?php
// getproducts.php

// 1. Set Headers for JSON and CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Include our dependencies using absolute paths
require __DIR__ . '/src/db_connection.php';
require __DIR__ . '/src/jwt_helper.php';

try {
    // 3. Mock JWT Authentication
    $tokenData = validateJwtAndGetPayload(); 
    // Map the token data to our newly standardized comp_id variable
    $comp_id = $tokenData['company_id']; 

    // 4. Get the Delta Sync Timestamp
    $last_sync = isset($_GET['last_sync']) ? $_GET['last_sync'] : '1970-01-01 00:00:00';

    // 5. The Secure 4-Step PDO Query (Now using comp_id!)
    $sql = "SELECT * FROM T4_Products WHERE comp_id = :comp_id AND updated_timestamp > :last_sync";
    
    $stmt = $conn->prepare($sql); 
    $stmt->bindParam(':comp_id', $comp_id);
    $stmt->bindParam(':last_sync', $last_sync);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Send the JSON Response
    echo json_encode([
        "status" => "success",
        "message" => count($products) . " products fetched.",
        "data" => $products
    ]);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred: " . $e->getMessage() 
    ]);
}
?>