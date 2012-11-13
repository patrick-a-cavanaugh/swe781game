## Installation basics ##

PHP 5.4 (with certain extensions including OpenSSL) and a suitable app server are required, ports 80 and 443
 should be open on the server.

Instructions for Apache 2.2 with mod_php are provided. Configuring an alternate server is not covered.

Please see the Instructions/sample.httpd.conf for how to configure it using Apache vhosts.

If you are configuring it without using vhosts in Apache, there is no need to configure anything in the
 httpd.conf, except to make sure htaccess files are enabled. (Set "AllowOverride All").

## Install Dependencies Using PHP Composer ##

Dependencies for the PHP application are not packaged in this repository and must
be downloaded using Composer. Install PHP composer and then run "composer install"
in the API/ directory. This installs the dependencies into vendor/.

## Set File Permissions ##

API/logs/ must have permissions for the web server to write into it.

## Set Database credentials and SMTP credentials ##

Copy API/config/dev.config.example.php to API/config/dev.config.php and add your
DB/username/password and SMTP credentials.

## Update files with correct URL ##

Several files in both PublicWeb and API need absolute URLs or paths to operate correctly. Update the following files accordingly (look for the ".localdev" URLs and change them):

API/config/dev.config.php (if running in development. Else whatever config file is in question)
PublicWeb/api/.htaccess (Mod rewrite rule)
PublicWeb/api/index.php (Path to API folder - this may not need to change.)
PublicWeb/index.php (edit PHP define at top of file)
PublicWeb/js/custom/config.php (edit wsUrl variable)

## Update database structure ##

First run the migrations by going on the command line to the API/ folder and run
the command "./console knp:migration:migrate" to update the DB with the correct
schema.

Then you must add some seed data using a DB client. Copy the contents of
src/resources/seedData.sql and run them in the DB you have the app connect to.