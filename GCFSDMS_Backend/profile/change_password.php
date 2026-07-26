<!-- 
Responsibilities:

1. JWT Authentication

2. Company Isolation

3. Verify Current Password

4. Validate New Password

5. Prevent Same Password

6. Hash Password

7. Update Password

8. Activity Log

9. Transaction

10. Standard Response


Request:

{
    "current_password": "OldPassword@123",
    "new_password": "NewPassword@123",
    "confirm_password": "NewPassword@123"
}

Response:
{
    "success": true,
    "message": "Password changed successfully."
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Change Password API
 * ------------------------------------------------------------
 * Allows the authenticated user to change their password.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$authUser = authenticate();

$userId    = $authUser["user_id"];
$companyId = $authUser["company_id"];


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$currentPassword = getParam($input, "current_password");
$newPassword     = getParam($input, "new_password");
$confirmPassword = getParam($input, "confirm_password");


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (empty($currentPassword)) {
    validationError("Current password is required.");
}

if (empty($newPassword)) {
    validationError("New password is required.");
}

if (strlen($newPassword) < 8) {
    validationError("Password must be at least 8 characters long.");
}

if ($newPassword !== $confirmPassword) {
    validationError("New password and confirm password do not match.");
}

if ($currentPassword === $newPassword) {
    validationError("New password must be different from the current password.");
}


try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Fetch Current Password Hash
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;

    $stmt = $conn->prepare("
        SELECT password
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
        $userId,
        $companyId,
        $status
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        $conn->rollback();

        notFound("User not found.");

    }

    $user = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Verify Current Password
    |--------------------------------------------------------------------------
    */

    if (!password_verify($currentPassword, $user["password"])) {

        $conn->rollback();

        validationError("Current password is incorrect.");

    }


    /*
    |--------------------------------------------------------------------------
    | Hash New Password
    |--------------------------------------------------------------------------
    */

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);


    /*
    |--------------------------------------------------------------------------
    | Update Password
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE users
        SET
            password = ?
        WHERE
            user_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "sii",
        $hashedPassword,
        $userId,
        $companyId
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
        "PASSWORD_CHANGED",
        []
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
        "Password changed successfully."
    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CHANGE PASSWORD ERROR : " .
        $e->getMessage()
    );

    serverError();

}