<?php

require_once __DIR__.'/bootstrap.php';

$pid = 0;
if (DAEMON) {
	$pid = pcntl_fork();
	if ($pid == -1)
		die("ERROR: Could not background process!\n");
}

if ($pid == 0) { // we're either the child fork, or we're not forked at all
	$bot = new SilverBot($config);
	$bot->connect();
}

// exit nicely
exit(0);
