<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Interface to manage some state variables for the current TM Session. 
 **/


class Session
{
	private static $vars;
	private static $path;
	
	/**
	 * Destructor.
	 * 
	 * We create one instance when the class is first used. File is written at the
	 * end of the PHP session when the instance is killed.
	 */
	function __destruct () {
		self::save_state();
	}
	
	/**
	 * Return the file path of the session file.
	 *
	 * Currently, this appends the TM PID which could theoretically be the same for a second launch.
	 * Maybe revisit later, but should be good enough for now assuming temp files get cleaned up.
	 */
	private static function path () {
		if (!isset(self::$path)) {
			self::$path = '/tmp/com.pgedit.session.' . posix_getppid();
		}
		return self::$path;
	}
	
	/**
	 * Save the state of the session variables.
	 */
	private static function save_state() {
		if (isset(self::$vars)) {
			file_put_contents(self::path(), serialize(self::$vars));
		}
	}
	
	/**
	 * Load the state of the session variables.
	 */
	private static function load_state () {
		static $session_instance;
		
		if (!isset(self::$vars)) {
			$session_instance = new Session();
			$path = self::path();
			$vars = array();
			if (file_exists($path)) {
				$content = file_get_contents($path);
				if (is_string($content)) $vars = unserialize($content);
				if (!is_array($vars)) $vars = array();
			}
			self::$vars = $vars;
		}
	}
	
	/**
	 * Set a session variable value.
	 */
	static function variable_set($key, $value) {
		self::load_state();
		self::$vars[$key] = $value;
	}
	
	
	/**
	 * Get a session variable value.
	 */
	static function variable($key, $default=null) {
		self::load_state();
		return isset(self::$vars[$key]) ? self::$vars[$key] : $default; 
	}
	
	
}