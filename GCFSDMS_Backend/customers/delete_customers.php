<!-- 
Requirements/ Responsibilities to complete: 

    1. JWT Authentication
    2. Company Isolation
    3. Validate customer_id
    4. Verify customer belongs to same company
    5. Soft delete customer
    6. Activity log
    7. Standard response 

Permissions:
Role	Delete Customer
Admin	Yes
Manager	Yes
Delivery Agent	No

Response Body:

{
    "customer_id":12
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Delete Customer API
 * ------------------------------------------------------------
 * Soft deletes a customer by setting status
 * to INACTIVE.
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
$userRole  = $authUser["role"];


/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

if (
    $userRole !== ROLE_ADMIN &&
    $userRole !== ROLE_MANAGER
)
{
    forbidden(
        "You are not authorized to deactivate customers."
    );
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("DELETE");

$customerId = intval(
    getParam($input, "customer_id")
);


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($customerId <= 0)
{
    validationError(
        "Valid customer ID is required."
    );
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

        WHERE
            customer_id = ?
        AND
            company_id = ?
        AND
            status = ?

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

        notFound(
            "Customer not found."
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Soft Delete
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        UPDATE customers

        SET status = ?

        WHERE
            customer_id = ?
        AND
            company_id = ?

    ");

    $inactive = STATUS_INACTIVE;

    $stmt->bind_param(

        "sii",

        $inactive,

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

        "CUSTOMER_DEACTIVATED",

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

        "Customer deactivated successfully.",

        [

            "customer_id" => $customerId

        ]

    );

}
catch(Throwable $e)
{

    $conn->rollback();

    logError(

        "DELETE CUSTOMER ERROR : " . $e->getMessage()

    );

    serverError();

}