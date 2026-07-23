<!-- 
 

Responsibilities:

JWT Authentication

Admin / Manager Authorization

Company Isolation

Delivery Boy Validation

Optional Date Filters

Location History

Latest First

Prepared Statements

Standard Response


Request:
    Complete history
{
    "delivery_boy": 7
}
    Date Range
{
    "delivery_boy": 7,
    "from_date": "2026-07-20",
    "to_date": "2026-07-23"
}


Response:
{
    "success": true,
    "message": "Location history fetched successfully.",
    "data": {
        "delivery_boy": 7,
        "count": 3,
        "history": [
            {
                "location_id": 101,
                "latitude": 26.21834,
                "longitude": 78.18274,
                "accuracy": 4.20,
                "speed": 25.40,
                "battery_percentage": 82,
                "recorded_at": "2026-07-23 22:55:10"
            },
            {
                "location_id": 100,
                "latitude": 26.21821,
                "longitude": 78.18260,
                "accuracy": 3.90,
                "speed": 22.80,
                "battery_percentage": 83,
                "recorded_at": "2026-07-23 22:54:55"
            }
        ]
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Location History API
 * ------------------------------------------------------------
 * Returns historical GPS records of a delivery agent.
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
    forbidden("You are not authorized to view location history.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$deliveryBoy = intval(getParam($input, "delivery_boy"));

$fromDate = trim(getParam($input, "from_date"));

$toDate = trim(getParam($input, "to_date"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($deliveryBoy <= 0) {

    validationError("Valid delivery agent is required.");

}


/*
|--------------------------------------------------------------------------
| Verify Delivery Agent
|--------------------------------------------------------------------------
*/

$status = STATUS_ACTIVE;

$role = ROLE_DELIVERY_AGENT;

$stmt = $conn->prepare("

SELECT user_id

FROM users

WHERE

user_id = ?

AND company_id = ?

AND role = ?

AND status = ?

LIMIT 1

");

$stmt->bind_param(

    "iiss",

    $deliveryBoy,

    $companyId,

    $role,

    $status

);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    $stmt->close();

    notFound("Delivery agent not found.");

}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$query = "

SELECT

location_id,

latitude,

longitude,

accuracy,

speed,

battery_percentage,

recorded_at

FROM location_history

WHERE

delivery_boy = ?

";

$types = "i";

$params = [$deliveryBoy];


/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

if (!empty($fromDate)) {

    $query .= "

    AND DATE(recorded_at) >= ?

    ";

    $types .= "s";

    $params[] = $fromDate;

}

if (!empty($toDate)) {

    $query .= "

    AND DATE(recorded_at) <= ?

    ";

    $types .= "s";

    $params[] = $toDate;

}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$query .= "

ORDER BY recorded_at DESC

";


try {

    $stmt = $conn->prepare($query);

    $stmt->bind_param(

        $types,

        ...$params

    );

    $stmt->execute();

    $result = $stmt->get_result();

    $history = [];

    while ($row = $result->fetch_assoc()) {

        $history[] = $row;

    }

    $stmt->close();

    returnSuccess(

        "Location history fetched successfully.",

        [

            "delivery_boy" => $deliveryBoy,

            "count" => count($history),

            "history" => $history

        ]

    );

}
catch(Throwable $e){

    logError(

        "GET LOCATION HISTORY ERROR : "

        .$e->getMessage()

    );

    serverError();

}