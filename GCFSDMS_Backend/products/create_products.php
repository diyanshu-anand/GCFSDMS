<!--

Responsibilities:

1. JWT Authentication
2. Company Isolation
3. Validate Input
4. Check Duplicate SKU (within company)
5. Create Product
6. Initial Stock = 0
7. Activity Log
8. Standard Response

Permissions :
Role	Create Product
Admin	Yes
Manager	Yes
Delivery Agent	No


Request Body :
{
    "product_name":"Amul Milk 500ml",
    "sku":"AM500",
    "category":"Dairy",
    "price":32.50,
    "unit":1,
    "minimum_stock":20
}

Response Body :
{
    "success": true,
    "message": "Product created successfully.",
    "data": {
        "product_id": 18
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Product API
 * ------------------------------------------------------------
 * Creates a new product for authenticated company.
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
        "You are not authorized to create products."
    );
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");


$productName = sanitize(getParam($input,"product_name"));
$sku          = strtoupper(trim(sanitize(getParam($input,"sku"))));
$category     = sanitize(getParam($input,"category"));
$price        = getParam($input,"price");
$unit         = intval(getParam($input,"unit"));
$minimumStock = intval(getParam($input,"minimum_stock"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if(!required($productName))
{
    validationError("Product name is required.");
}

if(!validateLength($productName,2,150))
{
    validationError("Invalid product name.");
}

if(!required($sku))
{
    validationError("SKU is required.");
}

if(!is_numeric($price) || $price < 0)
{
    validationError("Invalid product price.");
}

if($unit < 0)
{
    validationError("Invalid unit.");
}

if($minimumStock < 0)
{
    validationError("Invalid minimum stock.");
}



try
{

$conn->begin_transaction();



/*
|--------------------------------------------------------------------------
| Duplicate SKU Check
|--------------------------------------------------------------------------
*/

$stmt=$conn->prepare("

SELECT product_id

FROM products

WHERE

company_id=?

AND

sku=?

LIMIT 1

");

$stmt->bind_param(

"is",

$companyId,

$sku

);

$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows>0)
{
    $stmt->close();

    $conn->rollback();

    conflict("SKU already exists.");
}

$stmt->close();



/*
|--------------------------------------------------------------------------
| Insert Product
|--------------------------------------------------------------------------
*/

$status = STATUS_ACTIVE;

$currentStock = 0;

$stmt=$conn->prepare("

INSERT INTO products
(

company_id,

product_name,

sku,

category,

price,

unit,

current_stock,

minimum_stock,

status

)

VALUES

(

?,

?,

?,

?,

?,

?,

?,

?,

?

)

");

$stmt->bind_param(

"isssdiiis",

$companyId,

$productName,

$sku,

$category,

$price,

$unit,

$currentStock,

$minimumStock,

$status

);

$stmt->execute();

$productId=$conn->insert_id;

$stmt->close();



/*
|--------------------------------------------------------------------------
| Activity Log
|--------------------------------------------------------------------------
*/

logActivity(

$conn,

$userId,

"PRODUCT_CREATED",

[

"product_id"=>$productId,

"sku"=>$sku

]

);



$conn->commit();



returnSuccess(

"Product created successfully.",

[

"product_id"=>$productId

],

HTTP_CREATED

);

}
catch(Throwable $e)
{

$conn->rollback();

logError(

"CREATE PRODUCT ERROR : ".$e->getMessage()

);

serverError();

}