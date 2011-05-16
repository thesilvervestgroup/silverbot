<?php

function __autoload($class_name) {
    global $config;
	$filename = 'class/'.strtolower($class_name).'.class.php';
	print "looking for $filename\n";
	if (!file_exists($filename)) {
		$filename = 'plugins/'.strtolower($class_name).'.plugin.php';
        print "looking for $filename\n";
        if (!file_exists($filename)) {
            throw new Exception("Unable to load $class_name.");
            return false;
        }

        if(@in_array($class_name, $config['plugins']) !== true) {
            print "skipping $class_name\n";
            return ;
        }
	}

    include_once($filename);
}

