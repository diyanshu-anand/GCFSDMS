<?php

/**
 * ------------------------------------------------------------
 * FSDMS Bootstrap
 * ------------------------------------------------------------
 * Author  : Divyanshu Anand
 * Purpose : Initialize backend dependencies for every API.
 * ------------------------------------------------------------
 */

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/constants.php";
require_once __DIR__ . "/config/db.php";


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/helpers/response.php";
require_once __DIR__ . "/helpers/request.php";
require_once __DIR__ . "/helpers/validator.php";
require_once __DIR__ . "/helpers/jwt.php";
require_once __DIR__ . "/helpers/auth.php";
require_once __DIR__ . "/helpers/logger.php";