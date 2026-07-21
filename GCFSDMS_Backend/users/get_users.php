<!-- The biggest rule remains:

Never accept company_id from frontend. Always take it from JWT.


JWT Authentication
        |
        v
Extract company_id + role
        |
        v
Check Permission
        |
        v
Fetch users of same company only
        |
        v
Return response


Role	View Users
Admin	 All users
Manager	Users of same company
Delivery Agent	Not allowed


{
    "success":true,
    "message":"Users fetched successfully.",
    "data":[
        {
            "user_id":1,
            "name":"Rahul Sharma",
            "phone":"9876543210",
            "role":"Admin",
            "device_id":"android-123",
            "last_login":"2026-07-21 10:20:30",
            "status":"ACTIVE",
            "created_at":"2026-07-21 09:00:00"
        },
        {
            "user_id":2,
            "name":"Amit Kumar",
            "phone":"9876543211",
            "role":"Delivery_agent",
            "device_id":"android-456",
            "last_login":null,
            "status":"ACTIVE",
            "created_at":"2026-07-21 09:30:00"
        }
    ]
}

-->


<?php

/**
 * ------------------------------------------------------------
 * FSDMS Get Users API
 * ------------------------------------------------------------
 * Fetch all users belonging to authenticated company.
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



try
{


/*
|--------------------------------------------------------------------------
| Fetch Users
|--------------------------------------------------------------------------
*/


$query = "

SELECT

user_id,

name,

phone,

role,

device_id,

last_login,

status,

created_at

FROM users

WHERE company_id = ?

ORDER BY user_id DESC

";



$stmt = $conn->prepare($query);



$stmt->bind_param(

"i",

$companyId

);



$stmt->execute();



$result = $stmt->get_result();



$users = [];



while($row = $result->fetch_assoc())
{

    $users[] = $row;

}



$stmt->close();



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"Users fetched successfully.",

$users

);



}


catch(Throwable $e)
{


logError(

"GET USERS ERROR : ".$e->getMessage()

);



serverError();

}