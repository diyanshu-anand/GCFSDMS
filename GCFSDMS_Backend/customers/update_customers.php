<!-- 
Responsibilites / Work to be done by this : 

    1. Authenticate JWT
    2. Get company_id from JWT
    3. Validate request
    4. Ensure customer belongs to authenticated company
    5. Check duplicate phone within the same company (excluding current customer)
    6. Update customer
    7. Log activity
    8. Return standard response 

Request Body:
    {
        "customer_id": 12,
        "name": "Rahul Sharma",
        "phone": "9876543210",
        "address": "New Address",
        "latitude": "28.613939",
        "longitude": "77.209021"
    }

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Customer API
 * ------------------------------------------------------------
 * Updates customer details belonging to the
 * authenticated company.
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

$input = getJsonInput("PUT");


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$customerId = intval(getParam($input, "customer_id"));

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

if ($customerId <= 0) {
    validationError("Valid customer ID is required.");
}

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


try
{

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Verify Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT customer_id
        FROM customers
        WHERE customer_id = ?
        AND company_id = ?
        AND status = ?
        LIMIT 1
    ");

    $status = STATUS_ACTIVE;

    $stmt->bind_param(
        "iis",
        $customerId,
        $companyId,
        $status
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0)
    {
        $stmt->close();
        $conn->rollback();

        notFound("Customer not found.");
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Duplicate Phone Check
    |--------------------------------------------------------------------------
    */

    if (!empty($phone))
    {

        $stmt = $conn->prepare("
            SELECT customer_id
            FROM customers
            WHERE company_id = ?
            AND phone = ?
            AND customer_id <> ?
            AND status = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "isis",
            $companyId,
            $phone,
            $customerId,
            $status
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0)
        {
            $stmt->close();
            $conn->rollback();

            conflict("Phone number already exists.");
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Update Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE customers
        SET
            name = ?,
            phone = ?,
            address = ?,
            latitude = ?,
            longitude = ?
        WHERE
            customer_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "sssssii",
        $name,
        $phone,
        $address,
        $latitude,
        $longitude,
        $customerId,
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

        "CUSTOMER_UPDATED",

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

        "Customer updated successfully.",

        [

            "customer_id" => $customerId

        ]

    );

}
catch(Throwable $e)
{

    $conn->rollback();

    logError(

        "UPDATE CUSTOMER ERROR : ".$e->getMessage()

    );

    serverError();

}