<!-- 
JWT Authentication
        |
        v
Extract company_id + role
        |
        v
Receive user_id
        |
        v
Check user belongs to company
        |
        v
Check permission
        |
        v
Deactivate user
        |
        v
Log activity
        |
        v
Response

Some precautinary measures in the system design overall
Role	Deactivate User
Admin	Yes
Manager	Delivery Agent only
Delivery Agent	No

Additional protection:

Admin cannot deactivate himself

because that would lock the company out.


Response example:
{
    "success":true,
    "message":"User deactivated successfully.",
    "data":{
        "user_id":5
    }
}
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Delete User API
 * ------------------------------------------------------------
 * Soft deletes user by changing status.
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

$creatorId = $authUser["user_id"];

$creatorRole = $authUser["role"];



/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/


$input = getJsonInput("DELETE");



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
        "User ID required."
    );
}




try
{


$conn->begin_transaction();



/*
|--------------------------------------------------------------------------
| Fetch Target User
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

SELECT

user_id,

role,

status

FROM users

WHERE

user_id=?

AND

company_id=?

LIMIT 1

");



$stmt->bind_param(

"ii",

$userId,

$companyId

);



$stmt->execute();



$result=$stmt->get_result();



if($result->num_rows===0)
{

    $stmt->close();

    $conn->rollback();


    notFound(
        "User not found."
    );

}



$targetUser=$result->fetch_assoc();



$stmt->close();




/*
|--------------------------------------------------------------------------
| Self Delete Protection
|--------------------------------------------------------------------------
*/


if($userId == $creatorId)
{

    $conn->rollback();


    forbidden(
        "You cannot deactivate yourself."
    );

}




/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/


if($creatorRole === ROLE_DELIVERY_AGENT)
{

    $conn->rollback();


    forbidden(
        "No permission."
    );

}



if(

$creatorRole === ROLE_MANAGER

&&

$targetUser["role"] !== ROLE_DELIVERY_AGENT

)
{

    $conn->rollback();


    forbidden(
        "Manager can only deactivate delivery agents."
    );

}




/*
|--------------------------------------------------------------------------
| Soft Delete
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

UPDATE users

SET status=?

WHERE

user_id=?

AND

company_id=?

");



$status=STATUS_INACTIVE;



$stmt->bind_param(

"sii",

$status,

$userId,

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

$creatorId,

"USER_DEACTIVATED",

[

"deactivated_user_id"=>$userId

]

);



$conn->commit();




/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"User deactivated successfully.",

[

"user_id"=>$userId

]

);



}


catch(Throwable $e)
{


$conn->rollback();



logError(

"DELETE USER ERROR : ".$e->getMessage()

);



serverError();

}