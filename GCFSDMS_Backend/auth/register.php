<!--
Responsibilities:
JWT Authentication            -> N/A (public endpoint, this IS how a JWT is first issued)
Company Isolation             -> N/A (creates a brand new company)
Validate Request
Check Duplicate Company (phone)
Check Duplicate Admin (phone)
Create Company
Create Admin User
Register Device (device_id)
Update Last Login
Generate JWT
Activity Log
Transaction
Standard Response

Notes:
- This endpoint is the signup counterpart to login.php. It creates a new
  company tenant along with its first Admin user, then logs that admin in
  immediately (same as login.php would), so the Android app can go straight
  from Register -> Home without a second /login call.
- Uses the same failure/success response shape as login.php.

Request Format:
{
    "company_name": "ABC Foods Pvt Ltd",
    "ownername": "Rahul Sharma",
    "phone": "9876543210",
    "address": "Delhi",

    "admin_name": "Rahul Sharma",
    "admin_phone": "9876543210",
    "admin_password": "********",

    "device_id": "ANDROID_DEVICE_ID"
}

Success Response:
{
    "success": true,
    "message": "Company registered successfully.",
    "data": {
        "token": "JWT_TOKEN",
        "expires_in": 3600,
        "user": {
            "user_id": 1,
            "company_id": 1,
            "company_name": "ABC Foods Pvt Ltd",
            "name": "Rahul Sharma",
            "phone": "9876543210",
            "role": "Admin"
        }
    }
}

Failure Response:
{
    "success": false,
    "message": "Company already exists."
}

register flow:
Validate Request -> Read JSON -> Validate Input -> Check Duplicate Company
-> Check Duplicate Admin -> Create Company -> Create Admin -> Register Device
-> Update Last Login -> Generate JWT -> Log Activity -> Commit -> Return Response
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Register API
 * ------------------------------------------------------------
 * Creates a new company tenant and its first Admin user,
 * then returns a JWT exactly like login.php would.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";

/*
|--------------------------------------------------------------------------
| Only POST Allowed
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

/*
|--------------------------------------------------------------------------
| Company Data
|--------------------------------------------------------------------------
*/

$companyName = sanitize(getParam($input, "company_name"));
$ownerName   = sanitize(getParam($input, "ownername"));
$phone       = sanitize(getParam($input, "phone"));
$address     = sanitize(getParam($input, "address"));

/*
|--------------------------------------------------------------------------
| Admin Data
|--------------------------------------------------------------------------
*/

$adminName     = sanitize(getParam($input, "admin_name"));
$adminPhone    = sanitize(getParam($input, "admin_phone"));
$adminPassword = getParam($input, "admin_password");

/*
|--------------------------------------------------------------------------
| Device Data
|--------------------------------------------------------------------------
*/

$deviceId = sanitize(getParam($input, "device_id"));

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    !required($companyName) ||
    !required($phone) ||
    !required($adminName) ||
    !required($adminPhone) ||
    !required($adminPassword) ||
    !required($deviceId)
) {
    validationError(MSG_REQUIRED_FIELDS);
}

if (!validateLength($companyName, 2, 150)) {
    validationError("Invalid company name.");
}

if (!validatePhone($phone)) {
    validationError("Invalid company phone.");
}

if (!validatePhone($adminPhone)) {
    validationError("Invalid admin phone.");
}

if (!validatePassword($adminPassword)) {
    validationError("Password must contain minimum 8 characters.");
}

if (!validateLength($deviceId, 5, 255)) {
    validationError("Invalid device ID.");
}

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Check Existing Company
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT company_id
        FROM company
        WHERE phone = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $phone
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $stmt->close();
        $conn->rollback();

        conflict("Company already exists.");
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Check Existing Admin User
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE phone = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $adminPhone
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $stmt->close();
        $conn->rollback();

        conflict("Admin user already exists.");
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Create Company
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;

    $stmt = $conn->prepare("
        INSERT INTO company
        (
            company_name,
            ownername,
            phone,
            address,
            status
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssss",
        $companyName,
        $ownerName,
        $phone,
        $address,
        $status
    );

    $stmt->execute();

    $companyId = $conn->insert_id;

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Create Admin User (with device already registered)
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $adminPassword,
        PASSWORD_DEFAULT
    );

    $role = ROLE_ADMIN;

    $stmt = $conn->prepare("
        INSERT INTO users
        (
            company_id,
            name,
            phone,
            password,
            role,
            device_id,
            last_login,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->bind_param(
        "issssss",
        $companyId,
        $adminName,
        $adminPhone,
        $passwordHash,
        $role,
        $deviceId,
        $status
    );

    $stmt->execute();

    $userId = $conn->insert_id;

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Generate JWT
    |--------------------------------------------------------------------------
    */

    $token = generateJWT([

        "user_id"    => $userId,
        "company_id" => $companyId,
        "role"       => $role

    ]);

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(

        $conn,

        $userId,

        "COMPANY_REGISTERED",

        [

            "company_id" => $companyId,

            "admin_user_id" => $userId

        ]

    );

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Company registered successfully.",

        [

            "token" => $token,

            "expires_in" => JWT_EXPIRE_TIME,

            "user" => [

                "user_id"      => $userId,
                "company_id"   => $companyId,
                "company_name" => $companyName,
                "name"         => $adminName,
                "phone"        => $adminPhone,
                "role"         => $role

            ]

        ],

        HTTP_CREATED

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "REGISTER ERROR : " . $e->getMessage()
    );

    serverError();

}
