<?php

require_once('bootstrap.php');

$config = array( 
	'server' => 'irc.example.com',
	'port' => 6667,
	'nick' => 'silverbot',
	'name' => 'silverbot',
	'pass' => '',
	'channels' => array(
		'#silverbot',
	),
);

$bot = new SilverBot($config);
$bot->connect();

