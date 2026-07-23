<!-- 
Responsibilities:
 JWT Authentication

 Company Isolation

 Admin / Manager Authorization

 Validate Order ID

 Fetch Order Details

 Fetch Order Items

 Standard Response




Request:

{
    "order_id": 101
} 

Response:

{
    "success": true,
    "message": "Order fetched successfully.",
    "data": {
        "order_id": 101,
        "company_id": 1,
        "customer_id": 12,
        "delivery_boy": 5,

        "status": "Pending",
        "payment_status": "Pending",
        "payment_mode": "Cash",

        "total_amount": "3250.00",

        "remarks": "Deliver before 5 PM",

        "created_at": "2026-07-23 14:15:22",

        "customer_name": "Amit Sharma",
        "customer_phone": "9876543210",
        "customer_address": "Gwalior",

        "delivery_boy_name": "Rahul Kumar",
        "delivery_boy_phone": "9876500000",

        "items": [
            {
                "item_id": 1,
                "product_id": 5,
                "product_name": "Engine Oil",
                "quantity": 3,
                "price": "550.00",
                "unit": "Bottle",
                "total_amount": "1650.00"
            },
            {
                "item_id": 2,
                "product_id": 8,
                "product_name": "Brake Fluid",
                "quantity": 2,
                "price": "800.00",
                "unit": "Bottle",
                "total_amount": "1600.00"
            }
        ]
    }
}



-->


<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Order API
 * ------------------------------------------------------------
 * Returns complete details of a single order.
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
    forbidden("You are not authorized to view orders.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$orderId = intval(getParam($input, "order_id"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {
    validationError("Valid order ID is required.");
}


try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Order
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        SELECT

            o.*,

            c.name AS customer_name,
            c.phone AS customer_phone,
            c.address AS customer_address,

            u.name AS delivery_boy_name,
            u.phone AS delivery_boy_phone

        FROM orders o

        INNER JOIN customers c
            ON o.customer_id = c.customer_id

        LEFT JOIN users u
            ON o.delivery_boy = u.user_id

        WHERE

            o.order_id = ?

        AND

            o.company_id = ?

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

        notFound("Order not found.");

    }

    $order = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Fetch Order Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        SELECT

            item_id,
            product_id,
            product_name,
            quantity,
            price,
            unit,
            total_amount

        FROM order_items

        WHERE

            order_id = ?

        ORDER BY item_id ASC

    ");

    $stmt->bind_param(
        "i",
        $orderId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $items = [];

    while ($row = $result->fetch_assoc()) {

        $items[] = $row;

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Attach Items
    |--------------------------------------------------------------------------
    */

    $order["items"] = $items;


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Order fetched successfully.",

        $order

    );

}
catch (Throwable $e) {

    logError(
        "GET ORDER ERROR : " . $e->getMessage()
    );

    serverError();

}