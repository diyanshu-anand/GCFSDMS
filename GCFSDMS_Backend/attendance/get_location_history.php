<!-- 
Responsibilities:

1. JWT Authentication

2. Company Isolation

3. Role Based Access

4. Optional Delivery Boy Filter

5. Optional Date Filters

6. Attendance History

7. Latest First

8. Prepared Statements

9. Standard Response

Request:
Admin ( All History ) :
{}

Admin ( Single Delivery Agent ) :
{
    "delivery_boy": 7
}

Admin ( Date Filter ) :
{
    "delivery_boy": 7,
    "from_date": "2026-07-01",
    "to_date": "2026-07-26"
}

Delivery Agent :
{}

Response:
{
    "success": true,
    "message": "Attendance history fetched successfully.",
    "data": {
        "count": 3,
        "attendance": [
            {
                "attendance_id": 15,
                "user_id": 7,
                "name": "Rahul Sharma",
                "phone": "9876543210",
                "login_time": "2026-07-26 09:03:11",
                "logout_time": "2026-07-26 18:02:45",
                "working_hours": "8.99"
            },
            {
                "attendance_id": 14,
                "user_id": 7,
                "name": "Rahul Sharma",
                "phone": "9876543210",
                "login_time": "2026-07-25 09:01:18",
                "logout_time": "2026-07-25 17:45:20",
                "working_hours": "8.73"
            }
        ]
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Attendance History API
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
| Authorization
|--------------------------------------------------------------------------
*/

if (
    $userRole !== ROLE_ADMIN &&
    $userRole !== ROLE_MANAGER &&
    $userRole !== ROLE_DELIVERY_AGENT
) {
    forbidden("You are not authorized to view attendance history.");
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
| Delivery Agent Restriction
|--------------------------------------------------------------------------
*/

if ($userRole === ROLE_DELIVERY_AGENT) {

    $deliveryBoy = $userId;

}


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$query = "

SELECT

    a.attendance_id,

    u.user_id,

    u.name,

    u.phone,

    a.login_time,

    a.logout_time,

    a.working_hours

FROM attendance a

INNER JOIN users u

    ON a.delivery_boy = u.user_id

WHERE

    u.company_id = ?

";

$types = "i";

$params = [

    $companyId

];


/*
|--------------------------------------------------------------------------
| Delivery Boy Filter
|--------------------------------------------------------------------------
*/

if ($deliveryBoy > 0) {

    $query .= "

    AND

    a.delivery_boy = ?

    ";

    $types .= "i";

    $params[] = $deliveryBoy;

}


/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

if (!empty($fromDate)) {

    $query .= "

    AND DATE(a.login_time) >= ?

    ";

    $types .= "s";

    $params[] = $fromDate;

}

if (!empty($toDate)) {

    $query .= "

    AND DATE(a.login_time) <= ?

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

ORDER BY

a.login_time DESC

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

        "Attendance history fetched successfully.",

        [

            "count" => count($history),

            "attendance" => $history

        ]

    );

}
catch (Throwable $e) {

    logError(
        "GET ATTENDANCE HISTORY ERROR : " .
        $e->getMessage()
    );

    serverError();

}