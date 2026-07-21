<!--

Responsibilities :

JWT Authentication
Company Isolation
Return only ACTIVE products
Optional search (product name, SKU, category)
Ordered by newest first
Prepared statements
Standard response

Role	Get Products
Admin	Yes
Manager	Yes
Delivery Agent	Yes

Requests:
    Without search:

    {}

    With search:

    {
        "search":"oil"
    }

Response:
{
    "success": true,
    "message": "Products fetched successfully.",
    "data": {
        "count": 2,
        "products": [
            {
                "product_id": 5,
                "product_name": "Engine Oil 1L",
                "sku": "EO1001",
                "category": "Lubricants",
                "price": 550.00,
                "unit": 1,
                "current_stock": 120,
                "minimum_stock": 20,
                "status": "ACTIVE",
                "created_at": "2026-07-21 10:30:00"
            },
            {
                "product_id": 4,
                "product_name": "Air Filter",
                "sku": "AF202",
                "category": "Filters",
                "price": 180.00,
                "unit": 1,
                "current_stock": 35,
                "minimum_stock": 10,
                "status": "ACTIVE",
                "created_at": "2026-07-20 16:45:10"
            }
        ]
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Products API
 * ------------------------------------------------------------
 * Returns all active products belonging
 * to authenticated company.
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

$search = trim(sanitize(getParam($input, "search")));


try
{

    /*
    |--------------------------------------------------------------------------
    | Fetch Products
    |--------------------------------------------------------------------------
    */

    if ($search !== "")
    {

        $query = "

        SELECT

            product_id,
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
            company_id = ?
        AND
            status = ?
        AND
        (
            product_name LIKE ?
            OR sku LIKE ?
            OR category LIKE ?
        )

        ORDER BY product_id DESC

        ";

        $stmt = $conn->prepare($query);

        $status = STATUS_ACTIVE;

        $keyword = "%" . $search . "%";

        $stmt->bind_param(

            "issss",

            $companyId,

            $status,

            $keyword,

            $keyword,

            $keyword

        );

    }
    else
    {

        $query = "

        SELECT

            product_id,
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
            company_id = ?
        AND
            status = ?

        ORDER BY product_id DESC

        ";

        $stmt = $conn->prepare($query);

        $status = STATUS_ACTIVE;

        $stmt->bind_param(

            "is",

            $companyId,

            $status

        );

    }


    $stmt->execute();

    $result = $stmt->get_result();

    $products = [];

    while ($row = $result->fetch_assoc())
    {
        $products[] = $row;
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Products fetched successfully.",

        [

            "count" => count($products),

            "products" => $products

        ]

    );

}
catch(Throwable $e)
{

    logError(

        "GET PRODUCTS ERROR : " . $e->getMessage()

    );

    serverError();

}