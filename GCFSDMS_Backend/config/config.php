<!-- 
Coding standard to follow:
Use MySQLi with prepared statements.
Never concatenate SQL strings.
Return JSON only.
Use proper HTTP status codes.
Store passwords using password_hash().
Verify passwords using password_verify().
All protected APIs require JWT.
Never expose database errors to clients.
Log server errors internally.
Keep reusable logic inside helpers/.
One responsibility per file. 

It should define:

1. Application Name
2. API Version
3. Timezone
4. JWT Secret
5. JWT Expiry
6. JWT Issuer
7. JWT Audience
8. JWT Algorithm
9. CORS Configuration (if required)
10. Development Mode
-->

<?php

/**
 * ------------------------------------------------------------
 * FSDMS Configuration File
 * ------------------------------------------------------------
 * Author      : Divyanshu Anand
 * Project     : FSDMS (Food Supply & Delivery Management System)
 * Version     : v1.0.0
 * Created On  : 19 July 2026
 * ------------------------------------------------------------
 */

date_default_timezone_set('Asia/Kolkata');

/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'FSDMS');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');     // development | production

/*
|--------------------------------------------------------------------------
| JWT Configuration
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Change JWT_SECRET before deploying to production.
|
*/

define('JWT_SECRET', 'REPLACE_WITH_A_LONG_RANDOM_SECRET_KEY');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE_TIME', 3600);       // 1 Hour (Seconds)
define('JWT_ISSUER', 'FSDMS_SERVER');
define('JWT_AUDIENCE', 'FSDMS_ANDROID');

/*
|--------------------------------------------------------------------------
| API Configuration
|--------------------------------------------------------------------------
*/

define('API_CHARSET', 'UTF-8');
define('DEFAULT_CONTENT_TYPE', 'application/json');

/*
|--------------------------------------------------------------------------
| Status Constants
|--------------------------------------------------------------------------
*/

define('STATUS_ACTIVE', 'ACTIVE');
define('STATUS_INACTIVE', 'INACTIVE');
define('STATUS_BLOCKED', 'BLOCKED');
define('STATUS_DELETED', 'DELETED');

/*
|--------------------------------------------------------------------------
| User Roles
|--------------------------------------------------------------------------
*/

define('ROLE_ADMIN', 'Admin');
define('ROLE_MANAGER', 'Manager');
define('ROLE_DELIVERY_AGENT', 'Delivery_agent');

?>