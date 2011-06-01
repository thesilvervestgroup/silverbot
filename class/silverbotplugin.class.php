<?php

// this plugin is going to have a mixture of camelCase and "lowercase words separated by underscores" functions
// the non-camelCase functions are specifically for automagic calls and shouldn't be triggered manually
// these functions should always start with prv_ or pub_ or chn_
// eg, function prv_foo() indicates that function would be called when a user-to-user PRIVMSG is caught with 'foo' after the trigger
// eg, function chn_foo() indicates that function would be called when a channel PRIVMSG is caught with 'foo' after the trigger
// eg, function pub_foo() indicates that function would be called when any PRIVMSG is caught with 'foo' after the trigger
class SilverBotPlugin {
	protected $version = '0.0.1';
	public $trigger = '!'; // this is the string commands for this plugin will trigger off of
	protected $bot;	// to store the parent bot, for passing back irc commands
	protected $config = array(); // to store the parent bot configs

	public function __construct() {
		//
	}
	
	// sets up some internal vals passed over from the bot
	public function setup($bot, $config) {
		$this->bot = $bot;
		$this->config = $config;
	}
	
	// to perform on-connect functions
	// called when bot connects to a server
	public function onConnect() {
		//
	}
	
	// to perform on-join functions
	// called when a user joins a channel the bot is in, or the bot joins a channel
	public function onJoin($data) {
		//
	}
	
	// to perform on-part functions
	// called when a user parts a channel the bot is in, or the bot parts a channel
	public function onPart($data) {
		//
	}
	
	// for autoloading and all that crap
	public function ident() {
		return get_class($this) . '-' . $this->version;
	}
	
	// registers this plugin's functions with the bot
	// must return an array in the following structure
	public function register() {
		$functions = get_class_methods(get_class($this));
		$chan = $priv = $pub = array();
		foreach ($functions as $func) {
			$type = substr($func, 0, 4);
			$name = strtolower(substr($func, 4));
			switch (strtolower($type)) {
				case 'chn_':
					$chan[] = $name; break;
				case 'prv_':
					$priv[] = $name; break;
				case 'pub_':
					$pub[] = $name; break;
			}
		}
		return array(
			'channel' => $chan,
			'private' => $priv,
			'public'  => $pub
		);
	}
}

