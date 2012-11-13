<?php
/**
 * @file global.config.php - configuration values that provide sane defaults even if used in production.
 */

global $app;

$app['debug'] = false; // Don't print out exception stack traces to end users
ini_set('display_errors', false); // Don't accidentally display errors to the user

// Set UTC time as standard to avoid any weird bugs that might happen
date_default_timezone_set('UTC');
