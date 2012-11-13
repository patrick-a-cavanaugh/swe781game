<?php
define('BASE_URL', 'https://www.securitygame.localdev');
?>
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title></title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width">

        <!-- Place favicon.ico and apple-touch-icon.png in the root directory -->

        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/bootstrap.min.css">
        <script src="<?php echo BASE_URL; ?>/js/require.js" data-main="<?php echo BASE_URL; ?>/js/main.js"></script>
    </head>
<body>
<input type="hidden" value="<?php echo BASE_URL; ?>" id="gameAppBaseUrl" />
<input type="hidden" value="<?php echo BASE_URL; ?>/api" id="gameApiBaseUrl" />
<!--[if lt IE 7]>
<p class="chromeframe">You are using an outdated browser. <a href="http://browsehappy.com/">Upgrade your browser
    today</a> or <a href="http://www.google.com/chromeframe/?redirect=true">install Google Chrome Frame</a> to better
    experience this site.</p>
<![endif]-->

<div class="container">
    <div class="navbar navbar-inverse">
        <div class="navbar-inner">
            <a class="brand" href="<?php echo BASE_URL; ?>">SecurityGame</a>

            <ul class="nav pull-right">
                <li ><a href="<?php echo BASE_URL; ?>/gameApp/logout">Log out</a></li>
            </ul>
        </div>

    </div>

    <div id="flashMessage">

    </div>

    <div id="mainFrame">
        <!-- Add your site or application content here -->
        <p>Loading&hellip;</p>

    </div>
</div>

<script type="text/html" id="loginFormTmpl">
    <?php require 'tmpl/loginForm.tmpl.html'; ?>
</script>

<script type="text/html" id="registerFormTmpl">
    <?php require 'tmpl/registerForm.tmpl.html'; ?>
</script>

<script type="text/html" id="userHomeTmpl">
    <?php require 'tmpl/userHome.tmpl.html'; ?>
</script>

<script type="text/html" id="userHomeGameListItemTmpl">
    <?php require 'tmpl/userHomeIndivGame.tmpl.html'; ?>
</script>

<script type="text/html" id="gameHomeTmpl">
    <?php require 'tmpl/gameHome.tmpl.html'; ?>
</script>

</body>
</html>
