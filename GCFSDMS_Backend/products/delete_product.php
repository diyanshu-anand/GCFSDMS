<!-- 
 Request: 
{
    "product_id": 5
} 
    
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Delete Product API
 * ------------------------------------------------------------
 * Soft deletes a product.
 * Product cannot be deleted if stock exists.
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
    forbidden("You are not authorized to deactivate products.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("DELETE");

$productId = intval(getParam($input, "product_id"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($productId <= 0)
{
    validationError("Valid product ID is required.");
}


try
{

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Verify Product
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            product_id,
            current_stock
        FROM products
        WHERE
            product_id = ?
        AND
            company_id = ?
        AND
            status = ?
        LIMIT 1
    ");

    $status = STATUS_ACTIVE;

    $stmt->bind_param(
        "iis",
        $productId,
        $companyId,
        $status
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0)
    {
        $stmt->close();
        $conn->rollback();

        notFound("Product not found.");
    }

    $product = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Business Rule
    |--------------------------------------------------------------------------
    */

    if ((int)$product["current_stock"] > 0)
    {
        $conn->rollback();

        validationError(
            "Product cannot be deactivated while stock is available."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Soft Delete
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE products
        SET status = ?
        WHERE
            product_id = ?
        AND
            company_id = ?
    ");

    $inactive = STATUS_INACTIVE;

    $stmt->bind_param(
        "sii",
        $inactive,
        $productId,
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
        "PRODUCT_DEACTIVATED",
        [
            "product_id" => $productId,
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
        "Product deactivated successfully.",
        [
            "product_id" => $productId
        ]
    );

}
catch (Throwable $e)
{

    $conn->rollback();

    logError(
        "DELETE PRODUCT ERROR : " . $e->getMessage()
    );

    serverError();

}