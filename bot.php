<?php

require_once('bootstrap.php');

$config = array( 
	'server' => 'irc.austnet.org',
	'port' => 6667,
	'nick' => 'snacro',
	'name' => 'snacro',
	'pass' => '',
	'channels' => array(
		'#@home',
	),
);

$bot = new SilverBot($config);
$bot->addPlugin('Auth'); // should probably get loaded first (though doesn't really matter)
$bot->addPlugin('Channel');
$bot->addPlugin('Snacro');
$bot->connect();

