<?php
// return_stock.php

header('Content-Type: application/json');

require_once 'src/db_connection.php'; 
require_once 'src/jwt_helper.php';

$tokenData = validateJwtAndGetPayload();
if (!$tokenData) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$agentId = $tokenData['user_id'];
$companyId = $tokenData['company_id'];

$data = json_decode(file_get_contents("php://input"), true);
$productId = $data['product_id'] ?? null;
$returnedQuantity = $data['quantity'] ?? null;
$requestId = $data['request_id'] ?? null; // Added this line

if (!$productId || !$returnedQuantity || $returnedQuantity <= 0 || !$requestId) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid product ID, quantity, or missing request_id"]);
    exit();
}

try {
    // 1. Calculate Current Van Stock dynamically (Fixed with exact column names from your screenshots)
    $stockQuery = $conn->prepare("
        SELECT 
            (SELECT COALESCE(SUM(quantity), 0) FROM t5_inventory_logs WHERE delivery_boy = ? AND product_id = ? AND type = 'REMOVE') 
            -
            (SELECT COALESCE(SUM(quantity), 0) FROM t5_inventory_logs WHERE delivery_boy = ? AND product_id = ? AND type = 'RETURN') 
            -
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM t7_order_items oi 
             JOIN t6_orders o ON oi.order_id = o.order_id 
             WHERE o.delivery_boy = ? AND oi.product_id = ? AND o.status = 'DELIVERED') 
        AS current_van_stock
    ");
    
    $stockQuery->execute([$agentId, $productId, $agentId, $productId, $agentId, $productId]);
    $vanStockResult = $stockQuery->fetch(PDO::FETCH_ASSOC);
    $currentVanStock = $vanStockResult['current_van_stock'] ?? 0;

    // 2. The Edge Case Check
    if ($returnedQuantity > $currentVanStock) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Cannot return more stock than currently held in van. You hold: $currentVanStock"
        ]);
        exit();
    }

    // 3. Begin the ACID Transaction
    $conn->beginTransaction();

    // 4. Lock the row to prevent race conditions (Fixed with your table names)
    $stmt = $conn->prepare("SELECT current_stock FROM t4_products WHERE product_id = ? AND comp_id = ? FOR UPDATE");
    $stmt->execute([$productId, $companyId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Product not found in this company.");
    }

    $newWarehouseStock = $product['current_stock'] + $returnedQuantity;

    // 5. Update Warehouse (Your correct Answer B!)
    $updateStmt = $conn->prepare("UPDATE t4_products SET current_stock = ? WHERE product_id = ?");
    $updateStmt->execute([$newWarehouseStock, $productId]);

    // 6. Audit Log (Fixed column names)
   // 6. Audit Log (Now includes request_id)
    $logStmt = $conn->prepare("
        INSERT INTO t5_inventory_logs (request_id, comp_id, product_id, delivery_boy, type, quantity) 
        VALUES (?, ?, ?, ?, 'RETURN', ?)
    ");
    $logStmt->execute([$requestId, $companyId, $productId, $agentId, $returnedQuantity]);

    // 7. Commit everything permanently
    $conn->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Stock returned successfully.",
        "new_warehouse_stock" => $newWarehouseStock
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Transaction failed: " . $e->getMessage()]);
}
?>