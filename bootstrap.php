<?php

define('DEBUG', false);

function __autoload($class_name) {
	$filename = 'class/'.strtolower($class_name).'.class.php';
	print "looking for $filename\n";
	if (!file_exists($filename)) {
		$filename = 'plugins/'.ucwords($class_name).'/plugin.php';
		print "looking for $filename\n";
		if (!file_exists($filename)) {
			throw new Exception("Unable to load $class_name.");
			return false;
		}
	}
	include_once($filename);
}

