<!--
Responsibilities:
JWT Authentication
Clear device_id for this user (so device is no longer treated as logged in)
Activity Log
Standard Response

Notes:
- JWT in this system is stateless (no token blacklist table), so this
  endpoint cannot "invalidate" the token itself. What it does instead:
  clears users.device_id, matching what login.php sets on sign-in, and
  logs a LOGOUT_SUCCESS activity for audit purposes.
- If true server-side token invalidation is needed later, add a
  token_blacklist table (jti / expiry) and check it inside verifyJWT().

Request Body:
{}
    (no fields required — Authorization: Bearer <token> header only)

Response Body:
{
    "success": true,
    "message": "Logout successful.",
    "data": {
        "user_id": 5
    }
}
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Logout API
 * ------------------------------------------------------------
 * Clears device registration for the authenticated user
 * and logs the logout activity.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";


/*
|--------------------------------------------------------------------------
| Only POST Allowed
|--------------------------------------------------------------------------
*/

requireMethod("POST");


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$authUser = authenticate();

$userId = $authUser["user_id"];


try
{

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Clear Device Registration
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE users
        SET device_id = NULL
        WHERE user_id = ?
    ");

    $stmt->bind_param(
        "i",
        $userId
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
        "LOGOUT_SUCCESS"
    );


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(
        "Logout successful.",
        [
            "user_id" => $userId
        ]
    );

}
catch (Throwable $e)
{

    $conn->rollback();

    logError(
        "LOGOUT ERROR : " . $e->getMessage()
    );

    serverError();

}