<!-- 
    Response Body : 
    {
        "success": true,
        "message": "Customers fetched successfully.",
        "data": {
            "count": 2,
            "customers": [
                {
                    "customer_id": 12,
                    "name": "Rahul Sharma",
                    "phone": "9876543210",
                    "address": "Delhi",
                    "latitude": "28.613939",
                    "longitude": "77.209021",
                    "status": "ACTIVE",
                    "created_at": "2026-07-21 18:20:10"
                },
                {
                    "customer_id": 11,
                    "name": "Amit Kumar",
                    "phone": "9999999999",
                    "address": "Noida",
                    "latitude": null,
                    "longitude": null,
                    "status": "ACTIVE",
                    "created_at": "2026-07-20 14:32:08"
                }
            ]
        }
    } 
        
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Customers API
 * ------------------------------------------------------------
 * Returns all active customers belonging
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
    | Fetch Customers
    |--------------------------------------------------------------------------
    */

    if($search !== "")
    {

        $query = "

        SELECT

            customer_id,
            name,
            phone,
            address,
            latitude,
            longitude,
            status,
            created_at

        FROM customers

        WHERE company_id = ?

        AND status = ?

        AND
        (
            name LIKE ?
            OR phone LIKE ?
        )

        ORDER BY customer_id DESC

        ";

        $stmt = $conn->prepare($query);

        $status = STATUS_ACTIVE;

        $keyword = "%".$search."%";

        $stmt->bind_param(

            "isss",

            $companyId,

            $status,

            $keyword,

            $keyword

        );

    }
    else
    {

        $query = "

        SELECT

            customer_id,
            name,
            phone,
            address,
            latitude,
            longitude,
            status,
            created_at

        FROM customers

        WHERE company_id = ?

        AND status = ?

        ORDER BY customer_id DESC

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

    $customers = [];

    while($row = $result->fetch_assoc())
    {
        $customers[] = $row;
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Customers fetched successfully.",

        [

            "count" => count($customers),

            "customers" => $customers

        ]

    );

}
catch(Throwable $e)
{

    logError(

        "GET CUSTOMERS ERROR : ".$e->getMessage()

    );

    serverError();

}