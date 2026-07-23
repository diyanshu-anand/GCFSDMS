<!-- 
Responsibilities:
JWT Authentication

Admin / Manager Authorization

Company Isolation

Optional delivery_boy filter

Return latest live location

Join with users table

Standard Response 


Requests:
All delivery agents: {}

Single delivery Agents : 
{
    "delivery_boy": 7
}

Response:

{
    "success": true,
    "message": "Live locations fetched successfully.",
    "data": {
        "count": 2,
        "locations": [
            {
                "user_id": 5,
                "name": "Rahul Sharma",
                "phone": "9876543210",
                "latitude": 26.218345,
                "longitude": 78.182567,
                "accuracy": 3.50,
                "speed": 28.10,
                "battery_percentage": 84,
                "updated_at": "2026-07-23 22:40:18"
            },
            {
                "user_id": 7,
                "name": "Ankit Verma",
                "phone": "9876500000",
                "latitude": 26.225481,
                "longitude": 78.194521,
                "accuracy": 4.10,
                "speed": 19.30,
                "battery_percentage": 91,
                "updated_at": "2026-07-23 22:39:55"
            }
        ]
    }
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Live Location API
 * ------------------------------------------------------------
 * Returns the latest live location of delivery agents.
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
    forbidden("You are not authorized to view live locations.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$deliveryBoy = intval(getParam($input, "delivery_boy"));


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$query = "

SELECT

    u.user_id,
    u.name,
    u.phone,

    ll.latitude,
    ll.longitude,
    ll.accuracy,
    ll.speed,
    ll.battery_percentage,
    ll.updated_at

FROM live_location ll

INNER JOIN users u
    ON ll.delivery_boy = u.user_id

WHERE

    u.company_id = ?

AND

    u.role = ?

";

$types = "is";

$params = [
    $companyId,
    ROLE_DELIVERY_AGENT
];


/*
|--------------------------------------------------------------------------
| Optional Filter
|--------------------------------------------------------------------------
*/

if ($deliveryBoy > 0) {

    $query .= "

    AND

    u.user_id = ?

    ";

    $types .= "i";

    $params[] = $deliveryBoy;

}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$query .= "

ORDER BY

ll.updated_at DESC

";


try {

    $stmt = $conn->prepare($query);

    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $locations = [];

    while ($row = $result->fetch_assoc()) {

        $locations[] = $row;

    }

    $stmt->close();


    returnSuccess(

        "Live locations fetched successfully.",

        [

            "count" => count($locations),

            "locations" => $locations

        ]

    );

}
catch (Throwable $e) {

    logError(

        "GET LIVE LOCATION ERROR : "

        . $e->getMessage()

    );

    serverError();

}