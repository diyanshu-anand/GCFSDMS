<!-- Deployment requires change of credentials.-->
 <!-- """
  1. Credentials are of local.
  2. Basic and effecient connection code.
  3. No intense docker initialization.
 """-->

<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "fsdms";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Database Connection Failed"
    ]));
}

$conn->set_charset("utf8mb4");

?>