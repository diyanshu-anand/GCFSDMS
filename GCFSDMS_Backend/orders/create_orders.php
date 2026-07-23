<!-- 

Responsibilities:

JWT Authentication

Company Isolation

Admin/Manager Authorization

Validate Customer

Validate Products

Validate Quantities

Fetch Prices from DB

Calculate Total

Create Order

Create Order Items

Update Product Stock

Insert Inventory Logs

Activity Log

Commit Transaction

Standard Response 


Request:
{
    "customer_id": 15,
    "payment_mode": "Cash",
    "remarks": "Deliver before 5 PM",
    "items": [
        {
            "product_id": 1,
            "quantity": 5
        },
        {
            "product_id": 3,
            "quantity": 2
        }
    ]
}

backend flow :
Authenticate
      │
      ▼
Validate Customer
      │
      ▼
Loop Through Items
      │
      ├── Verify Product
      ├── Verify Stock
      ├── Calculate Subtotal
      ├── Build Grand Total
      ▼
Insert Order
      │
      ▼
Insert Order Items
      │
      ▼
For Each Item
      ├── Update products.current_stock
      └── Insert inventory movement (OUT)
      ▼
Log Activity
      ▼
Commit
      ▼
Return Order ID



-->


<?php

/**
 * ------------------------------------------------------------
 * FSDMS Create Order API
 * ------------------------------------------------------------
 * Creates a new customer order.
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
    forbidden("You are not authorized to create orders.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$customerId  = intval(getParam($input, "customer_id"));
$paymentMode = trim(getParam($input, "payment_mode"));
$remarks     = sanitize(getParam($input, "remarks"));
$items       = $input["items"] ?? [];


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($customerId <= 0) {
    validationError("Valid customer is required.");
}

if (empty($items)) {
    validationError("At least one product is required.");
}

$allowedPayments = [
    "Cash",
    "UPI",
    "Card",
    "Net Banking",
    "Cheque"
];

if (
    !empty($paymentMode) &&
    !in_array($paymentMode, $allowedPayments)
) {
    validationError("Invalid payment mode.");
}


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Verify Customer
    |--------------------------------------------------------------------------
    */

    $status = STATUS_ACTIVE;

    $stmt = $conn->prepare("
        SELECT customer_id
        FROM customers
        WHERE
            customer_id = ?
        AND
            company_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $customerId,
        $companyId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        $conn->rollback();

        notFound("Customer not found.");
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Process Products
    |--------------------------------------------------------------------------
    */

    $grandTotal = 0;

    $orderItems = [];

    foreach ($items as $item) {

        $productId = intval($item["product_id"] ?? 0);
        $quantity  = intval($item["quantity"] ?? 0);

        if ($productId <= 0) {

            $conn->rollback();

            validationError("Invalid product.");
        }

        if ($quantity <= 0) {

            $conn->rollback();

            validationError("Invalid quantity.");
        }

        /*
        ------------------------------------------------------------
        Verify Product
        ------------------------------------------------------------
        */

        $stmt = $conn->prepare("

            SELECT

                product_id,
                product_name,
                sku,
                price,
                unit,
                current_stock

            FROM products

            WHERE

                product_id = ?

            AND

                company_id = ?

            AND

                status = ?

            LIMIT 1

        ");

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

            validationError(
                "Product not found."
            );
        }

        $product = $result->fetch_assoc();

        $stmt->close();

        /*
        ------------------------------------------------------------
        Stock Check
        ------------------------------------------------------------
        */

        if (
            $product["current_stock"] < $quantity
        ) {

            $conn->rollback();

            validationError(

                $product["product_name"] .
                " has insufficient stock."

            );

        }

        /*
        ------------------------------------------------------------
        Calculate
        ------------------------------------------------------------
        */

        $price = (float)$product["price"];

        $subtotal = $price * $quantity;

        $grandTotal += $subtotal;

        /*
        ------------------------------------------------------------
        Prepare Item
        ------------------------------------------------------------
        */

        $orderItems[] = [

            "product_id"   => $productId,

            "product_name" => $product["product_name"],

            "price"        => $price,

            "unit"         => $product["unit"],

            "quantity"     => $quantity,

            "subtotal"     => $subtotal,

            "current_stock"=> $product["current_stock"]

        ];

    }

        /*
    |--------------------------------------------------------------------------
    | Create Order
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO orders
        (
            company_id,
            customer_id,
            payment_mode,
            total_amount
        )
        VALUES
        (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisd",
        $companyId,
        $customerId,
        $paymentMode,
        $grandTotal
    );

    $stmt->execute();

    $orderId = $conn->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert Order Items + Update Stock + Inventory Logs
    |--------------------------------------------------------------------------
    */

    foreach ($orderItems as $item) {

        /*
        ------------------------------------------------------------
        Insert Order Item
        ------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO order_items
            (
                order_id,
                product_id,
                product_name,
                quantity,
                price,
                unit,
                total_amount
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iisidsd",
            $orderId,
            $item["product_id"],
            $item["product_name"],
            $item["quantity"],
            $item["price"],
            $item["unit"],
            $item["subtotal"]
        );

        $stmt->execute();

        $stmt->close();


        /*
        ------------------------------------------------------------
        Update Product Stock
        ------------------------------------------------------------
        */

        $newStock = $item["current_stock"] - $item["quantity"];

        $stmt = $conn->prepare("
            UPDATE products
            SET current_stock = ?
            WHERE
                product_id = ?
            AND
                company_id = ?
        ");

        $stmt->bind_param(
            "iii",
            $newStock,
            $item["product_id"],
            $companyId
        );

        $stmt->execute();

        $stmt->close();


        /*
        ------------------------------------------------------------
        Inventory Log
        ------------------------------------------------------------
        */

        $inventoryRemarks =
            "Stock issued against Order #".$orderId;

        $movement = "OUT";

        $referenceType = "ORDER";

        $referenceId = $orderId;

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
            (?, ?, NULL, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iissiis",
            $companyId,
            $item["product_id"],
            $movement,
            $referenceType,
            $referenceId,
            $item["quantity"],
            $newStock,
            $inventoryRemarks
        );

        $stmt->execute();

        $stmt->close();

    }


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(

        $conn,

        $userId,

        "ORDER_CREATED",

        [

            "order_id" => $orderId,

            "customer_id" => $customerId,

            "items" => count($orderItems),

            "total_amount" => $grandTotal

        ]

    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    returnSuccess(

        "Order created successfully.",

        [

            "order_id" => $orderId,

            "customer_id" => $customerId,

            "total_amount" => $grandTotal,

            "total_items" => count($orderItems)

        ],

        HTTP_CREATED

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "CREATE ORDER ERROR : " . $e->getMessage()
    );

    serverError();

}