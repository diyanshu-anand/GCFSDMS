<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Company API
 * ------------------------------------------------------------
 * Purpose:
 * Creates a new company tenant
 * and its first Admin user.
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../bootstrap.php";


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");


/*
|--------------------------------------------------------------------------
| Company Data
|--------------------------------------------------------------------------
*/

$companyName = sanitize(
    getParam($input,"company_name")
);

$ownerName = sanitize(
    getParam($input,"ownername")
);

$email = sanitize(
    getParam($input,"email")
);

$phone = sanitize(
    getParam($input,"phone")
);

$address = sanitize(
    getParam($input,"address")
);


/*
|--------------------------------------------------------------------------
| Admin Data
|--------------------------------------------------------------------------
*/

$adminName = sanitize(
    getParam($input,"admin_name")
);

$adminPhone = sanitize(
    getParam($input,"admin_phone")
);

$adminPassword =
    getParam($input,"admin_password");


/*
|--------------------------------------------------------------------------
| Payment Reference
|--------------------------------------------------------------------------
*/

$paymentId = sanitize(
    getParam($input,"payment_id")
);



/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/


if (
    !required($companyName) ||
    !required($email) ||
    !required($phone) ||
    !required($adminName) ||
    !required($adminPhone) ||
    !required($adminPassword) ||
    !required($paymentId)
){

    validationError(MSG_REQUIRED_FIELDS);

}



if(!validateEmail($email))
{
    validationError("Invalid email.");
}



if(!validatePhone($phone))
{
    validationError("Invalid company phone.");
}



if(!validatePhone($adminPhone))
{
    validationError("Invalid admin phone.");
}



if(!validatePassword($adminPassword))
{
    validationError(
        "Password must contain minimum 8 characters."
    );
}



try
{

$conn->begin_transaction();



/*
|--------------------------------------------------------------------------
| Check Existing Company
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

SELECT company_id

FROM company

WHERE email=?

OR phone=?

LIMIT 1

");


$stmt->bind_param(
    "ss",
    $email,
    $phone
);


$stmt->execute();


$result=$stmt->get_result();


if($result->num_rows > 0)
{

    $stmt->close();

    $conn->rollback();


    conflict(
        "Company already exists."
    );

}


$stmt->close();



/*
|--------------------------------------------------------------------------
| Create Company
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

INSERT INTO company

(
company_name,
ownername,
email,
phone,
address,
status
)

VALUES

(?,?,?,?,?,?)

");



$status=STATUS_ACTIVE;



$stmt->bind_param(

"ssssss",

$companyName,

$ownerName,

$email,

$phone,

$address,

$status

);



$stmt->execute();



$companyId=$conn->insert_id;



$stmt->close();



/*
|--------------------------------------------------------------------------
| Check Admin User
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
    $adminPhone
);


$stmt->execute();


$result=$stmt->get_result();



if($result->num_rows > 0)
{

    $conn->rollback();

    conflict(
        "Admin user already exists."
    );

}



$stmt->close();



/*
|--------------------------------------------------------------------------
| Create Admin User
|--------------------------------------------------------------------------
*/


$passwordHash=password_hash(

    $adminPassword,

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



$role=ROLE_ADMIN;



$stmt->bind_param(

"isssss",

$companyId,

$adminName,

$adminPhone,

$passwordHash,

$role,

$status

);



$stmt->execute();



$userId=$conn->insert_id;



$stmt->close();



/*
|--------------------------------------------------------------------------
| Log Activity
|--------------------------------------------------------------------------
*/


logActivity(

$conn,

$userId,

"COMPANY_CREATED",

[

"company_id"=>$companyId,

"payment_id"=>$paymentId

]

);



$conn->commit();



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


returnSuccess(

"Company created successfully.",

[

"company_id"=>$companyId,

"admin_user_id"=>$userId

],

HTTP_CREATED

);



}


catch(Throwable $e)
{

$conn->rollback();


logError(

"CREATE COMPANY ERROR : ".$e->getMessage()

);


serverError();

}