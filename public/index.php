<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// cPanel en subcarpeta /korapp: si la URL limpia reescribe a /public,
// Laravel debe tratar la base como /korapp (no /korapp/public).
if (
    isset($_SERVER['SCRIPT_NAME'])
    && str_contains($_SERVER['SCRIPT_NAME'], '/korapp/public/index.php')
) {
    $_SERVER['SCRIPT_NAME'] = str_replace(
        '/korapp/public/index.php',
        '/korapp/index.php',
        $_SERVER['SCRIPT_NAME']
    );
}

if (
    isset($_SERVER['PHP_SELF'])
    && str_contains($_SERVER['PHP_SELF'], '/korapp/public/index.php')
) {
    $_SERVER['PHP_SELF'] = str_replace(
        '/korapp/public/index.php',
        '/korapp/index.php',
        $_SERVER['PHP_SELF']
    );
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
