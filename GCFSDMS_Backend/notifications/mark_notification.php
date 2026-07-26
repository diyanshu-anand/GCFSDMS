<!-- 
Responsibilities:
1. JWT Authentication

2. Company Isolation

3. Role Based Access

4. Delivery Agent Can Mark Own Notifications

5. Admin / Manager Can Mark Any Company Notification

6. Validate Notification

7. Update Read Status

8. Activity Log

9. Standard Response

Request:
Delivery :
{
    "notification_id": 15
}

Admin/Manager :
{
    "notification_id": 15
}

Response:
{
    "success": true,
    "message": "Notification marked as read.",
    "data": {
        "notification_id": 15,
        "is_read": true
    }
}
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Mark Notification Read API
 * ------------------------------------------------------------
 * Marks a notification as read.
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
    forbidden("You are not authorized to update notifications.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$notificationId = intval(getParam($input, "notification_id"));

if ($notificationId <= 0) {
    validationError("Valid notification ID is required.");
}


try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Verify Notification
    |--------------------------------------------------------------------------
    */

    $query = "

        SELECT
            notification_id,
            user_id,
            is_read
        FROM notifications
        WHERE
            notification_id = ?
        AND
            company_id = ?

    ";

    if ($userRole === ROLE_DELIVERY_AGENT) {

        $query .= "

            AND user_id = ?

        ";

    }

    $query .= "

        LIMIT 1

    ";

    $stmt = $conn->prepare($query);

    if ($userRole === ROLE_DELIVERY_AGENT) {

        $stmt->bind_param(

            "iii",

            $notificationId,

            $companyId,

            $userId

        );

    } else {

        $stmt->bind_param(

            "ii",

            $notificationId,

            $companyId

        );

    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        $conn->rollback();

        notFound("Notification not found.");

    }

    $notification = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Already Read
    |--------------------------------------------------------------------------
    */

    if ($notification["is_read"]) {

        $conn->commit();

        returnSuccess(

            "Notification already marked as read.",

            [

                "notification_id" => $notificationId

            ]

        );

    }


    /*
    |--------------------------------------------------------------------------
    | Update Notification
    |--------------------------------------------------------------------------
    */

    $isRead = 1;

    $stmt = $conn->prepare("

        UPDATE notifications

        SET

            is_read = ?

        WHERE

            notification_id = ?

    ");

    $stmt->bind_param(

        "ii",

        $isRead,

        $notificationId

    );

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

        "NOTIFICATION_READ",

        [

            "notification_id" => $notificationId

        ]

    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Notification marked as read.",

        [

            "notification_id" => $notificationId,

            "is_read" => true

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(

        "MARK NOTIFICATION READ ERROR : " .

        $e->getMessage()

    );

    serverError();

}