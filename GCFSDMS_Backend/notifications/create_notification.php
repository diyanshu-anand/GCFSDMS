<!-- 
Responsibilities:
1. JWT Authentication

2. Admin / Manager Authorization

3. Company Isolation

4. Validate Recipient

5. Validate Title

6. Validate Message

7. Validate Notification Type

8. Create Notification

9. Activity Log

10. Transaction

11. Standard Response


Request:
{
    "user_id": 7,
    "title": "New Order Assigned",
    "message": "Order #125 has been assigned to you.",
    "type": "ORDER"
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Notification API
 * ------------------------------------------------------------
 * Creates a notification for a user.
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
    forbidden("You are not authorized to create notifications.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$receiverId = intval(getParam($input, "user_id"));

$title = sanitize(trim(getParam($input, "title")));

$message = sanitize(trim(getParam($input, "message")));

$type = strtoupper(trim(getParam($input, "type")));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($receiverId <= 0) {
    validationError("Valid user is required.");
}

if (empty($title)) {
    validationError("Title is required.");
}

if (strlen($title) > 255) {
    validationError("Title cannot exceed 255 characters.");
}

if (empty($message)) {
    validationError("Message is required.");
}

$allowedTypes = [
    "ORDER",
    "ATTENDANCE",
    "SYSTEM",
    "GENERAL"
];

if (!in_array($type, $allowedTypes)) {
    validationError("Invalid notification type.");
}


/*
|--------------------------------------------------------------------------
| Verify Receiver
|--------------------------------------------------------------------------
*/

$status = STATUS_ACTIVE;

$stmt = $conn->prepare("
    SELECT
        user_id
    FROM users
    WHERE
        user_id = ?
    AND
        company_id = ?
    AND
        status = ?
    LIMIT 1
");

$stmt->bind_param(
    "iis",
    $receiverId,
    $companyId,
    $status
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    notFound("User not found.");

}

$stmt->close();


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Create Notification
    |--------------------------------------------------------------------------
    */

    $isRead = 0;

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            company_id,
            sender_id,
            user_id,
            title,
            message,
            type,
            is_read
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiisssi",
        $companyId,
        $userId,
        $receiverId,
        $title,
        $message,
        $type,
        $isRead
    );

    $stmt->execute();

    $notificationId = $stmt->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(

        $conn,

        $userId,

        "NOTIFICATION_CREATED",

        [

            "notification_id" => $notificationId,

            "receiver_id" => $receiverId,

            "type" => $type

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

        "Notification created successfully.",

        [

            "notification_id" => $notificationId

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CREATE NOTIFICATION ERROR : " .
        $e->getMessage()
    );

    serverError();

}