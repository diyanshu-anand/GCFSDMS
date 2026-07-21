<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create User API
 * ------------------------------------------------------------
 * Creates Manager and Delivery Agent users.
 * ------------------------------------------------------------
 */


require_once __DIR__ . "/../bootstrap.php";



/*
|--------------------------------------------------------------------------
| Authenticate Request
|--------------------------------------------------------------------------
*/

$authUser = authenticate();


$companyId = $authUser["company_id"];

$creatorRole = $authUser["role"];

$creatorId = $authUser["user_id"];



/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");



/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/


$name = sanitize(
    getParam($input,"name")
);


$phone = sanitize(
    getParam($input,"phone")
);


$password = getParam(
    $input,
    "password"
);


$role = sanitize(
    getParam($input,"role")
);



/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/


if(
    !required($name) ||
    !required($phone) ||
    !required($password) ||
    !required($role)
)
{
    validationError(MSG_REQUIRED_FIELDS);
}



if(!validatePhone($phone))
{
    validationError("Invalid phone number.");
}



if(!validatePassword($password))
{
    validationError(
        "Password must contain minimum 8 characters."
    );
}



if(
    !validateEnum(
        $role,
        [
            ROLE_MANAGER,
            ROLE_DELIVERY_AGENT
        ]
    )
)
{
    validationError(
        "Invalid user role."
    );
}



/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/


if($creatorRole === ROLE_DELIVERY_AGENT)
{
    forbidden(
        "Delivery agent cannot create users."
    );
}



if(
    $creatorRole === ROLE_MANAGER
    &&
    $role !== ROLE_DELIVERY_AGENT
)
{
    forbidden(
        "Manager can only create delivery agents."
    );
}




try
{


$conn->begin_transaction();



/*
|--------------------------------------------------------------------------
| Check Duplicate Phone
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

SELECT user_id

FROM users

WHERE phone=?

LIMIT 1

");



$stmt->bind_param(
    "s",
    $phone
);



$stmt->execute();



$result=$stmt->get_result();



if($result->num_rows > 0)
{

    $stmt->close();

    $conn->rollback();


    conflict(
        "User already exists."
    );

}



$stmt->close();



/*
|--------------------------------------------------------------------------
| Create User
|--------------------------------------------------------------------------
*/


$passwordHash=password_hash(
    $password,
    PASSWORD_DEFAULT
);



$stmt=$conn->prepare("

INSERT INTO users

(
company_id,
name,
phone,
password,
role,
status
)

VALUES

(?,?,?,?,?,?)

");



$status=STATUS_ACTIVE;



$stmt->bind_param(

"isssss",

$companyId,

$name,

$phone,

$passwordHash,

$role,

$status

);



$stmt->execute();



$userId=$conn->insert_id;



$stmt->close();




/*
|--------------------------------------------------------------------------
| Activity Log
|--------------------------------------------------------------------------
*/


logActivity(

$conn,

$creatorId,

"USER_CREATED",

[

"created_user_id"=>$userId,

"role"=>$role,

"company_id"=>$companyId

]

);



$conn->commit();




/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"User created successfully.",

[

"user_id"=>$userId,

"name"=>$name,

"role"=>$role

],

HTTP_CREATED

);



}

catch(Throwable $e)
{


$conn->rollback();



logError(

"CREATE USER ERROR : ".$e->getMessage()

);



serverError();

}