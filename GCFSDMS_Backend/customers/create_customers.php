<!-- 
 
Authenticate user using JWT
Get company_id from JWT (never from request)
Validate request
Check duplicate customer (same phone within same company)
Insert customer
Log activity
Return standard response

Role	Create Customer
Admin	yes
Manager	yes
Delivery Agent	yes


Request body:
{
    "name":"Rahul Sharma",
    "phone":"9876543210",
    "address":"Sector 21, Noida",
    "latitude":"28.567890",
    "longitude":"77.321456"
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Customer API
 * ------------------------------------------------------------
 * Creates a customer for authenticated company.
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


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$name = sanitize(getParam($input, "name"));
$phone = sanitize(getParam($input, "phone"));
$address = sanitize(getParam($input, "address"));
$latitude = sanitize(getParam($input, "latitude"));
$longitude = sanitize(getParam($input, "longitude"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (!required($name)) {
    validationError("Customer name is required.");
}

if (!validateLength($name, 2, 150)) {
    validationError("Customer name must be between 2 and 150 characters.");
}

if (!empty($phone) && !validatePhone($phone)) {
    validationError("Invalid phone number.");
}

if (!empty($latitude) && !is_numeric($latitude)) {
    validationError("Invalid latitude.");
}

if (!empty($longitude) && !is_numeric($longitude)) {
    validationError("Invalid longitude.");
}


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Duplicate Phone Check
    |--------------------------------------------------------------------------
    */

    if (!empty($phone)) {

        $stmt = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE company_id = ?
            AND phone = ?
            AND status = ?
            LIMIT 1
        ");

        $active = STATUS_ACTIVE;

        $stmt->bind_param(
            "iss",
            $companyId,
            $phone,
            $active
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $stmt->close();
            $conn->rollback();

            conflict("Customer already exists.");
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Customer
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;

    $stmt = $conn->prepare("
        INSERT INTO customers
        (
            company_id,
            name,
            phone,
            address,
            latitude,
            longitude,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssss",
        $companyId,
        $name,
        $phone,
        $address,
        $latitude,
        $longitude,
        $status
    );

    $stmt->execute();

    $customerId = $conn->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(

        $conn,

        $userId,

        "CUSTOMER_CREATED",

        [

            "customer_id" => $customerId,

            "company_id" => $companyId

        ]

    );


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Customer created successfully.",

        [

            "customer_id" => $customerId

        ],

        HTTP_CREATED

    );

}
catch(Throwable $e)
{

    $conn->rollback();

    logError(

        "CREATE CUSTOMER ERROR : ".$e->getMessage()

    );

    serverError();

}