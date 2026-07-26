<!-- 

Responsibilities:

1. JWT Authentication

2. Company Isolation

3. Role Based Access

4. Delivery Agent Self Access

5. Admin / Manager Access

6. Optional User Filter

7. Optional Read Status Filter

8. Optional Type Filter

9. Latest First

10. Prepared Statements

11. Standard Response


Request :
Delivery Agent :
{}

Admin ( Specific User ) :
{
    "user_id": 7
}

Admin ( Unread Only ) :
{
    "is_read": 0
}

Admin ( By Type ) :
{
    "type": "ORDER"
}

Response :
{
    "success": true,
    "message": "Notifications fetched successfully.",
    "data": {
        "count": 2,
        "notifications": [
            {
                "notification_id": 12,
                "title": "New Order Assigned",
                "message": "Order #145 has been assigned to you.",
                "type": "ORDER",
                "is_read": 0,
                "created_at": "2026-07-26 18:20:12",
                "sender_id": 1,
                "sender_name": "Administrator",
                "receiver_id": 7,
                "receiver_name": "Rahul Sharma"
            }
        ]
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Notifications API
 * ------------------------------------------------------------
 * Returns notifications.
 * Admin/Manager:
 *      - Can view all company notifications
 *      - Can filter by user
 * Delivery Agent:
 *      - Can view only own notifications
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
    forbidden("You are not authorized to view notifications.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$requestedUser = intval(getParam($input, "user_id"));
$isRead = getParam($input, "is_read");
$type = strtoupper(trim(getParam($input, "type")));


/*
|--------------------------------------------------------------------------
| Delivery Agent Restriction
|--------------------------------------------------------------------------
*/

if ($userRole === ROLE_DELIVERY_AGENT) {
    $requestedUser = $userId;
}


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$query = "

SELECT

    n.notification_id,
    n.title,
    n.message,
    n.type,
    n.is_read,
    n.created_at,

    sender.user_id AS sender_id,
    sender.name AS sender_name,

    receiver.user_id AS receiver_id,
    receiver.name AS receiver_name

FROM notifications n

INNER JOIN users sender
    ON n.sender_id = sender.user_id

INNER JOIN users receiver
    ON n.user_id = receiver.user_id

WHERE

n.company_id = ?

";

$types = "i";
$params = [$companyId];


/*
|--------------------------------------------------------------------------
| User Filter
|--------------------------------------------------------------------------
*/

if ($requestedUser > 0) {

    $query .= "
        AND n.user_id = ?
    ";

    $types .= "i";
    $params[] = $requestedUser;
}


/*
|--------------------------------------------------------------------------
| Read Filter
|--------------------------------------------------------------------------
*/

if ($isRead !== "" && $isRead !== null) {

    $query .= "
        AND n.is_read = ?
    ";

    $types .= "i";
    $params[] = intval($isRead);
}


/*
|--------------------------------------------------------------------------
| Type Filter
|--------------------------------------------------------------------------
*/

if (!empty($type)) {

    $allowedTypes = [
        "ORDER",
        "ATTENDANCE",
        "SYSTEM",
        "GENERAL"
    ];

    if (!in_array($type, $allowedTypes)) {
        validationError("Invalid notification type.");
    }

    $query .= "
        AND n.type = ?
    ";

    $types .= "s";
    $params[] = $type;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$query .= "
ORDER BY
n.created_at DESC
";


try {

    $stmt = $conn->prepare($query);

    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();

    returnSuccess(
        "Notifications fetched successfully.",
        [
            "count" => count($notifications),
            "notifications" => $notifications
        ]
    );

}
catch (Throwable $e) {

    logError(
        "GET NOTIFICATIONS ERROR : " .
        $e->getMessage()
    );

    serverError();

}