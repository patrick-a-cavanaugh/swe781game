<?php
use Game\GameAppConfig;

/**
 * @file test.config.php - Configuration values for local development. Not suitable for production.
 */

global $app;

// Safety measure to prevent accessing TEST config on a live, public server.
if (php_sapi_name() !== 'cli' && !in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
    die('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}

$app['debug'] = true;

GameAppConfig::$disableMailer = true;

GameAppConfig::$dbOptions['dbname'] = 'swe781_test';
GameAppConfig::$dbOptions['user']   = 'swe781_test';
// Ok to store this in git because it's a local dev database password and isn't exposed to the net or attackers at all.
GameAppConfig::$dbOptions['password'] = 'testDatabasePassword_tahNaeg4';