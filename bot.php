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
$bot->addPlugin('Auth'); // should probably get loaded first (though doesn't really matter)
$bot->addPlugin('Channel');
$bot->connect();

