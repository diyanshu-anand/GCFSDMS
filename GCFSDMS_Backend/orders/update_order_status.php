<!-- 
Responsibilities:
JWT Authentication

Company Isolation

Admin / Manager Authorization

Validate Order

Validate New Status

Enforce Valid Status Transition

Require Delivery Agent Before Progressing

Set delivered_at on Delivered

Activity Log

Transaction

Standard Response 

Request:
{
    "order_id": 101,
    "status": "Picked"
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Order Status API
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
    forbidden("You are not authorized to update orders.");
}

/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$orderId  = intval(getParam($input, "order_id"));
$newStatus = trim(getParam($input, "status"));

if ($orderId <= 0) {
    validationError("Valid order ID is required.");
}

$allowedStatus = [
    "Pending",
    "Accepted",
    "Picked",
    "Out for Delivery",
    "Delivered",
    "Cancelled"
];

if (!in_array($newStatus, $allowedStatus)) {
    validationError("Invalid order status.");
}

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Fetch Current Order
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            status,
            delivery_boy
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

    if ($result->num_rows == 0) {

        $stmt->close();

        $conn->rollback();

        notFound("Order not found.");
    }

    $order = $result->fetch_assoc();

    $stmt->close();

    $currentStatus = $order["status"];

    /*
    |--------------------------------------------------------------------------
    | Transition Rules
    |--------------------------------------------------------------------------
    */

    $validTransitions = [

        "Pending" => ["Accepted","Cancelled"],

        "Accepted" => ["Picked","Cancelled"],

        "Picked" => ["Out for Delivery"],

        "Out for Delivery" => ["Delivered"],

        "Delivered" => [],

        "Cancelled" => []

    ];

    if (!in_array($newStatus, $validTransitions[$currentStatus])) {

        $conn->rollback();

        validationError(
            "Invalid status transition."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delivery Agent Required
    |--------------------------------------------------------------------------
    */

    if (

        in_array(

            $newStatus,

            [

                "Accepted",

                "Picked",

                "Out for Delivery",

                "Delivered"

            ]

        )

        &&

        empty($order["delivery_boy"])

    ) {

        $conn->rollback();

        validationError(
            "Assign a delivery agent first."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Status
    |--------------------------------------------------------------------------
    */

    if ($newStatus == "Delivered") {

        $stmt = $conn->prepare("

            UPDATE orders

            SET

                status = ?,

                delivered_at = NOW()

            WHERE

                order_id = ?

            AND

                company_id = ?

        ");

        $stmt->bind_param(

            "sii",

            $newStatus,

            $orderId,

            $companyId

        );

    } else {

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

            $newStatus,

            $orderId,

            $companyId

        );

    }

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

        "ORDER_STATUS_UPDATED",

        [

            "order_id" => $orderId,

            "from" => $currentStatus,

            "to" => $newStatus

        ]

    );

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    returnSuccess(

        "Order status updated successfully.",

        [

            "order_id" => $orderId,

            "old_status" => $currentStatus,

            "new_status" => $newStatus

        ]

    );

}
catch(Throwable $e){

    $conn->rollback();

    logError(
        "UPDATE ORDER STATUS ERROR : ".$e->getMessage()
    );

    serverError();

}