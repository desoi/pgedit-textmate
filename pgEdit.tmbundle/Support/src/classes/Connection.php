<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Connection management class. 
 **/


class Connection
{
	public $username = '';
	public $password = '';
	public $host = '';
	public $port = '';
	
	public $dbname = '';
	
	public $search_path = ''; // not a connection parameter, but used for partial execution
	
	public $parse_error = ''; // if set, url was not valid
	
	private static $file_connections;
	
	/**
	 * Constructor using url format: //user@host:port/database/optional/search/path
	 */
	function __construct ($url) {
		$url = trim($url);
		 // psql prefix allowed, but not required; using 'psql' because only 4 chars allowed for protocol
		if (strtolower(substr($url, 0, 5)) == 'psql:') $url = substr($url, 5); 
		$uhp = substr($url, 0, 2) == '//'; // user/host/port is specified; else default user and unix domain port
		$split = explode('/', trim($url, '/')); // remove all leading and trailing /before split
		
		if ($uhp && $split) {
			$user_host = explode('@', $split[0], 2);
			array_shift($split);
			if (count($user_host) > 1) {
				$this->username = $user_host[0];
				$this->host = $user_host[1];
			} else $this->host = $user_host[0];
			$pass = explode(':', $this->username, 2); // form of user:password
			if (count($pass) > 1) {
				$this->username = $pass[0];
				$this->password = $pass[1];
			}
			$port = explode(':', $this->host, 2); // form of host:port
			if (count($port) > 1) {
				$this->host = $port[0];
				$this->port = $port[1];
			}
			$this->username = html_entity_decode($this->username);
			$this->password = html_entity_decode($this->password);
			$this->host = html_entity_decode($this->host);
			$this->port = html_entity_decode($this->port);
		}
		
		foreach($split as $key => $val) $split[$key] = html_entity_decode($val);
		
		if ($split) {
			$this->dbname = $split[0];
			array_shift($split);
		}
		if ($split) {
			$this->search_path = implode(',', $split);
		}
		
		if (!$this->dbname && !$this->username) 
			$this->parse_error = 'Invalid connection string. Database name or user name must be provided.';
	}
	
	/**
	 * Format the connection as a URL. Password is always excluded.
	 */
	function url($prefix=false) {
		$url = '';
		if ($this->username) $url .= $this->username . '@';
		if ($this->host) $url .= $this->host;
		if ($this->port) $url .= ':' . $this->port;
		if ($url) $url = '//' . $url . '/';
		if ($this->dbname) $url .= $this->dbname;
		if ($this->search_path) $url .= '/' . str_replace(',', '/', $this->search_path);
		if ($prefix) $url = 'psql:' . $url;
		return $url;
	}
	
	/**
	 * Return an array of file path/connection string associations for the session.
	 *
	 * Optionally add a new path/url association for the session.
	 */
	static function file_connections($set_path='', $set_url='') {
		$session_var = 'file_connections';
		
		if (!is_array(self::$file_connections)) {
			self::$file_connections = Session::variable($session_var, array());
		}
		if ($set_path) {
			if ($set_url) self::$file_connections[$set_path] = $set_url;
			else unset(self::$file_connections[$set_path]);
			Session::variable_set($session_var, self::$file_connections);
		}
		return self::$file_connections;
	}
	
	/**
	 * Prompt the user for the database connection.
	 */
	static function request_dialog($default='') {
		// if (!$default) $default = 'database';
		$prompt = '//user@host:port/database';
		$res = true;
		while ($res) {
			$conn = null;
			$res = CocoaDialog::request_text($prompt, $default, 'Database Connection');
			if (is_string($res)) {
				$conn = new Connection($res);
				if (!$conn->parse_error) $res = false;
				else {
					$res = true;
					$prompt = $conn->parse_error;
				}
			}
		}
		return $conn;
	}
	
	/**
	 * Return the connection to use for the file path.
	 */
	static function for_path($path, $ask=true, $confirm=null) {
		$file_conn = self::file_connections();
		$conn = null;
		if (!$path) {
			if ($confirm || $ask) Dialog::alert('The document must have an associated file path.');
		} else {
			$url = isset($file_conn[$path]) ? $file_conn[$path] : '';
			if (!$url) {
				$url = File::xattr($path, 'connection');
				if ($url && $confirm === null) $confirm = true; // confirm the user still wants this path the first time
			}
			if ($url && !$confirm) $conn = new Connection($url);
			elseif (($confirm || ($ask && !$url)) && $conn = self::request_dialog($url)) self::for_path_set($path, $conn);
		}
		return $conn;
	}
	
	/**
	 * Set the connection to use for the file.
	 */
	static function for_path_set($path, $conn_or_url) {
		$url = is_string($conn_or_url) ? $conn_or_url : $conn_or_url->url();
		self::file_connections($path, $url);
		File::xattr_set($path, 'connection', $url);
	}
	
	
	/**
	 * Execute the security command and return the result.
	 */
	function security_exec($command, $args=array()) {
		global $env;
		
		$cmd = 'security ' . $command;
		foreach ($args as $key => $val) {
			$cmd .= ' -' . $key;
			if ($val !== null) $cmd .= ' "' . $val . '"';
		}
		$cmd .= ' -r "psql"';
		$cmd .= ' -s "' . $this->host . '"';
		$user = $this->username ? $this->username : $env['USER'];
		$cmd .= ' -a "' . $user . '"';
		$path = $this->dbname ? $this->dbname : $user;
		$cmd .= ' -p "' . $path . '"';
		if ($this->port) $cmd .= ' -P "' . $this->port . '"';
		$cmd .= ' 2>&1 >/dev/null';
		$output = array();
		$res = exec($cmd, $output);
		return $output;
	}
	
	/**
	 * Store the connection information in the keychain.
	 */
	function save_password() {
		$args = array(
			'U' => null, // update
			'w' => $this->password,
			'l' => $this->url(true), // label
			);
		$this->security_exec('add-internet-password', $args);
	}
	
	/**
	 * Load the password from the keychain.
	 */
	function load_password() {
		$pass = '';
		$out = $this->security_exec('find-internet-password', array('g' => null)); // -g is show password option
		foreach ($out as $line) {
			if (preg_match('/^password: "(.*)"$/', $line, $match )) {
				$pass = $match[1];
				break;
			}
		}
		$this->password = $pass;
	}
	
	
	
}