<!-- 
Previous code has multiple issues,  
Needed a scratch code.

Before all the mistakes listed here is
backend flow:
Receive request -> Validate Json -> Check phone -> Verify passwords -> Check status ACTIVE -> Check company ACTIVE -> Update device_id -> Update last_login -> Generate JWT -> Return Json

Issues Existing in Login.php
1. Login implemented using email while the database schema uses phone.
2. References non-existent columns (id, password_hash) instead of user_id and password.
3. Does not follow the standardized API response format (success, message, data).
4. Does not validate user account status before allowing login.
5. Does not validate company status before allowing login.
6. Does not update last_login after successful authentication.
7. Does not store/update the user's device_id.
8. Returns only JWT token without user profile information.
9. Does not return company_id.
10. Does not return user role.
11. Does not return user's name.
12. Does not return phone.
13. JWT payload contains incorrect user identifier (id instead of user_id).
14. Uses hardcoded localhost issuer and audience values.
15. Does not return token expiration information.
16. No input sanitization (trimming/validation).
17. Generic error responses instead of standardized API responses.
18. Does not verify whether the user's role is valid.
19. Not aligned with the new database architecture.
20. Requires complete redesign to support the Delivery Management System.


Request Format:
{
    "phone": "9876543210",
    "password": "********",
    "device_id": "ANDROID_DEVICE_ID"
}

Success Response:
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "JWT_TOKEN",
        "expires_in": 3600,
        "user": {
            "user_id": 1,
            "company_id": 1,
            "name": "Divyanshu Anand",
            "phone": "9876543210",
            "role": "Admin"
        }
    }
}

Failure Response: 
{
    "success": false,
    "message": "Invalid phone or password"
}

login flow:
Validate Request → Read JSON → Validate Input → Fetch User → Verify Password → Validate User Status → Validate Company Status → Update Device ID → Update Last Login → Generate JWT → Return Response
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Login API
 * ------------------------------------------------------------
 */

require_once "../bootstrap.php";

/*
|--------------------------------------------------------------------------
| Only POST Allowed
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

/*
|--------------------------------------------------------------------------
| Read Inputs
|--------------------------------------------------------------------------
*/

$phone = sanitize(getParam($input, "phone"));
$password = getParam($input, "password");
$deviceId = sanitize(getParam($input, "device_id"));

/*
|--------------------------------------------------------------------------
| Validate Inputs
|--------------------------------------------------------------------------
*/

if (
    !required($phone) ||
    !required($password) ||
    !required($deviceId)
) {
    validationError(MSG_REQUIRED_FIELDS);
}

if (!validatePhone($phone)) {
    validationError("Invalid phone number.");
}

if (!validateLength($deviceId, 5, 255)) {
    validationError("Invalid device ID.");
}

/*
|--------------------------------------------------------------------------
| Find User
|--------------------------------------------------------------------------
*/

$query = "
SELECT
    u.user_id,
    u.company_id,
    u.name,
    u.phone,
    u.password,
    u.role,
    u.status,
    u.device_id,

    c.company_name,
    c.status AS company_status

FROM users u

INNER JOIN company c
ON u.company_id = c.company_id

WHERE u.phone = ?
LIMIT 1
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    logError("Login Prepare Failed : " . $conn->error);
    serverError();
}

$stmt->bind_param("s", $phone);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    logWarning("Unknown login attempt : {$phone}");

    unauthorized(MSG_INVALID_CREDENTIALS);
}

$user = $result->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $user["password"])) {

    logWarning("Invalid password : {$phone}");

    unauthorized(MSG_INVALID_CREDENTIALS);
}

/*
|--------------------------------------------------------------------------
| User Status
|--------------------------------------------------------------------------
*/

if ($user["status"] !== STATUS_ACTIVE) {

    unauthorized("User account is inactive.");
}

/*
|--------------------------------------------------------------------------
| Company Status
|--------------------------------------------------------------------------
*/

if ($user["company_status"] !== STATUS_ACTIVE) {

    unauthorized("Company account is inactive.");
}

/*
|--------------------------------------------------------------------------
| Update Login Information
|--------------------------------------------------------------------------
*/

$update = $conn->prepare("
UPDATE users
SET
device_id=?,
last_login=NOW()
WHERE user_id=?
");

$update->bind_param(
    "si",
    $deviceId,
    $user["user_id"]
);

$update->execute();

/*
|--------------------------------------------------------------------------
| Generate JWT
|--------------------------------------------------------------------------
*/

$token = generateJWT([

    "user_id"=>$user["user_id"],
    "company_id"=>$user["company_id"],
    "role"=>$user["role"]

]);

/*
|--------------------------------------------------------------------------
| Log Activity
|--------------------------------------------------------------------------
*/

logActivity(

    $conn,

    $user["user_id"],

    "LOGIN_SUCCESS",

    [

        "company"=>$user["company_id"]

    ]

);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

returnSuccess(

    MSG_LOGIN_SUCCESS,

    [

        "token"=>$token,

        "user"=>[

            "user_id"=>$user["user_id"],
            "company_id"=>$user["company_id"],
            "company_name"=>$user["company_name"],
            "name"=>$user["name"],
            "phone"=>$user["phone"],
            "role"=>$user["role"]

        ]

    ]

);