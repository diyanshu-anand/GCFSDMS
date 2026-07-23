<!-- 
Responsibilities:

JWT Authentication
Company Isolation
Admin / Manager Authorization
Validate log_id
Verify Log Exists
Join Product & User Details
Standard Response

Request :
{
    "log_id": 25
}

Response:
{
    "success": true,
    "message": "Inventory log fetched successfully.",
    "data": {
        "log_id": 25,
        "company_id": 1,
        "product_id": 8,
        "delivery_boy": 4,

        "movement": "OUT",
        "reference_type": "ORDER",
        "reference_id": 145,

        "quantity": 6,
        "stock_after_transaction": 94,
        "remarks": "Issued for Order #145",

        "created_at": "2026-07-23 18:45:20",

        "product_name": "Engine Oil 1L",
        "sku": "EO1001",
        "category": "Lubricants",
        "price": "550.00",
        "unit": 1,
        "current_stock": 94,

        "delivery_boy_name": "Rahul Kumar",
        "delivery_boy_phone": "9876543210"
    }
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Inventory Log API
 * ------------------------------------------------------------
 * Returns a single inventory transaction.
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
    forbidden("You are not authorized to view inventory.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$logId = intval(getParam($input, "log_id"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($logId <= 0) {
    validationError("Valid inventory log ID is required.");
}


try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Inventory Log
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        SELECT

            i.log_id,
            i.company_id,
            i.product_id,
            i.delivery_boy,
            i.movement,
            i.reference_type,
            i.reference_id,
            i.quantity,
            i.stock_after_transaction,
            i.remarks,
            i.created_at,

            p.product_name,
            p.sku,
            p.category,
            p.price,
            p.unit,
            p.current_stock,

            u.name AS delivery_boy_name,
            u.phone AS delivery_boy_phone

        FROM inventory i

        INNER JOIN products p
            ON p.product_id = i.product_id

        LEFT JOIN users u
            ON u.user_id = i.delivery_boy

        WHERE

            i.log_id = ?

        AND

            i.company_id = ?

        LIMIT 1

    ");

    $stmt->bind_param(
        "ii",
        $logId,
        $companyId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        notFound("Inventory log not found.");

    }

    $inventoryLog = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Inventory log fetched successfully.",

        $inventoryLog

    );

}
catch (Throwable $e) {

    logError(
        "GET INVENTORY LOG ERROR : " . $e->getMessage()
    );

    serverError();

}