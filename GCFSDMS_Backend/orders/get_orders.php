<!-- 
JWT Authentication

Company Isolation

Admin / Manager Authorization

Search (Customer Name / Phone)

Filter by Status

 Filter by Payment Status

 Filter by Delivery Boy

 Latest First

 Prepared Statements

Standard Response 

Request:

{
    "search": "Amit",
    "status": "Pending",
    "payment_status": "Paid",
    "delivery_boy": 5
}

Responses:
{
    "success": true,
    "message": "Orders fetched successfully.",
    "data": {
        "count": 2,
        "orders": [
            {
                "order_id": 101,
                "order_date": "2026-07-23 11:30:00",
                "status": "Pending",
                "payment_status": "Pending",
                "payment_mode": "Cash",
                "total_amount": "2450.00",
                "created_at": "2026-07-23 11:30:00",
                "delivered_at": null,

                "customer_id": 15,
                "customer_name": "Amit Sharma",
                "customer_phone": "9876543210",
                "customer_address": "Gwalior",

                "user_id": 7,
                "delivery_boy_name": "Rahul Kumar"
            }
        ]
    }
}


-->





<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Orders API
 * ------------------------------------------------------------
 * Returns all orders of the authenticated company.
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

$search        = trim(sanitize(getParam($input, "search")));
$status        = trim(getParam($input, "status"));
$paymentStatus = trim(getParam($input, "payment_status"));
$deliveryBoy   = intval(getParam($input, "delivery_boy"));


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$query = "

SELECT

o.order_id,
o.order_date,
o.status,
o.payment_status,
o.payment_mode,
o.total_amount,
o.created_at,
o.delivered_at,

c.customer_id,
c.name AS customer_name,
c.phone AS customer_phone,
c.address AS customer_address,

u.user_id,
u.name AS delivery_boy_name

FROM orders o

INNER JOIN customers c
ON o.customer_id = c.customer_id

LEFT JOIN users u
ON o.delivery_boy = u.user_id

WHERE

o.company_id = ?

";

$types = "i";
$params = [$companyId];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if (!empty($search)) {

    $query .= "

    AND
    (
        c.name LIKE ?
        OR
        c.phone LIKE ?
    )

    ";

    $keyword = "%" . $search . "%";

    $types .= "ss";

    $params[] = $keyword;
    $params[] = $keyword;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (!empty($status)) {

    $query .= " AND o.status = ? ";

    $types .= "s";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if (!empty($paymentStatus)) {

    $query .= " AND o.payment_status = ? ";

    $types .= "s";

    $params[] = $paymentStatus;
}


/*
|--------------------------------------------------------------------------
| Delivery Boy
|--------------------------------------------------------------------------
*/

if ($deliveryBoy > 0) {

    $query .= " AND o.delivery_boy = ? ";

    $types .= "i";

    $params[] = $deliveryBoy;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$query .= "

ORDER BY o.order_id DESC

";


try {

    $stmt = $conn->prepare($query);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $result = $stmt->get_result();

    $orders = [];

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;

    }

    $stmt->close();


    returnSuccess(

        "Orders fetched successfully.",

        [

            "count" => count($orders),

            "orders" => $orders

        ]

    );

}
catch (Throwable $e) {

    logError(

        "GET ORDERS ERROR : " .

        $e->getMessage()

    );

    serverError();

}