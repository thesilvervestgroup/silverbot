<?php

define('DEBUG', false);

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

