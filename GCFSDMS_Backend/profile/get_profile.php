<!-- 

Responsibilities:

1. JWT Authentication

2. Company Isolation

3. Return Logged-in User Details

4. Exclude Password Hash

5. Prepared Statements

6. Standard Response

Request:
{}

Response:
{
    "success": true,
    "message": "Profile fetched successfully.",
    "data": {
        "user_id": 7,
        "company_id": 2,
        "name": "Rahul Sharma",
        "email": "rahul@example.com",
        "phone": "9876543210",
        "role": "DELIVERY_AGENT",
        "status": "ACTIVE",
        "created_at": "2026-06-15 10:25:12"
    }
}

-->

<?php

/**
 * --------------------------------------------------------------------------------------
 * FSDMS Get Profile API
 * --------------------------------------------------------------------------------------
 * Returns the profile of the authenticated user. System's most simple API that interacts.
 * --------------------------------------------------------------------------------------
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


try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Profile
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;

    $stmt = $conn->prepare("

        SELECT

            user_id,
            company_id,
            name,
            email,
            phone,
            role,
            status,
            created_at

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

        notFound("Profile not found.");

    }

    $profile = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Profile fetched successfully.",

        $profile

    );

}
catch (Throwable $e) {

    logError(

        "GET PROFILE ERROR : " .

        $e->getMessage()

    );

    serverError();

}