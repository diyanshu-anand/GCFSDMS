<!-- 
Responsibilities:
JWT Authentication

Delivery Agent Authorization

Company Isolation

Validate Latitude

Validate Longitude

Validate Accuracy

Validate Speed

Validate Battery

UPSERT into live_location

Insert into location_history

Activity Log

Transaction

Standard Response


Request:
{
    "latitude": 26.2183,
    "longitude": 78.1828,
    "accuracy": 4.25,
    "speed": 18.50,
    "battery_percentage": 87
}

-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Update Live Location API
 * ------------------------------------------------------------
 * Updates current location of delivery agent and
 * stores history.
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

if ($userRole !== ROLE_DELIVERY_AGENT) {
    forbidden("Only delivery agents can update live location.");
}


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$input = getJsonInput("POST");

$latitude  = floatval(getParam($input, "latitude"));
$longitude = floatval(getParam($input, "longitude"));
$accuracy  = floatval(getParam($input, "accuracy"));
$speed     = floatval(getParam($input, "speed"));
$battery   = intval(getParam($input, "battery_percentage"));


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($latitude < -90 || $latitude > 90) {
    validationError("Invalid latitude.");
}

if ($longitude < -180 || $longitude > 180) {
    validationError("Invalid longitude.");
}

if ($accuracy < 0) {
    validationError("Invalid GPS accuracy.");
}

if ($speed < 0) {
    validationError("Invalid speed.");
}

if ($battery < 0 || $battery > 100) {
    validationError("Battery percentage must be between 0 and 100.");
}


try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Update Live Location
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO live_location
        (
            delivery_boy,
            latitude,
            longitude,
            accuracy,
            speed,
            battery_percentage
        )
        VALUES
        (?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            accuracy = VALUES(accuracy),
            speed = VALUES(speed),
            battery_percentage = VALUES(battery_percentage),
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->bind_param(
        "iddddi",
        $userId,
        $latitude,
        $longitude,
        $accuracy,
        $speed,
        $battery
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Store History
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO location_history
        (
            delivery_boy,
            latitude,
            longitude
        )
        VALUES
        (?, ?, ?)
    ");

    $stmt->bind_param(
        "idd",
        $userId,
        $latitude,
        $longitude
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

        "LIVE_LOCATION_UPDATED",

        [

            "latitude" => $latitude,

            "longitude" => $longitude,

            "accuracy" => $accuracy,

            "speed" => $speed,

            "battery" => $battery

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

        "Location updated successfully.",

        [

            "latitude" => $latitude,

            "longitude" => $longitude,

            "updated_at" => date("Y-m-d H:i:s")

        ]

    );

}
catch (Throwable $e) {

    $conn->rollback();

    logError(
        "UPDATE LIVE LOCATION ERROR : " .
        $e->getMessage()
    );

    serverError();

}