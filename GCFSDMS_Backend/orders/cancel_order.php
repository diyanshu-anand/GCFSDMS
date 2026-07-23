<!-- 
JWT Authentication

Company Isolation

Admin / Manager Authorization

Validate Order

Only Pending / Accepted Orders Can Be Cancelled

Fetch Order Items

Restore Product Stock

Create Inventory IN Logs

Update Order Status

Activity Log

Transaction


Standard Response 


Request:
{
    "order_id": 101,
    "remarks": "Customer requested cancellation."
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Cancel Order API
 * ------------------------------------------------------------
 * Cancels an order and restores inventory.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$authUser = authenticate();

$companyId = $authUser["company_id"];
$userId    = $authUser["user_id"];
$userRole  = $authUser["role"];

/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

if (
    $userRole !== ROLE_ADMIN &&
    $userRole !== ROLE_MANAGER
) {
    forbidden("You are not authorized to cancel orders.");
}

/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$orderId = intval(getParam($input, "order_id"));
$remarks = sanitize(getParam($input, "remarks"));

if ($orderId <= 0) {
    validationError("Valid order ID is required.");
}

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Verify Order
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            status
        FROM orders
        WHERE
            order_id = ?
        AND
            company_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $orderId,
        $companyId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        $conn->rollback();

        notFound("Order not found.");
    }

    $order = $result->fetch_assoc();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Business Rule
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $order["status"],
            ["Pending", "Accepted"]
        )
    ) {

        $conn->rollback();

        validationError(
            "Only Pending or Accepted orders can be cancelled."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch Order Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            product_id,
            quantity
        FROM order_items
        WHERE order_id = ?
    ");

    $stmt->bind_param(
        "i",
        $orderId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($item = $result->fetch_assoc()) {

        /*
        ------------------------------------------------------------
        Current Stock
        ------------------------------------------------------------
        */

        $stockStmt = $conn->prepare("
            SELECT current_stock
            FROM products
            WHERE
                product_id = ?
            AND
                company_id = ?
            LIMIT 1
        ");

        $stockStmt->bind_param(
            "ii",
            $item["product_id"],
            $companyId
        );

        $stockStmt->execute();

        $stockResult = $stockStmt->get_result();

        $product = $stockResult->fetch_assoc();

        $stockStmt->close();

        $newStock =
            $product["current_stock"] + $item["quantity"];

        /*
        ------------------------------------------------------------
        Update Product Stock
        ------------------------------------------------------------
        */

        $updateStmt = $conn->prepare("
            UPDATE products
            SET current_stock = ?
            WHERE
                product_id = ?
            AND
                company_id = ?
        ");

        $updateStmt->bind_param(
            "iii",
            $newStock,
            $item["product_id"],
            $companyId
        );

        $updateStmt->execute();

        $updateStmt->close();

        /*
        ------------------------------------------------------------
        Inventory Log
        ------------------------------------------------------------
        */

        $movement = "IN";
        $referenceType = "RETURN";
        $referenceId = $orderId;

        $inventoryRemark =
            !empty($remarks)
                ? $remarks
                : "Order Cancelled #".$orderId;

        $inventoryStmt = $conn->prepare("
            INSERT INTO inventory
            (
                company_id,
                product_id,
                delivery_boy,
                movement,
                reference_type,
                reference_id,
                quantity,
                stock_after_transaction,
                remarks
            )
            VALUES
            (?, ?, NULL, ?, ?, ?, ?, ?, ?)
        ");

        $inventoryStmt->bind_param(
            "iissiis",
            $companyId,
            $item["product_id"],
            $movement,
            $referenceType,
            $referenceId,
            $item["quantity"],
            $newStock,
            $inventoryRemark
        );

        $inventoryStmt->execute();

        $inventoryStmt->close();

    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Update Order
    |--------------------------------------------------------------------------
    */

    $status = "Cancelled";

    $stmt = $conn->prepare("
        UPDATE orders
        SET
            status = ?
        WHERE
            order_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "sii",
        $status,
        $orderId,
        $companyId
    );

    $stmt->execute();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(
        $conn,
        $userId,
        "ORDER_CANCELLED",
        [
            "order_id" => $orderId
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    returnSuccess(
        "Order cancelled successfully.",
        [
            "order_id" => $orderId,
            "status" => "Cancelled"
        ]
    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CANCEL ORDER ERROR : ".$e->getMessage()
    );

    serverError();

}