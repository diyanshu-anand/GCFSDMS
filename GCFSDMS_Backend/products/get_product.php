<!-- 
Responsibilities:

1. JWT Authentication
2. Company Isolation
3. Validate product_id
4. Return only ACTIVE product
5. Prepared Statements
6. Standard Response 


Permissions:
Role	Get Product
Admin	Yes
Manager	Yes
Delivery Agent	Yes

Request Body:
{
    "product_id": 5
}

Response :
{
    "success": true,
    "message": "Product fetched successfully.",
    "data": {
        "product_id": 5,
        "company_id": 1,
        "product_name": "Engine Oil 1L",
        "sku": "EO1001",
        "category": "Lubricants",
        "price": "550.00",
        "unit": 1,
        "current_stock": 120,
        "minimum_stock": 20,
        "status": "ACTIVE",
        "created_at": "2026-07-21 10:30:00"
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Product API
 * ------------------------------------------------------------
 * Returns a single active product belonging
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

    /*
    |--------------------------------------------------------------------------
    | Fetch Product
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("

        SELECT

            product_id,
            company_id,
            product_name,
            sku,
            category,
            price,
            unit,
            current_stock,
            minimum_stock,
            status,
            created_at

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

        notFound("Product not found.");
    }

    $product = $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Product fetched successfully.",

        $product

    );

}
catch (Throwable $e)
{
    die($e->getMessage());
}