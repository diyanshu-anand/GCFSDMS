<!-- 
 
Responsibilites :
1. JWT Authentication

2. Delivery Agent Authorization

3. Company Isolation

4. Prevent Multiple Check-ins

5. Create Attendance Entry

6. Activity Log

7. Standard Response 

Request:
{}


Buisness Rules:
1. One check-in per day

2. Check-in time = Server Time

3. logout_time = NULL

4. working_hours = 0.00

5. If already checked in and not checked out → Reject

6. Only Delivery Agents can check in

SQL logic :
SELECT attendance_id
FROM attendance
WHERE delivery_boy = ?
AND DATE(login_time) = CURDATE()
AND logout_time IS NULL;

If record exists, Above ensures you are checked in......

Otherwise:
INSERT INTO attendance
(
    delivery_boy,
    login_time,
    working_hours
)
VALUES
(
    ?,
    NOW(),
    0.00
);


Response :
{
    "success": true,
    "message": "Checked in successfully.",
    "data": {
        "login_time": "2026-07-26 09:10:52"
    }
}

-->


<?php

/**
 * ------------------------------------------------------------
 * FSDMS Check In API
 * ------------------------------------------------------------
 * Allows a delivery agent to mark attendance.
 * Only one active check-in is allowed per day.
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
    forbidden("Only delivery agents can check in.");
}


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Check Existing Attendance
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            attendance_id
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

    if ($result->num_rows > 0) {

        $stmt->close();

        $conn->rollback();

        validationError(
            "You are already checked in."
        );

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Create Attendance
    |--------------------------------------------------------------------------
    */

    $workingHours = 0.00;

    $stmt = $conn->prepare("
        INSERT INTO attendance
        (
            delivery_boy,
            login_time,
            working_hours
        )
        VALUES
        (
            ?,
            NOW(),
            ?
        )
    ");

    $stmt->bind_param(
        "id",
        $userId,
        $workingHours
    );

    $stmt->execute();

    $attendanceId = $stmt->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(

        $conn,

        $userId,

        "CHECK_IN",

        [

            "attendance_id" => $attendanceId,

            "login_time" => date("Y-m-d H:i:s")

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

        "Checked in successfully.",

        [

            "attendance_id" => $attendanceId,

            "login_time" => date("Y-m-d H:i:s")

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CHECK IN ERROR : " .
        $e->getMessage()
    );

    serverError();

}