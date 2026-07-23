<!--

Responsibilities:

JWT Authentication
Admin/Manager Authorization
Company Isolation
Validate Input
Verify Product
Verify Delivery Boy (if provided)
Calculate New Stock
Prevent Negative Stock
Update products.current_stock
Insert Inventory Log
Activity Log
Transaction Handling
Standard Response

Permission Matrix
Role	IN	OUT	RETURN	ADJUSTMENT
Admin	Yes	 Yes Yes	      Yes
Manager	Yes	Yes	Yes	  Yes
Delivery Agent	No 	No	No	No

Request :
{
    "product_id": 10,
    "delivery_boy": 7,
    "movement": "OUT",
    "reference_type": "ORDER",
    "reference_id": 152,
    "quantity": 5,
    "remarks": "Issued for Order #152"
}

Buisness Logic ( needed over here to understand and write the code in order to think much better.)
1. Current Stock = 100

    Type = IN
    Quantity = 20

    New Stock = 120

2. Current Stock = 100

    Type = OUT
    Quantity = 10

    New Stock = 90

3. Current Stock = 100

    Type = RETURN
    Quantity = 5

    New Stock = 105

4. Current Stock = 100

Type = ADJUSTMENT

Quantity = 3

(Admin decides whether adjustment increases or decreases stock based on your business rule. For V1, we can treat ADJUSTMENT as stock addition or introduce an adjustment direction later.)

Ok, i figured out and mapped out the transactional statements like this :
| type       | movement |
| ---------- | -------- |
| PURCHASE   | IN       |
| ORDER      | OUT      |
| RETURN     | IN       |
| ADJUSTMENT | IN       |
| ADJUSTMENT | OUT      |


| Movement | Reference Type | Meaning                            |
| -------- | -------------- | ---------------------------------- |
| IN       | PURCHASE       | Stock received from supplier       |
| OUT      | ORDER          | Stock issued for an order          |
| IN       | RETURN         | Stock returned by a delivery agent |
| OUT      | ADJUSTMENT     | Stock reduced after audit/damage   |
| IN       | ADJUSTMENT     | Stock increased after audit        |
| IN       | INITIAL        | Opening stock                      |

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Inventory API
 * ------------------------------------------------------------
 * Creates a stock movement and updates product stock.
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
$userId    = $authUser["user_id"];
$userRole  = $authUser["role"];


/*
|--------------------------------------------------------------------------
| Permission
|--------------------------------------------------------------------------
*/

if (
    $userRole !== ROLE_ADMIN &&
    $userRole !== ROLE_MANAGER
) {
    forbidden("You are not authorized to manage inventory.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$productId     = intval(getParam($input, "product_id"));
$deliveryBoy   = getParam($input, "delivery_boy");
$movement      = strtoupper(trim(getParam($input, "movement")));
$referenceType = strtoupper(trim(getParam($input, "reference_type")));
$referenceId   = getParam($input, "reference_id");
$quantity      = intval(getParam($input, "quantity"));
$remarks       = sanitize(getParam($input, "remarks"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($productId <= 0) {
    validationError("Valid product ID is required.");
}

if (!in_array($movement, ["IN", "OUT"])) {
    validationError("Invalid inventory movement.");
}

if (
    !empty($referenceType) &&
    !in_array($referenceType, [
        "PURCHASE",
        "ORDER",
        "RETURN",
        "ADJUSTMENT",
        "INITIAL"
    ])
) {
    validationError("Invalid reference type.");
}

if ($quantity <= 0) {
    validationError("Quantity must be greater than zero.");
}


try {

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | Verify Product
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT current_stock
        FROM products
        WHERE product_id = ?
        AND company_id = ?
        AND status = ?
        LIMIT 1
    ");

    $status = STATUS_ACTIVE;

    $stmt->bind_param(
        "iis",
        $productId,
        $companyId,
        $status
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();

        notFound("Product not found.");
    }

    $product = $result->fetch_assoc();

    $stmt->close();

    $currentStock = (int)$product["current_stock"];


    /*
    |--------------------------------------------------------------------------
    | Verify Delivery Boy
    |--------------------------------------------------------------------------
    */

    if (!empty($deliveryBoy)) {

        $deliveryBoy = intval($deliveryBoy);

        $stmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
            AND company_id = ?
            AND status = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "iis",
            $deliveryBoy,
            $companyId,
            $status
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->rollback();

            validationError("Invalid delivery boy.");
        }

        $stmt->close();
    } else {
        $deliveryBoy = null;
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Stock
    |--------------------------------------------------------------------------
    */

    if ($movement === "IN") {
        $newStock = $currentStock + $quantity;
    } else {

        if ($currentStock < $quantity) {
            $conn->rollback();

            validationError("Insufficient stock available.");
        }

        $newStock = $currentStock - $quantity;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Product Stock
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE products
        SET current_stock = ?
        WHERE product_id = ?
        AND company_id = ?
    ");

    $stmt->bind_param(
        "iii",
        $newStock,
        $productId,
        $companyId
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert Inventory Log
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO inventory
        (
            company_id,
            product_id,
            delivery_boy,
            movement,
            reference_type,
            reference_id,
            quantity,
            stock_after_transaction,
            remarks
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiissiiis",
        $companyId,
        $productId,
        $deliveryBoy,
        $movement,
        $referenceType,
        $referenceId,
        $quantity,
        $newStock,
        $remarks
    );

    $stmt->execute();

    $logId = $conn->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(
        $conn,
        $userId,
        "INVENTORY_CREATED",
        [
            "log_id"     => $logId,
            "product_id" => $productId,
            "movement"   => $movement,
            "quantity"   => $quantity
        ]
    );


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(
        "Inventory transaction created successfully.",
        [
            "log_id" => $logId,
            "current_stock" => $newStock
        ],
        HTTP_CREATED
    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CREATE INVENTORY ERROR : " . $e->getMessage()
    );

    serverError();
}