<!-- 
 
Responsibilities

1. JWT Authentication
2. Company Isolation
3. Admin/Manager Access
4. Optional Filters
5. Search
6. Pagination (future-ready) For future implementation
7. Joins with Products & Users
8. Ordered by Latest First
 
Request :
{

    "search":"oil",
    "movement":"OUT",
    "reference_type":"ORDER"

}

Response :
{
    "success": true,
    "message": "Inventory fetched successfully.",
    "data": {
        "count": 2,
        "inventory": [
            {
                "log_id": 15,
                "movement": "OUT",
                "reference_type": "ORDER",
                "reference_id": 25,
                "quantity": 10,
                "stock_after_transaction": 90,
                "remarks": "Issued for Order #25",
                "created_at": "2026-07-23 18:25:00",

                "product_id": 3,
                "product_name": "Engine Oil",
                "sku": "EO100",

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
 * FSDMS Get Inventory API
 * ------------------------------------------------------------
 * Returns inventory history for the authenticated company.
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

$search        = trim(sanitize(getParam($input, "search")));
$movement      = strtoupper(trim(getParam($input, "movement")));
$referenceType = strtoupper(trim(getParam($input, "reference_type")));


try {

    /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */

    $query = "

    SELECT

        i.log_id,
        i.movement,
        i.reference_type,
        i.reference_id,
        i.quantity,
        i.stock_after_transaction,
        i.remarks,
        i.created_at,

        p.product_id,
        p.product_name,
        p.sku,

        u.user_id,
        u.name AS delivery_boy_name

    FROM inventory i

    INNER JOIN products p
        ON p.product_id = i.product_id

    LEFT JOIN users u
        ON u.user_id = i.delivery_boy

    WHERE

        i.company_id = ?

    ";

    $types = "i";

    $params = [$companyId];


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if ($search !== "") {

        $query .= "

        AND
        (
            p.product_name LIKE ?
            OR
            p.sku LIKE ?
        )

        ";

        $keyword = "%" . $search . "%";

        $types .= "ss";

        $params[] = $keyword;
        $params[] = $keyword;
    }


    /*
    |--------------------------------------------------------------------------
    | Movement Filter
    |--------------------------------------------------------------------------
    */

    if ($movement !== "") {

        $query .= "

        AND i.movement = ?

        ";

        $types .= "s";

        $params[] = $movement;
    }


    /*
    |--------------------------------------------------------------------------
    | Reference Type Filter
    |--------------------------------------------------------------------------
    */

    if ($referenceType !== "") {

        $query .= "

        AND i.reference_type = ?

        ";

        $types .= "s";

        $params[] = $referenceType;
    }


    /*
    |--------------------------------------------------------------------------
    | Ordering
    |--------------------------------------------------------------------------
    */

    $query .= "

    ORDER BY i.log_id DESC

    ";


    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare($query);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $result = $stmt->get_result();

    $inventory = [];

    while ($row = $result->fetch_assoc()) {

        $inventory[] = $row;

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Inventory fetched successfully.",

        [

            "count" => count($inventory),

            "inventory" => $inventory

        ]

    );

}
catch (Throwable $e) {

    logError(

        "GET INVENTORY ERROR : " . $e->getMessage()

    );

    serverError();

}