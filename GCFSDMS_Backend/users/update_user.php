<!-- Action	Admin	Manager	Delivery Agent
Update own profile	Yes	    Yes	     Later
Update Manager	Yes	        No	      No
Update Delivery Agent Yes	Yes	      No
Change Role	Yes	No	No
Change Status	Yes	No	No
Change Password	Own user / Admin	Own user	Own user

1. Admin can update anyone in his company.
2. Manager can update only Delivery Agents.
3. Delivery Agent cannot update other users.

Request Body :
{
    "user_id":5,
    "name":"Ramesh Kumar",
    "phone":"9876543215",
    "role":"Delivery_agent",
    "status":"ACTIVE",
    "password":"NewPassword123"
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update User API
 * ------------------------------------------------------------
 * Updates user information inside same company.
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


$input = getJsonInput("PUT");



/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/


$userId = intval(
    getParam($input,"user_id")
);


$name = sanitize(
    getParam($input,"name")
);


$phone = sanitize(
    getParam($input,"phone")
);


$role = sanitize(
    getParam($input,"role")
);


$status = sanitize(
    getParam($input,"status")
);


$password = getParam(
    $input,
    "password"
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


if(
    !required($name) ||
    !required($phone)
)
{
    validationError(MSG_REQUIRED_FIELDS);
}



if(!validatePhone($phone))
{
    validationError(
        "Invalid phone number."
    );
}



if(
    $password !== null &&
    $password !== ""
)
{
    if(!validatePassword($password))
    {
        validationError(
            "Invalid password format."
        );
    }
}



/*
|--------------------------------------------------------------------------
| Role Permission
|--------------------------------------------------------------------------
*/


if($creatorRole === ROLE_DELIVERY_AGENT)
{
    forbidden(
        "No permission."
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
role

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
| Manager Restriction
|--------------------------------------------------------------------------
*/


if(
    $creatorRole === ROLE_MANAGER
    &&
    $targetUser["role"] !== ROLE_DELIVERY_AGENT
)
{

    $conn->rollback();

    forbidden(
        "Manager can only update delivery agents."
    );

}




/*
|--------------------------------------------------------------------------
| Build Update
|--------------------------------------------------------------------------
*/


if(
    $password !== null &&
    $password !== ""
)
{


$passwordHash=password_hash(

$password,

PASSWORD_DEFAULT

);


$stmt=$conn->prepare("

UPDATE users

SET

name=?,

phone=?,

role=?,

status=?,

password=?

WHERE

user_id=?

AND

company_id=?

");


$stmt->bind_param(

"sssssii",

$name,

$phone,

$role,

$status,

$passwordHash,

$userId,

$companyId

);


}

else
{


$stmt=$conn->prepare("

UPDATE users

SET

name=?,

phone=?,

role=?,

status=?

WHERE

user_id=?

AND

company_id=?

");


$stmt->bind_param(

"ssssii",

$name,

$phone,

$role,

$status,

$userId,

$companyId

);


}



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

"USER_UPDATED",

[

"updated_user_id"=>$userId

]

);



$conn->commit();




/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"User updated successfully.",

[

"user_id"=>$userId

]

);



}


catch(Throwable $e)
{


$conn->rollback();


logError(

"UPDATE USER ERROR : ".$e->getMessage()

);


serverError();

}