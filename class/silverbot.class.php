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

	public function __construct($config) {
		$this->config = $config;		

		register_shutdown_function(array($this, '__destruct'));
		$this->discoverPlugins();
	}


	
	public function __destruct() {
		if ($this->socket) {
			$this->disconnect('Terminated');
		}
	}
	

	// basic IRC network-y type functions
	public function disconnect($message = '') {
		$this->send("QUIT :$message");
		fclose($this->socket);
	}	
	
	public function connect() {
		$this->socket = fsockopen($this->config['server'], $this->config['port']);
		if ($this->socket === false) {
			print("ERROR: unable to connect to {$this->config['server']}:{$this->config['port']}\n");
			return;
		}
		$this->send("USER {$this->config['nick']} - - :{$this->config['name']}");
		$this->send("NICK {$this->config['nick']}");
        if(isset($this->config['oper']) && !empty($this->config['oper']))
            $this->send("OPER " . $this->config["oper"]);
		
		// process the connect handlers
		while (!feof($this->socket)) {
			$incoming = trim(fgets($this->socket));
            if (DEBUG) echo $incoming . "\n";
            //reply to any pings that happen before the motd (some servers do this and refuse to this and refuse to send the MOTD until you pong)
			if (substr($incoming, 0, 6) == 'PING :') {
				$this->send('PONG :' . substr($incoming, 6));
				continue;
			}
			$parts = explode(' ', $incoming);
			// 376 and 442 are end of motd or motd not found respectively
			// which is indicative of the connect process completing
			if ($parts[1] == '376' || $parts[1] == '422') {
				// process plugin on-connect functions
				foreach ($this->plugins as $plugname=>$plugin) {
					$plugin['plugin']->onConnect();
				}
				break;
			}
		}
		
		// now that we're connected, start looping
		$this->main();
	}
	
	public function send($data) {
		if (!$this->socket) return;
		if (DEBUG) echo " ==> $data\n";
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
		socket_set_blocking($this->socket, false);
		while (!feof($this->socket)) {
			$incoming = trim(fgets($this->socket));
			if ($incoming == '') continue;
			if (DEBUG) echo " <== $incoming\n";
			if (substr($incoming, 0, 6) == 'PING :') {
				$this->send('PONG :' . substr($incoming, 6));
				continue;
			}
			$this->handle($incoming);
            //process other stuff until we see something on the stream to read
            while(!stream_socket_recvfrom($this->socket, 8, STREAM_PEEK)) {
                $this->processTimers();
    			usleep(10000); // because we're non-blocking
            }
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
		}
	}
	
	// processes incoming text into a useful data set
	protected function process($input) {
		// process the input first
		$buffer = explode(" ", $input, 4);
		$data['username'] = substr($buffer[0], 1, strpos($buffer[0], "!")-1);
		$a = strpos($buffer[0], "!");
		$b = strpos($buffer[0], "@");
		$data['ident'] = substr($buffer[0], $a+1, $b-$a-1);
		$data['hostname'] = substr($buffer[0], strpos($buffer[0], "@")+1);
		$data['user_host'] = substr($buffer[0],1);
		$data['command'] = $buffer[1];
		if (strpos($buffer[2], '#') === false) // no # means it came from a user, not a channel
			$data['source'] = $data['username'];
		else
			$data['source'] = $buffer[2];
		
		$data['text'] = '';
		if (!empty($buffer[3]))
			$data['text'] = substr($buffer[3], 1);
		
		return $data;
	}
	// end basic IRC funcs
	
	// plugin handling
	private function discoverPlugins() {
		$pluginFiles = glob("plugins/*.plugin.php");
       	foreach($pluginFiles as $pluginFile) {
       		$classname = basename($pluginFile, ".plugin.php");
       		$this->addPlugin($classname);
		}   
	}


	public function addPlugin($name) {
		if (!class_exists($name)) {
            print "WARNING: '$name' not loaded. It might not be enabled.\n";
			return;
		}

		if (get_parent_class($name) !== "SilverBotPlugin") {
			print "ERROR: $name does not inherit from SilverBotPlugin";
			return;
		}
		
		$plugin = new $name();
		if (!method_exists($plugin, 'ident') || !method_exists($plugin, 'register')) {
			print "ERROR: Plugin '$name' isn't a valid plugin\n";
			return;
		}
		
		$plugname = $plugin->ident();
		if (array_key_exists($plugname, $this->plugins)) {
			print "ERROR: Plugin '$name' already loaded\n";
			return;
		}
		
		$commands = $plugin->register();
		$plugin->setup($this, $this->config);
		
		$this->plugins[$plugname]['commands'] = $commands;
		$this->plugins[$plugname]['plugin'] = $plugin;
		$this->$name =& $this->plugins[$plugname]['plugin'];
		print "Loaded plugin '$plugname'\n";
	}

    protected function processTimers()
    {
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
