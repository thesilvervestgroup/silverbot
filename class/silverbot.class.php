<?php

/**
 * SilverBot 
 * 
 * @package 
 * @version $id$
 * @copyright 
 * @author The Silvervest Group
 * @license 
 */
class SilverBot {
	protected $socket;
	protected $config = array();
	protected $plugins = array();
	protected $channels = array();
	protected $settings = array();
	public $nickname = '';

	public function __construct($config) {
		$this->settings = $config; // full settings array, incl plugin settings
		$this->config = $config['SilverBot']; // config for the actual bot

		// fix hostname if we're configured for ssl
		if ($this->config['ssl'] == true)
			$this->config['name'] = 'ssl://' . $this->config['name'];
			
		register_shutdown_function(array($this, '__destruct'));
		$this->discoverPlugins();
	}
	
	public function __destruct() {
		if (isset($this->socket) && $this->socket) {
			$this->disconnect('Terminated');
		}
	}

	// basic IRC network-y type functions
	public function disconnect($message = '') {
		olog('Exiting!', LOG_LEVEL_INFO);
		$this->send("QUIT :$message");
		fclose($this->socket);
		unset($this->socket);
		exit(0);
	}
	
	public function connect() {
		$altnick = false;
		olog("Connecting to {$this->config['name']}:{$this->config['port']}...", LOG_LEVEL_INFO);
		$this->socket = fsockopen($this->config['name'], $this->config['port']);
		if ($this->socket === false) {
			olog("ERROR: unable to connect to {$this->config['server']}:{$this->config['port']}");
			return;
		}
		if (!empty($this->config['pass']))
			$this->send("PASS {$this->config['pass']}");
		$this->send("USER {$this->config['nick']} - - :{$this->config['ident']}");
		$this->send("NICK {$this->config['nick']}");
		$this->nickname = $this->config['nick'];
        if(isset($this->config['oper']) && !empty($this->config['oper']))
            $this->send("OPER " . $this->config["oper"]);
		
		// process the connect handlers
		while (!feof($this->socket)) {
			$incoming = rtrim(fgets($this->socket));
            olog("<== $incoming", LOG_LEVEL_DEBUG);
            //reply to any pings that happen before the motd (some servers do this and refuse to this and refuse to send the MOTD until you pong)
			if (substr($incoming, 0, 6) == 'PING :') {
				$this->send('PONG :' . substr($incoming, 6));
				continue;
			}
			$parts = explode(' ', $incoming);
			
			switch ($parts[1]) {
				// 376 and 442 are end of motd or motd not found respectively
				// which is indicative of the connect process completing
				case '376':
				case '422':
					// process plugin on-connect functions
					foreach ($this->plugins as $plugname=>$plugin) {
						$plugin['plugin']->onConnect();
					}
					olog("Connected!", LOG_LEVEL_INFO);
					break 2;

				// 433 is 'nickname already in use' so go to our alternative
				case '433':
					if ($altnick == true) {
						olog("Both primary and alternate nicknames are in use, exiting...");
						$this->disconnect();
					}
					olog("Nickname '{$this->config['nick']}' already in use, trying alternate '{$this->config['alt_nick']}'...", LOG_LEVEL_INFO);
					$this->send("NICK {$this->config['alt_nick']}");
					$this->nickname = $this->config['alt_nick'];
					$altnick = true;
					break;
			}
		}
		
		// now that we're connected, start looping
		$this->main();
		
		
		// if we got here, it means we were disconnected
		// notify all plugins that we were disconnected
		olog('Disconnected...', LOG_LEVEL_INFO);
		foreach ($this->plugins as $plugname=>$plugin) {
			$plugin['plugin']->onDisconnect();
		}
		// and try to reconnect if we're configured to
		if ($this->config['reconnect'] == true) {
			olog('Attempting to reconnect.', LOG_LEVEL_INFO);
			$this->connect(); // this... might get recursive ???
		}
	}
	
	public function send($data) {
		if (!$this->socket) return;
		olog("==> $data", LOG_LEVEL_DEBUG);
		fwrite($this->socket, $data . "\r\n");
	}

	// responds to the last incoming source with your text
	public function reply($text) {
		if (empty($this->processed['source'])) return;
		$string = 'PRIVMSG ' . $this->processed['source'] . ' :' . $text;
		$this->send($string);
	}
	
	// directly messages $user with $text
	public function pm($user, $text) {
		$string = 'PRIVMSG ' . $user . ' :' . $text;
		$this->send($string);
	}

	// main loop, should only be called from $this->connect()
	private function main() {
		socket_set_blocking($this->socket, false); // so we can do timed events
		
		while (!feof($this->socket)) {
			while (!$incoming = rtrim(fgets($this->socket))) { // check if there's data in the pipes
				// nope! so, we need to process plugin timers, then continue sleeping for the remainder of our 100ms pause
				// this code has been tested to be accurate to about 0.5-1ms (and damnit, that's good enough for me)
				$start = microtime(true);
				$this->processTimers();
				$end = microtime(true);
				$diff = 100000 - ((($end - $start) * 1000000) * 1.0000005); // add some trial-and-error fuzz
				if ($diff > 0) usleep($diff);
			}
			
			// looks like we've got data in them-thar pipes
			olog("<== $incoming", LOG_LEVEL_DEBUG);
			
			// play table tennis if necessary
			if (substr($incoming, 0, 6) == 'PING :') {
				$this->send('PONG :' . substr($incoming, 6));
				continue;
			}

			$this->handle($incoming);
		}
	}
	
