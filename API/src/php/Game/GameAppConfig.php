<?php

namespace Game;

/**
 * Update the static values here in the config files.
 */ 
class GameAppConfig {

    public static $clientBaseUrl = "https://www.securitygame.localdev";

    public static $mailerOptions = [];

    public static $dbOptions = array(
        'driver'        => 'pdo_mysql',
        'dbname'        => 'swe781_dev',
        'host'          => 'localhost',
        'user'          => 'swe781_dev',
        'charset'       => 'utf8',
        'password'      => 'INSERT PASSWORD HERE', // You must set your own password in dev.config.php!
        'driverOptions' => array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8, time_zone = '+0:00'"
        )
    );

    public static $disableMailer = false;

    public static $redirectMails = true;

}
