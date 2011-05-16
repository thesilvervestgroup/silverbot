<?php
if(file_exists('config.php') !== true)
    die("Doh! It looks like you haven't created your own config.php file. Please copy config.php-default to config.php and make your changes");

require_once('config.php');
require_once('bootstrap.php');

$bot = new SilverBot($config);
$bot->connect();
