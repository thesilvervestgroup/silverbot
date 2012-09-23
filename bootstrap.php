<?php

// defining this here and not in the ini file below for true debugging stuff
define('LOG_LEVEL_NONE', 0);
define('LOG_LEVEL_ERROR', 1);
define('LOG_LEVEL_INFO', 2);
define('LOG_LEVEL_DEBUG', 3);
define('DEBUG', LOG_LEVEL_ERROR);

if (!defined('__DIR__')) // for backwards compat
	define('__DIR__', dirname(__FILE__));

// parse cli arguments
require_once 'Console/CommandLine.php';
$parser = new Console_CommandLine();
$parser->addOption(
	'config',
	array(
		'short_name'  => '-c',
		'long_name'   => '--config',
		'description' => 'configuration file to use, defaults to \'config.ini\'',
		'default'     => 'config.ini'
	)
);

$parser->addOption(
	'daemon',
	array(
		'short_name'  => '-d',
		'long_name'   => '--daemon',
		'description' => 'run the bot in the background',
		'action'      => 'StoreTrue',
		'default'     => false
	)
);

$result = $parser->parse();
$options = $result->options;

if (file_exists($options['config']) !== true)
    die("Can't load config file '{$options['config']}'\n");

// whether we want to daemonise
define('DAEMON', ($options['daemon'] == true));

// load the config items globally
$config = parse_ini_file($options['config'], true);

// set the timezone
$timezone = 'Australia/Sydney';
if (isset($config['SilverBot']['timezone'])) {
    $timezone = $config['SilverBot']['timezone'];
}
date_default_timezone_set($timezone);

function __autoload($class_name) {
	// check for a native class first
	$filename = 'class/'.strtolower($class_name).'.class.php';

	if (!file_exists($filename)) {
		// if we can't find a native class, look for a plugin
		$filename = 'plugins/'.ucwords($class_name).'/plugin.php';

		if (!file_exists($filename)) {
			// i can't see shit, captain
			throw new Exception("Unable to load $class_name."); // this only works in php 5.3.0 or greater
			return false; // so we need to also return false
		}
	}
	
	// found it, include it
	include_once $filename;
}

// logging either to syslog or to stdout
function olog($str, $level = LOG_LEVEL_ERROR) {
	if (DEBUG >= $level) print date('M d H:i:s ') . '[' . posix_getpid() . ']: ' . $str . "\n";
}
