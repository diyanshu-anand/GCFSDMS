<!-- 

Responsibilities:

1. JWT Authentication
2. Company Isolation
3. Admin/Manager authorization
4. Validate request
5. Verify product exists
6. Check duplicate SKU (excluding current product)
7. Update product master data only
8. Log activity
9. Standard response 

Request body:
{
    "product_id": 5,
    "product_name": "Engine Oil 1L",
    "sku": "EO1001",
    "category": "Lubricants",
    "price": 550.00,
    "unit": 1,
    "minimum_stock": 20
}




-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Product API
 * ------------------------------------------------------------
 * Updates product master information.
 * Current stock cannot be updated from here.
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
    forbidden("You are not authorized to update products.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("PUT");


$productId    = intval(getParam($input, "product_id"));
$productName  = sanitize(getParam($input, "product_name"));
$sku          = strtoupper(trim(sanitize(getParam($input, "sku"))));
$category     = sanitize(getParam($input, "category"));
$price        = getParam($input, "price");
$unit         = intval(getParam($input, "unit"));
$minimumStock = intval(getParam($input, "minimum_stock"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($productId <= 0)
{
    validationError("Valid product ID is required.");
}

if (!required($productName))
{
    validationError("Product name is required.");
}

if (!validateLength($productName, 2, 150))
{
    validationError("Invalid product name.");
}

if (!required($sku))
{
    validationError("SKU is required.");
}

if (!is_numeric($price) || $price < 0)
{
    validationError("Invalid product price.");
}

if ($unit < 0)
{
    validationError("Invalid unit.");
}

if ($minimumStock < 0)
{
    validationError("Invalid minimum stock.");
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
        SELECT product_id
        FROM products
        WHERE product_id = ?
        AND company_id = ?
        AND status = ?
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

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Duplicate SKU Check
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT product_id
        FROM products
        WHERE company_id = ?
        AND sku = ?
        AND product_id <> ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "isi",
        $companyId,
        $sku,
        $productId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0)
    {
        $stmt->close();
        $conn->rollback();

        conflict("SKU already exists.");
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Update Product
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE products
        SET
            product_name = ?,
            sku = ?,
            category = ?,
            price = ?,
            unit = ?,
            minimum_stock = ?
        WHERE
            product_id = ?
        AND
            company_id = ?
    ");

    $stmt->bind_param(
        "sssdiiii",
        $productName,
        $sku,
        $category,
        $price,
        $unit,
        $minimumStock,
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
        "PRODUCT_UPDATED",
        [
            "product_id" => $productId,
            "sku" => $sku
        ]
    );


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(
        "Product updated successfully.",
        [
            "product_id" => $productId
        ]
    );

}
catch (Throwable $e)
{

    $conn->rollback();

    logError(
        "UPDATE PRODUCT ERROR : " . $e->getMessage()
    );

    serverError();

}