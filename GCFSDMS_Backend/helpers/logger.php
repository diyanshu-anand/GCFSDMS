<?php

/**
 * ------------------------------------------------------------
 * FSDMS Logger Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Application Logging Utility
 * ------------------------------------------------------------
 */

require_once __DIR__ . "/../config/config.php";

/**
 * Log Levels
 */
define('LOG_INFO', 'INFO');
define('LOG_WARNING', 'WARNING');
define('LOG_ERROR', 'ERROR');

/**
 * Write Log
 */
function writeLog(string $level, string $message): void
{
    $logDirectory = __DIR__ . "/../logs";

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0777, true);
    }

    $fileName = date("Y-m-d") . ".log";

    $filePath = $logDirectory . "/" . $fileName;

    $time = date("Y-m-d H:i:s");

    $log = sprintf(
        "[%s] [%s] %s%s",
        $time,
        $level,
        $message,
        PHP_EOL
    );

    file_put_contents(
        $filePath,
        $log,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Information Log
 */
function logInfo(string $message): void
{
    writeLog(LOG_INFO, $message);
}

/**
 * Warning Log
 */
function logWarning(string $message): void
{
    writeLog(LOG_WARNING, $message);
}

/**
 * Error Log
 */
function logError(string $message): void
{
    writeLog(LOG_ERROR, $message);
}

// App log table writing
function logActivity(
    mysqli $conn,
    int $userId,
    string $activity,
    array $data = []
): bool
{
    $query = "
        INSERT INTO app_logs
        (
            user_id,
            activity,
            ip_address,
            device,
            data
        )
        VALUES
        (?, ?, ?, ?, ?)
    ";

    $statement = $conn->prepare($query);

    if (!$statement) {
        logError("Failed to prepare activity log statement.");
        return false;
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? "UNKNOWN";
    $device = $_SERVER['HTTP_USER_AGENT'] ?? "UNKNOWN";
    $jsonData = json_encode($data);

    $statement->bind_param(
        "issss",
        $userId,
        $activity,
        $ipAddress,
        $device,
        $jsonData
    );

    $result = $statement->execute();

    $statement->close();

    return $result;
}