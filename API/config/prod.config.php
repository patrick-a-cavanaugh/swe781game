<?php
use Game\GameAppConfig;

/**
 * @file prod.config.php - Configuration values for production environment.
 *
 * Debugging utilities are disabled and HTTPS is required.
 */

global $app;

define('REQUIRED_SCHEME', 'https');
$app['debug'] = false;
ini_set('display_errors', false);

require __DIR__ . '/prod.passwords.config.php';

GameAppConfig::$redirectMails = false;
GameAppConfig::$clientBaseUrl = "https://swe781demo.patcavanaugh.info";