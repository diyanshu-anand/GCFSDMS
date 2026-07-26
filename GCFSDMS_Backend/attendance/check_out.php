<!-- 

Responsibilities :

1. JWT Authentication

2. Delivery Agent Authorization

3. Company Isolation

4. Verify Active Check-in

5. Calculate Working Hours

6. Update Logout Time

7. Update Working Hours

8. Activity Log

9. Transaction

10. Standard Response

Request:
{}

{
    "success": true,
    "message": "Checked out successfully.",
    "data": {
        "attendance_id": 18,
        "login_time": "2026-07-26 09:05:22",
        "logout_time": "2026-07-26 18:12:41",
        "working_hours": 9.12
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Check Out API
 * ------------------------------------------------------------
 * Allows a delivery agent to mark check out.
 * Calculates total working hours automatically.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$authUser = authenticate();

$userId   = $authUser["user_id"];
$userRole = $authUser["role"];


/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

if ($userRole !== ROLE_DELIVERY_AGENT) {
    forbidden("Only delivery agents can check out.");
}


try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Fetch Active Attendance
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            attendance_id,
            login_time
        FROM attendance
        WHERE
            delivery_boy = ?
        AND
            DATE(login_time) = CURDATE()
        AND
            logout_time IS NULL
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        $conn->rollback();

        validationError(
            "No active check-in found."
        );

    }

    $attendance = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Calculate Working Hours
    |--------------------------------------------------------------------------
    */

    $loginTime = strtotime($attendance["login_time"]);

    $logoutTime = time();

    $workingHours = round(
        ($logoutTime - $loginTime) / 3600,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | Update Attendance
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE attendance
        SET
            logout_time = NOW(),
            working_hours = ?
        WHERE
            attendance_id = ?
    ");

    $stmt->bind_param(
        "di",
        $workingHours,
        $attendance["attendance_id"]
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

        "CHECK_OUT",

        [

            "attendance_id" => $attendance["attendance_id"],

            "working_hours" => $workingHours

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

        "Checked out successfully.",

        [

            "attendance_id" => $attendance["attendance_id"],

            "login_time" => $attendance["login_time"],

            "logout_time" => date("Y-m-d H:i:s"),

            "working_hours" => $workingHours

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CHECK OUT ERROR : " .
        $e->getMessage()
    );

    serverError();

}