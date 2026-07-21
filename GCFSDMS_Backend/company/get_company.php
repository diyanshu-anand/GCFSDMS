<!-- JWT
 |
 v
Extract company_id
 |
 v
Fetch only that company
 |
 v
Return data 

Response Example
{
    "success":true,
    "message":"Company fetched successfully.",
    "data":{
        "company_id":1,
        "company_name":"ABC Foods Pvt Ltd",
        "ownername":"Rahul Sharma",
        "email":"rahul@abc.com",
        "phone":"9876543210",
        "address":"Delhi",
        "logo":null,
        "status":"ACTIVE",
        "created_at":"2026-07-21 12:00:00"
    }
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Company API
 * ------------------------------------------------------------
 * Returns authenticated user's company details.
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



try
{


/*
|--------------------------------------------------------------------------
| Fetch Company
|--------------------------------------------------------------------------
*/


$query = "

SELECT

company_id,
company_name,
ownername,
email,
phone,
address,
logo,
status,
created_at

FROM company

WHERE company_id = ?

LIMIT 1

";



$stmt = $conn->prepare($query);



$stmt->bind_param(

"i",

$companyId

);



$stmt->execute();



$result = $stmt->get_result();



if($result->num_rows === 0)
{

    $stmt->close();

    notFound(
        "Company not found."
    );

}



$company = $result->fetch_assoc();



$stmt->close();



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"Company fetched successfully.",

$company

);



}


catch(Throwable $e)
{


logError(

"GET COMPANY ERROR : ".$e->getMessage()

);


serverError();

}