<!-- 
It should only validate and sanitize data.

It should never touch the database.

It should never return JSON.

It should never generate JWT.

Only validation. 
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Validator Helper
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Common validation and sanitization functions.
 * ------------------------------------------------------------
 */

/**
 * Remove unnecessary spaces and encode HTML characters.
 */
function sanitize($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Check whether a value is present.
 */
function required($value): bool
{
    return isset($value) && trim($value) !== '';
}

/**
 * Validate Indian mobile number.
 */
function validatePhone(string $phone): bool
{
    return preg_match('/^[6-9][0-9]{9}$/', $phone) === 1;
}

/**
 * Validate email address.
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Password policy.
 * Minimum 8 characters.
 */
function validatePassword(string $password): bool
{
    return strlen($password) >= 8;
}

/**
 * Validate integer.
 */
function validateInteger($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * Validate decimal / float.
 */
function validateFloat($value): bool
{
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
}

/**
 * Validate positive integer.
 */
function validatePositiveInteger($value): bool
{
    return validateInteger($value) && $value > 0;
}

/**
 * Validate positive decimal.
 */
function validatePositiveFloat($value): bool
{
    return validateFloat($value) && $value >= 0;
}

/**
 * Validate date.
 * Format: YYYY-MM-DD
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);

    return $d &&
           $d->format('Y-m-d') === $date;
}

/**
 * Validate datetime.
 * Format: YYYY-MM-DD HH:MM:SS
 */
function validateDateTime(string $datetime): bool
{
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);

    return $d &&
           $d->format('Y-m-d H:i:s') === $datetime;
}

/**
 * Validate JSON.
 */
function validateJSON(string $json): bool
{
    json_decode($json);

    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Validate URL.
 */
function validateURL(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate Latitude.
 */
function validateLatitude($latitude): bool
{
    return is_numeric($latitude) &&
           $latitude >= -90 &&
           $latitude <= 90;
}

/**
 * Validate Longitude.
 */
function validateLongitude($longitude): bool
{
    return is_numeric($longitude) &&
           $longitude >= -180 &&
           $longitude <= 180;
}

/**
 * Validate String Length.
 */
function validateLength(string $value, int $min, int $max): bool
{
    $length = strlen(trim($value));

    return ($length >= $min && $length <= $max);
}

/**
 * Check if value exists inside allowed values.
 */
function validateEnum($value, array $allowedValues): bool
{
    return in_array($value, $allowedValues, true);
}