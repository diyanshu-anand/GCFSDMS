<?php
// takeStock.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$host = "localhost";
$db_name = "fsdms"; // <-- Make sure this is your database name!
$username = "root";
$password = "harsh"; // <-- Add your password if you have one!

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $exception->getMessage()]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// 1. Validation matching your new Postman JSON
if(empty($data->comp_id) || empty($data->product_id) || empty($data->delivery_boy) || empty($data->quantity) || empty($data->request_id)) {
    echo json_encode(["status" => "error", "message" => "Incomplete data provided."]);
    exit();
}

$comp_id = (int) $data->comp_id;
$product_id = (int) $data->product_id;
$delivery_boy = (int) $data->delivery_boy;
$quantity = (int) $data->quantity;
$request_id = $data->request_id;

if($quantity <= 0) {
    echo json_encode(["status" => "error", "message" => "Quantity must be greater than zero."]);
    exit();
}

try {
    $conn->beginTransaction();

    // 2. Update Products
    $updateQuery = "UPDATE T4_Products 
                    SET current_stock = current_stock - :quantity 
                    WHERE product_id = :product_id 
                    AND comp_id = :comp_id 
                    AND current_stock >= :quantity";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([
        ':quantity' => $quantity,
        ':product_id' => $product_id,
        ':comp_id' => $comp_id
    ]);

    if($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Insufficient stock or invalid product."]);
        exit();
    }

    // 3. Insert Log into the exact table you just created in the screenshot!
    $insertQuery = "INSERT INTO T5_Inventory_Logs (request_id, comp_id, product_id, delivery_boy, type, quantity) 
                    VALUES (:request_id, :comp_id, :product_id, :delivery_boy, 'REMOVE', :quantity)";
    
    $logStmt = $conn->prepare($insertQuery);
    $logStmt->execute([
        ':request_id' => $request_id,
        ':comp_id' => $comp_id,
        ':product_id' => $product_id,
        ':delivery_boy' => $delivery_boy,
        ':quantity' => $quantity
    ]);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Stock taken successfully."]);

} catch(Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Idempotency check!
    if ($e->getCode() == 23000) { 
        echo json_encode(["status" => "success", "message" => "Stock already taken (Duplicate Request)"]);
    } else {
        echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
    }
}
?>