	// handles incoming data
	private function handle($input) {
		$this->processed = $this->process($input);
		
		switch (strtoupper($this->processed['command'])) {
			case 'PRIVMSG':
				$trigger = substr($this->processed['text'], 0, 1);
				if (strpos($this->processed['text'], ' ') === false) {
					$command = substr($this->processed['text'], 1);
					$this->processed['data'] = '';
				} else {
					$command = substr($this->processed['text'], 1, strpos($this->processed['text'], ' ') - 1);
					$this->processed['data'] = substr($this->processed['text'], strpos($this->processed['text'], ' ') + 1);
				}
				$this->callPublic($trigger, $command, $this->processed);
				if (substr($this->processed['source'], 0, 1) == '#') {
					$this->callChannel($trigger, $command, $this->processed);
				} else {
					$this->callPrivate($trigger, $command, $this->processed);
				}
				break;
				
			case 'PART':
				foreach ($this->plugins as $plugname=>$plugin) {
					$plugin['plugin']->onPart($this->processed);
				}
				break;
				
			case 'JOIN':
				foreach ($this->plugins as $plugname=>$plugin) {
					$plugin['plugin']->onJoin($this->processed);
				}
				break;

			case 'KICK':
				// split off who was kicked
				list($this->processed['kicked'], $this->processed['text']) = explode(' ', $this->processed['text'], 2);
				foreach ($this->plugins as $plugname=>$plugin) {
					$plugin['plugin']->onKick($this->processed);
				}

		}
	}
	
	// processes incoming text into a useful data set
	protected function process($input, $arguments = -1) {
		// split off the user prefix (if available)
		if (substr($input, 0, 1) == ':') {
			list($user, $input) = explode(' ', $input, 2);
			$data['username'] = substr($user, 1, strpos($user, "!")-1);
			$a = strpos($user, "!");
			$b = strpos($user, "@");
			$data['ident'] = substr($user, $a+1, $b-$a-1);
			$data['hostname'] = substr($user, strpos($user, "@")+1);
			$data['user_host'] = substr($user, 1);
		}
		
		// now work on the rest
		$buffer = preg_split('/ :?/S', $input, $arguments);

		$data['command'] = array_shift($buffer);
		$source = array_shift($buffer);
		if (strpos($source, '#') === false) // no # means it came from a user, not a channel
			$data['source'] = $data['username'];
		else
			$data['source'] = $source;
		
		$data['text'] = '';
		if (!empty($buffer))
			$data['text'] = implode(' ', $buffer);
		
		return $data;
	}
	// end basic IRC funcs
	
	// plugin handling
	private function discoverPlugins() {
		$pluginFiles = glob("plugins/*/plugin.php");
       	foreach($pluginFiles as $pluginFile) {
       		preg_match('/^plugins\/(.+?)\/plugin\.php$/', $pluginFile, $matches);
       		list(,$className) = $matches;       		
       		$this->addPlugin($className);
		}
	}

	public function addPlugin($name) {
		if (!class_exists($name)) {
			olog("ERROR: Could not load plugin '$name'");
			return;
		}

		if (get_parent_class($name) !== "SilverBotPlugin") {
			olog("ERROR: $name does not inherit from SilverBotPlugin");
			return;
		}
		
		$plugin = new $name();
		if (!method_exists($plugin, 'ident') || !method_exists($plugin, 'register')) {
			olog("ERROR: Plugin '$name' isn't a valid plugin");
			return;
		}
		
		$plugname = $plugin->ident();
		if (array_key_exists($plugname, $this->plugins)) {
			olog("ERROR: Plugin '$name' already loaded");
			return;
		}
		
		$commands = $plugin->register();
		$plugin->setup($this, (!empty($this->settings[$name]) ? $this->settings[$name] : array()));
		
		$this->plugins[$plugname]['commands'] = $commands;
		$this->plugins[$plugname]['plugin'] = $plugin;
		$this->$name =& $this->plugins[$plugname]['plugin'];
		olog("Loaded plugin '$plugname'", LOG_LEVEL_INFO);
	}

    protected function processTimers() {
		foreach ($this->plugins as $plugname=>$plugin) {
            $plugin["plugin"]->processTimers();
		}
    }
	
	protected function callPublic($trigger, $command, $data) {
		foreach ($this->plugins as $plugname=>$plugin) {
     		$plugin['plugin']->pub($data);
			if ($plugin['plugin']->trigger !== $trigger) continue;
			foreach ($plugin['commands']['public'] as $name) {
				if ($name === $command) { // match!
					$cmd = 'pub_' . $name;
					$plugin['plugin']->$cmd($data);
					continue;
				}
			}
		}
	}
	
	protected function callPrivate($trigger, $command, $data) {
		foreach ($this->plugins as $plugname=>$plugin) {
    		$plugin['plugin']->prv($data);
			if ($plugin['plugin']->trigger !== $trigger) continue;
			foreach ($plugin['commands']['private'] as $name) {
				if ($name === $command) { // match!
					$cmd = 'prv_' . $name;
					$plugin['plugin']->$cmd($data);
					continue;
				}
			}
		}
	}
	
	protected function callChannel($trigger, $command, $data) {
		foreach ($this->plugins as $plugname=>$plugin) {
    		$plugin['plugin']->chn($data);
			if ($plugin['plugin']->trigger !== $trigger) continue;
			foreach ($plugin['commands']['channel'] as $name) {
				if ($name === $command) { // match!
					$cmd = 'chn_' . $name;
					$plugin['plugin']->$cmd($data);
					continue;
				}
			}
		}
	}	
}
