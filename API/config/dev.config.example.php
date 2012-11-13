<?php
use Game\GameAppConfig;

/**
 * @file dev.config.php - Configuration values for local development. Not suitable for production.
 */

global $app;

// Safety measure to prevent accessing DEV config on a live, public server.
if (php_sapi_name() !== 'cli' && !in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
    die('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}

define('REQUIRED_SCHEME', 'https');

$app['debug'] = true; // enables Silex debugging mode including user-visible errors.
ini_set('display_errors', true);

$app['swiftmailer.options'] = [
    'host'       => 'smtp.gmail.com',
    'port'       => '465',
    'username'   => 'sgp@patcavanaugh.info',
    // Add your gmail password here to enable mail sending!
    'password'   => '',
    'encryption' => 'ssl',
    'auth_mode'  => 'login'
];

// Add your database password here. If you need to use a different database or driver, configure that as well.
GameAppConfig::$dbOptions['password'] = 'INSERT PASSWORD HERE';