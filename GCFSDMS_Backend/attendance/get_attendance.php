<!-- 
Responsibilities:
1. JWT Authentication

2. Company Isolation

3. Role-based Access

4. Today's Attendance

5. Optional Delivery Agent Filter (Admin/Manager)

6. Join Users Table

7. Standard Response

Request:
Admin - {}

Admin ( Single Delivery Agent ) : 
{
    "delivery_boy": 5
}

Delivery Agent:
{}

Response:
{
    "success": true,
    "message": "Attendance fetched successfully.",
    "data": {
        "count": 2,
        "attendance": [
            {
                "attendance_id": 15,
                "user_id": 7,
                "name": "Rahul Sharma",
                "phone": "9876543210",
                "login_time": "2026-07-26 09:03:11",
                "logout_time": null,
                "working_hours": "0.00"
            },
            {
                "attendance_id": 14,
                "user_id": 9,
                "name": "Amit Singh",
                "phone": "9876500000",
                "login_time": "2026-07-26 08:56:48",
                "logout_time": "2026-07-26 17:21:14",
                "working_hours": "8.41"
            }
        ]
    }
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Attendance API
 * ------------------------------------------------------------
 * Returns today's attendance.
 * Admin/Manager:
 *      - All delivery agents
 *      - Specific delivery agent
 * Delivery Agent:
 *      - Own attendance only
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
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$deliveryBoy = intval(getParam($input, "delivery_boy"));


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
    forbidden("You are not authorized to view attendance.");
}


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

AND

    DATE(a.login_time) = CURDATE()

";

$types = "i";

$params = [

    $companyId

];


/*
|--------------------------------------------------------------------------
| Optional Delivery Boy Filter
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

    $attendance = [];

    while ($row = $result->fetch_assoc()) {

        $attendance[] = $row;

    }

    $stmt->close();

    returnSuccess(

        "Attendance fetched successfully.",

        [

            "count" => count($attendance),

            "attendance" => $attendance

        ]

    );

}
catch (Throwable $e) {

    logError(
        "GET ATTENDANCE ERROR : " .
        $e->getMessage()
    );

    serverError();

}