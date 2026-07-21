<!-- 
 1. get_user.php → detailed profile of one user
 2. Never trust company/user ownership from frontend.
 3. The frontend will send only:

        {
            "user_id": 5
        }
 4. Verification flow is:
        Requested user_id
            |
            v
    Does it belong to JWT company_id?
            |
            v
    Yes → Return data
    No → Reject 
 5. Flow:
    JWT Authentication
        |
        v
    Extract company_id + requester role
            |
            v
    Receive user_id
            |
            v
    Validate user_id
            |
            v
    Fetch user with company check
            |
            v
    Return user details

Permission Rule
Role	View User Detail
Admin	Yes
Manager	Yes
Delivery Agent No	

Request body:
{
    "user_id":2
}

Response body:
{
    "success":true,
    "message":"User fetched successfully.",
    "data":{
        "user_id":2,
        "company_id":1,
        "name":"Amit Kumar",
        "phone":"9876543211",
        "role":"Delivery_agent",
        "device_id":"android-456",
        "last_login":"2026-07-21 10:30:00",
        "status":"ACTIVE",
        "created_at":"2026-07-21 09:30:00"
    }
}


-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Single User API
 * ------------------------------------------------------------
 * Fetch detailed user information.
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

$userRole = $authUser["role"];



/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/


if($userRole === ROLE_DELIVERY_AGENT)
{
    forbidden(
        "Delivery agent cannot view users."
    );
}



/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/


$input = getJsonInput("POST");



$userId = intval(
    getParam($input,"user_id")
);



/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/


if(!$userId)
{
    validationError(
        "User ID is required."
    );
}




try
{


/*
|--------------------------------------------------------------------------
| Fetch User
|--------------------------------------------------------------------------
*/


$query = "

SELECT

u.user_id,

u.company_id,

u.name,

u.phone,

u.role,

u.device_id,

u.last_login,

u.status,

u.created_at


FROM users u


WHERE

u.user_id = ?

AND

u.company_id = ?


LIMIT 1

";



$stmt=$conn->prepare($query);



$stmt->bind_param(

"ii",

$userId,

$companyId

);



$stmt->execute();



$result=$stmt->get_result();



if($result->num_rows === 0)
{

    $stmt->close();


    notFound(
        "User not found."
    );

}



$user=$result->fetch_assoc();



$stmt->close();



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"User fetched successfully.",

$user

);



}


catch(Throwable $e)
{


logError(

"GET USER ERROR : ".$e->getMessage()

);



serverError();

}