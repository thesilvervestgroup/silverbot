<?php

/**
 * SilverBotPlugin 
 * 
 * This plugin is going to have a mixture of camelCase and "lowercase words separated by underscores" functions
 * the non-camelCase functions are specifically for automagic calls and shouldn't be triggered manually
 * these functions should always start with prv_ or pub_ or chn_
 * eg, function prv_foo() indicates that function would be called when a user-to-user PRIVMSG is caught with 'foo' after the trigger
 * eg, function chn_foo() indicates that function would be called when a channel PRIVMSG is caught with 'foo' after the trigger
 * eg, function pub_foo() indicates that function would be called when any PRIVMSG is caught with 'foo' after the trigger
 * @abstract
 * @package 
 * @version $id$
 * @author The Silvervest Group
 */
abstract class SilverBotPlugin {
	protected $version = '0.0.1';
	public $trigger = '!'; // this is the string commands for this plugin will trigger off of
	protected $bot;	// to store the parent bot, for passing back irc commands
	protected $config = array(); // to store the parent bot configs   
    protected $timers = array(); // stores info about any times this plugin registers

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
	
	// when it is detected that we were disconnected for
	// whatever reason (even when bot was told to /quit)
	public function onDisconnect() {
		//
	}
	
	// when someone is kicked in a channel the bot is in
	// can be either the bot, or another person being kicked
	public function onKick($data) {
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
	
	// when someone invites the bot into a channel
	public function onInvite($data) {
		//
	}
	
	// for autoloading and all that crap
	public function ident() {
		return get_class($this) . '-' . $this->version;
	}

    /**
     * Adds a new timer
     * string $timerName - Name of timer to add. If the same as an existing timer, overwrite the old one.
     * string $when - When to run the timer, uses the strtotime() syntax
     * callback $func - Function to call when this timer elapses, see call_user_func() for callback details
     * mixed $args - Arguments to give to the function
     * boolean $onshot - Indicates whether this timer only happens once.
     */
    public function addTimer($timerName, $when, $func, $args = null, $oneshot = false) {
        $this->timers[$timerName] = array(
            "when" => $when,
            "nextrun" => strtotime("now + $when"),
            "func" => $func,
            "args" => $args,
            "oneshot" => $oneshot,
        );
    }

    /**
     * Removes a timer by name
     * string $timerName - The name of the timer to remove
     */
    public function removeTimer($timerName) {
        if ($this->timerExists($timerName)) {
            unset($this->timers[$timerName]);
            return true;
        }
        return false;
    }

    public function timerExists($timerName) {
        return array_key_exists($timerName, $this->timers);
    }

    public function prv($data) {
    }

    public function chn($data) {
    }

    public function pub($data) {
    }

    /**
     * Runs each registered timer.
     */
    public function processTimers() {
        foreach($this->timers as $timerName=>&$timer) {
            if (time() > $timer["nextrun"]) {
                call_user_func($timer["func"], $timer["args"]);
                if ($timer["oneshot"]) {
                    //remove any non-recurring timers
                    $this->removeTimer($timerName);
                } else {               
                    //reset the next run time
                    $timer["nextrun"] = strtotime("now + " . $timer["when"]);
                }
            } 
        }
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

    /**
     * Returns the directory this plugin should store its data files
     * Must have trailing slash
     */
    protected function getDataDirectory() {
        return 'plugins/' . get_class($this) . '/'; // relative to bot.php
    }
    
    /**
     * Helper function for plugins to use
     * Uses curl to HTTP GET a URL and returns the output
     * string $url - URL to request
     * boolean $incHeaders - whether to include HTTP Headers with the returned result
     */
	protected function curlGet($url, $incHeaders = false) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, $incHeaders);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Silverbot/1.0 (+https://github.com/thesilvervestgroup/silverbot)');
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
    
}
