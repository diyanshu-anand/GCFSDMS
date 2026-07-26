<!-- 
Responsibilities:
1. JWT Authentication

2. Company Isolation

3. Update Own Profile Only

4. Validate Name

5. Validate Email

6. Validate Phone

7. Prevent Duplicate Email

8. Prevent Duplicate Phone

9. Activity Log

10. Transaction

11. Standard Response 

Editable Fields:
1. name
2. email
3. phone

Keep these restricted :
1. role
2. company_id
3. status
4. password
5. created_at

Request:
{
    "name": "Rahul Sharma",
    "email": "rahul@example.com",
    "phone": "9876543210"
}

Response:
{
    "success": true,
    "message": "Profile updated successfully."
}
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Profile API
 * ------------------------------------------------------------
 * Updates the authenticated user's profile.
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

$name  = sanitize(trim(getParam($input, "name")));
$email = strtolower(trim(getParam($input, "email")));
$phone = trim(getParam($input, "phone"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (empty($name)) {
    validationError("Name is required.");
}

if (strlen($name) > 100) {
    validationError("Name cannot exceed 100 characters.");
}

if (empty($email)) {
    validationError("Email is required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    validationError("Invalid email address.");
}

if (empty($phone)) {
    validationError("Phone number is required.");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    validationError("Invalid phone number.");
}


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE
            email = ?
        AND
            company_id = ?
        AND
            user_id != ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "sii",
        $email,
        $companyId,
        $userId
    );

    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        $stmt->close();

        $conn->rollback();

        validationError("Email already exists.");

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Phone
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE
            phone = ?
        AND
            company_id = ?
        AND
            user_id != ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "sii",
        $phone,
        $companyId,
        $userId
    );

    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        $stmt->close();

        $conn->rollback();

        validationError("Phone number already exists.");

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE users
        SET
            name = ?,
            email = ?,
            phone = ?
        WHERE
            user_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "sssii",
        $name,
        $email,
        $phone,
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

        "PROFILE_UPDATED",

        [
            "updated_fields" => [
                "name",
                "email",
                "phone"
            ]
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

        "Profile updated successfully."

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "UPDATE PROFILE ERROR : " .
        $e->getMessage()
    );

    serverError();

}