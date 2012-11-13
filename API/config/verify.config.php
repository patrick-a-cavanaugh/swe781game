<?php

use Game\GameAppConfig;

/** @file verify.config.php - checks that configuration values are set and are not set incorrectly */

// Check some config values that we can't set at runtime. If the server was misconfigured, security issues would
// result, so we just die instead.
if (get_magic_quotes_gpc() || get_magic_quotes_runtime()) {
    trigger_error("Magic quotes should not be enabled", E_USER_ERROR);
}

$php_version = explode(".", phpversion());
foreach ($php_version as &$version_component) {
    $version_component = intval($version_component, 10);
}
if ($php_version[0] < 5 || $php_version[1] < 4) {
    trigger_error("PHP 5.4 is required", E_USER_ERROR);
}
if (empty(GameAppConfig::$dbOptions['password']) && GameAppConfig::$dbOptions['driver'] === 'pdo_mysql') {
    trigger_error("You must set a database password to connect to the database", E_USER_ERROR);
}