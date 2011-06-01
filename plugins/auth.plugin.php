<?php

class Auth extends SilverBotPlugin {
	public $trigger = '~';
	public $accounts = array();
	private $db = 'sqlite:auth.db';
	private $dbh; // for the PDO connection
	
	// init the db and load users
	public function __construct() {
		$this->dbh = new PDO($this->db, '', '');

		$result = $this->dbh->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
		if ($result->fetchColumn() === false) { // db not initialised
			$this->dbh->query("CREATE TABLE users (name TEXT PRIMARY KEY, hostmask TEXT);");
		} else {
			$result = $this->dbh->query('SELECT * FROM users');
			if (!empty($result)) foreach ($result as $row) {
				$this->accounts[$row['name']] = $row['hostmask'];
			}
		}
	}
	
	public function hasAccess($mask) {
		foreach ($this->accounts as $user=>$test)
			if (preg_match('/'.$test.'/', $mask) == 1) return true;
		return false;
	}
	
	public function addUser($user, $mask) {
		return ($this->dbh->prepare('INSERT INTO users (name, hostmask) VALUES (?, ?)')->execute(array($user, $mask)));
	}
	
	public function delUser($user) {
		return ($this->dbh->prepare('DELETE FROM users WHERE name = ?')->execute(array($user)));
	}
	
	public function editUser($user, $newmask) {
		return ($this->dbh->prepare('UPDATE users SET hostmask = ? WHERE name = ?')->execute(array($newmask, $user)));
	}
		
	private function refreshUsers() {
		$result = $this->dbh->query('SELECT * FROM users');
		if (!empty($result)) foreach ($result as $row) {
			$this->accounts[$row['name']] = $row['hostmask'];
		}
	}
	
	public function prv_adduser($data) {
		if ($this->hasAccess($data['user_host'])) {
			$params = explode(' ', $data['data']);
			if (count($params) != 2) {
				$this->bot->reply('Usage: ~adduser <nickname> <hostmask (regexp)>');
				return;
			}
			$this->addUser($params[0], $params[1]);
			$this->bot->reply("User '{$params[0]}' added!");
			
			$this->refreshUsers();
		}
	}
	
	public function prv_deluser($data) {
		if ($this->hasAccess($data['user_host'])) {
			$params = explode(' ', $data['data']);
			if (count($params) != 1) {
				$this->bot->reply('Usage: ~deluser <nickname>');
				return;
			}
			$this->delUser($params[0]);
			$this->bot->reply("Access for '{$params[0]}' revoked!");

			$this->refreshUsers();
		}
	}
	
	public function prv_edituser($data) {
		if ($this->hasAccess($data['user_host'])) {
			$params = explode(' ', $data['data']);
			if (count($params) != 2) {
				$this->bot->reply('Usage: ~edituser <nickname> <new hostmask (regexp)>');
				return;
			}
			$this->editUser($params[0], $params[1]);
			$this->bot->reply("Hostmask for '{$params[0]}' updated to '{$params[1]}!");

			$this->refreshUsers();
		}
	}
	
}
