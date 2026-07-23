<!-- 
Responsibilities:
JWT Authentication

Company Isolation

Admin / Manager Authorization

Validate Order

Validate Delivery Agent

Ensure User Role = Delivery_agent

Update Order Assignment

Activity Log

Standard Response 


Request:
{
    "order_id": 101,
    "delivery_boy": 7
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Assign Delivery API
 * ------------------------------------------------------------
 * Assigns/Reassigns a delivery agent to an order.
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
    forbidden("You are not authorized to assign delivery.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$orderId     = intval(getParam($input, "order_id"));
$deliveryBoy = intval(getParam($input, "delivery_boy"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {
    validationError("Valid order ID is required.");
}

if ($deliveryBoy <= 0) {
    validationError("Valid delivery agent is required.");
}

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Verify Order
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT order_id, status
        FROM orders
        WHERE order_id = ?
        AND company_id = ?
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
        $order["status"] === "Delivered" ||
        $order["status"] === "Cancelled"
    ) {

        $conn->rollback();

        validationError(
            "Delivery agent cannot be changed for this order."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Delivery Agent
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;
    $deliveryRole = "Delivery_agent";

    $stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE
            user_id = ?
        AND
            company_id = ?
        AND
            role = ?
        AND
            status = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "iiss",
        $deliveryBoy,
        $companyId,
        $deliveryRole,
        $status
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();
        $conn->rollback();

        validationError("Invalid delivery agent.");
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Assign Delivery Agent
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE orders
        SET delivery_boy = ?
        WHERE
            order_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "iii",
        $deliveryBoy,
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
        "DELIVERY_ASSIGNED",
        [
            "order_id"      => $orderId,
            "delivery_boy"  => $deliveryBoy
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(
        "Delivery agent assigned successfully.",
        [
            "order_id" => $orderId,
            "delivery_boy" => $deliveryBoy
        ]
    );

} catch (Throwable $e) {

    $conn->rollback();

    logError(
        "ASSIGN DELIVERY ERROR : " . $e->getMessage()
    );

    serverError();

}