<!-- 
 
Responsibilities:
    1. Authenticate JWT
    2. Get company_id from JWT
    3. Validate customer_id
    4. Fetch customer belonging to the authenticated company
    5. Return customer details
    6. Do not return inactive customers 

Request body:

{
    "customer_id": 15
}

Response body:
{
    "success": true,
    "message": "Customer fetched successfully.",
    "data": {
        "customer_id": 15,
        "company_id": 1,
        "name": "Rahul Sharma",
        "phone": "9876543210",
        "address": "Sector 21, Noida",
        "latitude": "28.613939",
        "longitude": "77.209021",
        "status": "ACTIVE",
        "created_at": "2026-07-21 11:42:18"
    }
}
    
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Customer API
 * ------------------------------------------------------------
 * Returns a single active customer belonging
 * to the authenticated company.
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


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$customerId = intval(getParam($input, "customer_id"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($customerId <= 0) {
    validationError("Valid customer ID is required.");
}


try
{

    /*
    |--------------------------------------------------------------------------
    | Fetch Customer
    |--------------------------------------------------------------------------
    */

    $query = "

        SELECT

            customer_id,
            company_id,
            name,
            phone,
            address,
            latitude,
            longitude,
            status,
            created_at

        FROM customers

        WHERE
            customer_id = ?
        AND
            company_id = ?
        AND
            status = ?

        LIMIT 1

    ";

    $stmt = $conn->prepare($query);

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

        notFound("Customer not found.");
    }

    $customer = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Customer fetched successfully.",

        $customer

    );

}
catch(Throwable $e)
{

    logError(

        "GET CUSTOMER ERROR : ".$e->getMessage()

    );

    serverError();

}