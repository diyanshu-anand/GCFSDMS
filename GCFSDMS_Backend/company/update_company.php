<!-- JWT
 |
 v
Extract company_id
 |
 v
Check user role
 |
 v
Validate input
 |
 v
Update ONLY that company
 |
 v
Log activity
 |
 v
Response 

Permission Rule
Role	Update Company
Admin	 Allowed
Manager	 Not allowed
Delivery Agent	 Not allowed



-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Company API
 * ------------------------------------------------------------
 * Updates authenticated company's information.
 * Only Admin can update company details.
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

$userId = $authUser["user_id"];

$role = $authUser["role"];



/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/


if($role !== ROLE_ADMIN)
{
    forbidden(
        "Only admin can update company details."
    );
}



/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/


$input = getJsonInput("PUT");



/*
|--------------------------------------------------------------------------
| Input Data
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


$logo = sanitize(
    getParam($input,"logo")
);



/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/


if(
    !required($companyName) ||
    !required($email) ||
    !required($phone)
)
{
    validationError(MSG_REQUIRED_FIELDS);
}



if(!validateEmail($email))
{
    validationError(
        "Invalid email."
    );
}



if(!validatePhone($phone))
{
    validationError(
        "Invalid phone number."
    );
}




try
{


$conn->begin_transaction();



/*
|--------------------------------------------------------------------------
| Check Email / Phone Conflict
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

SELECT company_id

FROM company

WHERE

(email=? OR phone=?)

AND company_id != ?

LIMIT 1

");



$stmt->bind_param(

"ssi",

$email,

$phone,

$companyId

);



$stmt->execute();



$result=$stmt->get_result();



if($result->num_rows > 0)
{

    $stmt->close();

    $conn->rollback();


    conflict(
        "Email or phone already belongs to another company."
    );

}



$stmt->close();




/*
|--------------------------------------------------------------------------
| Update Company
|--------------------------------------------------------------------------
*/


$stmt=$conn->prepare("

UPDATE company

SET

company_name=?,

ownername=?,

email=?,

phone=?,

address=?,

logo=?

WHERE company_id=?

");



$stmt->bind_param(

"ssssssi",

$companyName,

$ownerName,

$email,

$phone,

$address,

$logo,

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

$userId,

"COMPANY_UPDATED",

[

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

"Company updated successfully.",

[

"company_id"=>$companyId

]

);



}


catch(Throwable $e)
{


$conn->rollback();



logError(

"UPDATE COMPANY ERROR : ".$e->getMessage()

);



serverError();

}