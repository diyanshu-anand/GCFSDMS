<!-- 

Responsibilities:
1. JWT Authentication

2. Company Isolation

3. Role Based Access

4. Delivery Agent Can Delete Own Notifications

5. Admin / Manager Can Delete Company Notifications

6. Validate Notification

7. Hard Delete Notification

8. Activity Log

9. Transaction

10. Standard Response

Request:

{
    "notification_id": 15
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Delete Notification API
 * ------------------------------------------------------------
 * Permanently deletes a notification.
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
    forbidden("You are not authorized to delete notifications.");
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
            user_id

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

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Notification
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        DELETE FROM notifications

        WHERE

            notification_id = ?

    ");

    $stmt->bind_param(

        "i",

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

        "NOTIFICATION_DELETED",

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

        "Notification deleted successfully.",

        [

            "notification_id" => $notificationId

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(

        "DELETE NOTIFICATION ERROR : " .

        $e->getMessage()

    );

    serverError();

}