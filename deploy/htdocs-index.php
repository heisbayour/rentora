<?php

declare(strict_types=1);

/*
 * Front controller for the deploy package (scripts/build-deploy.php copies
 * this over public/index.php). The document root holds the contents of
 * public/, and the application lives in _rentora/ next to this file, so
 * no real folder at the top level shadows a route such as /app or /vendor.
 * Local development keeps using public/index.php unchanged.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = __DIR__.'/_rentora';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

// public_path(), the "uploads" disk and Vite's build/ resolve to this folder.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
