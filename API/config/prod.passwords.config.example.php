<?php
use Game\GameAppConfig;

// Set your Gmail password below or customize the SMTP settings in order to send mail.
GameAppConfig::$mailerOptions = [
    'host'       => 'smtp.gmail.com',
    'port'       => '465',
    'username'   => 'sgp@patcavanaugh.info',
    'password'   => '',
    'encryption' => 'ssl',
    'auth_mode'  => 'login'
];

GameAppConfig::$dbOptions['dbname'] = 'swe781_prod';
// Add your database password here. If you need to use a different database or driver, configure that as well.
GameAppConfig::$dbOptions['password'] = '';