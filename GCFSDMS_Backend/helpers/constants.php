<?php

/**
 * ------------------------------------------------------------
 * FSDMS Constants
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Common constants used throughout the application.
 * ------------------------------------------------------------
 */

/*
|--------------------------------------------------------------------------
| User Roles
|--------------------------------------------------------------------------
*/

define('ROLE_ADMIN', 'Admin');
define('ROLE_MANAGER', 'Manager');
define('ROLE_DELIVERY_AGENT', 'Delivery_agent');


/*
|--------------------------------------------------------------------------
| User / Company Status
|--------------------------------------------------------------------------
*/

define('STATUS_ACTIVE', 'ACTIVE');
define('STATUS_INACTIVE', 'INACTIVE');
define('STATUS_BLOCKED', 'BLOCKED');
define('STATUS_DELETED', 'DELETED');


/*
|--------------------------------------------------------------------------
| Order Status
|--------------------------------------------------------------------------
*/

define('ORDER_PENDING', 'Pending');
define('ORDER_ACCEPTED', 'Accepted');
define('ORDER_PICKED', 'Picked');
define('ORDER_OUT_FOR_DELIVERY', 'Out for Delivery');
define('ORDER_DELIVERED', 'Delivered');
define('ORDER_CANCELLED', 'Cancelled');


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

define('PAYMENT_PENDING', 'Pending');
define('PAYMENT_PAID', 'Paid');
define('PAYMENT_FAILED', 'Failed');
define('PAYMENT_REFUNDED', 'Refunded');


/*
|--------------------------------------------------------------------------
| Payment Modes
|--------------------------------------------------------------------------
*/

define('PAYMENT_CASH', 'Cash');
define('PAYMENT_UPI', 'UPI');
define('PAYMENT_CARD', 'Card');
define('PAYMENT_NET_BANKING', 'Net Banking');
define('PAYMENT_CHEQUE', 'Cheque');


/*
|--------------------------------------------------------------------------
| Inventory Transaction Types
|--------------------------------------------------------------------------
*/

define('INVENTORY_REMOVE', 'REMOVE');
define('INVENTORY_RETURN', 'RETURN');


/*
|--------------------------------------------------------------------------
| HTTP Status Codes
|--------------------------------------------------------------------------
*/

define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_CONFLICT', 409);
define('HTTP_INTERNAL_SERVER_ERROR', 500);


/*
|--------------------------------------------------------------------------
| Common Response Messages
|--------------------------------------------------------------------------
*/

define('MSG_SUCCESS', 'Success');
define('MSG_FAILED', 'Failed');
define('MSG_LOGIN_SUCCESS', 'Login successful');
define('MSG_INVALID_CREDENTIALS', 'Invalid phone or password');
define('MSG_UNAUTHORIZED', 'Unauthorized access');
define('MSG_TOKEN_EXPIRED', 'Session expired');
define('MSG_INVALID_TOKEN', 'Invalid token');
define('MSG_ACCOUNT_BLOCKED', 'Account is blocked');
define('MSG_ACCOUNT_INACTIVE', 'Account is inactive');
define('MSG_COMPANY_INACTIVE', 'Company is inactive');
define('MSG_REQUIRED_FIELDS', 'Required fields are missing');
define('MSG_METHOD_NOT_ALLOWED', 'Method not allowed');
define('MSG_INTERNAL_ERROR', 'Internal server error');


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

define('AUTH_HEADER', 'Authorization');
define('TOKEN_PREFIX', 'Bearer ');