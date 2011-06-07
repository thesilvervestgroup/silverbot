<?php

require_once('bootstrap.php');

$config = array( 
	'server' => 'irc.m3rls.com',
	'port' => 6667,
	'nick' => 'silverbot',
	'name' => 'silverbot',
	'pass' => '',
	'channels' => array(
		'#tsg',
	),
);

$bot = new SilverBot($config);
$bot->connect();

