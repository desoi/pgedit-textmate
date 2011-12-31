<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Class for using CocoaDialog class. 
 **/

class CocoaDialog
{

	public $stdin = null;
	public $stdout = null;
	public $stderr = null;
	
	private $process;
	
	
	/**
	 * Close the progress dialog.
	 */
	function close () {
		fclose($this->stdout);
		fclose($this->stderr);
		fclose($this->stdin);
		proc_close($this->process);
	}
	
	
	static function command ($type) {
		return 'CocoaDialog ' . $type;
	}
	
	/**
	 * Dialog to request a string from the user.
	 * 
	 * Returns FALSE if the user cancels, otherwise the string entered. 
	 **/
	static function request_text($prompt='', $default='', $title='', $default_button='OK', $alternate_button='Cancel', $secure=false) {
		$cmd = self::command('inputbox');
		$cmd .= " --text '$default'";
		$cmd .= " --title '$title'";
		if ($prompt) $cmd .= " --informative-text '$prompt'";
		if ($secure) $cmd .= ' --no-show';
		$buttons = array();
		if ($default_button) $buttons[] = $default_button;
		if ($alternate_button) $buttons[] = $alternate_button;
		if (!$buttons) $buttons[] = 'OK';
		foreach($buttons as $key => $val) {
			$index = $key + 1;
			$cmd .= " --button$index '$val'";
		}
		$output = array();
		exec($cmd, $output);
		$result = -1;
		if ($output[0] == 1) return $output[1];
		else return false;
	}
	
	
	/**
	 * Request a password.
	 */
	static function request_password($prompt='Password:', $title='') {
		return self::request_text($prompt, '', $title, 'OK', 'Cancel', true);
	}
	
	
	/**
	 * Progress dialog.
	 *
	 * Really just indeterminate version so far. Need some more methods to do updating for determinate version.
	 */
	static function progress($text, $title='', $indeterminate=true, $percent=false) {
		$dialog = new CocoaDialog();
		
		$cmd = self::command('progressbar');
		$cmd .= " --text '$text'";
		if ($title) $cmd .= " --title '$title'";
		if ($indeterminate) $cmd .= ' --indeterminate';
		if ($percent !== false) $cmd .= " --percent '$percent'";
		
		
		$descriptorspec = array(
		   0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
		   1 => array('pipe', 'w'),  // stdout is a pipe that the child will write to
		   2 => array('pipe', 'w')   // stderr
		);
		$pipes = array();
		
		$dialog->process = proc_open($cmd, $descriptorspec, $pipes, '/tmp');
		if (is_resource($dialog->process)) {
			$dialog->stdin = $pipes[0];
			$dialog->stdout = $pipes[1];
			$dialog->stderr = $pipes[2];
		}
		
		return $dialog;
	}
	
}