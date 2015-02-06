<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Execute psql in a process. 
 **/

class PsqlProcess
{
	public $process;
	public $exit_status = -1;
	public $stdin;
	public $stdout;
	public $stderr;
	public $single_statement;
	public $statement = '';
	public $connection;
	
	private $exec1 = false; // executing 1 line, not a select statement
	private $select1 = false; // executing 1 select statement
	private $file_exec = false; // executing entire file
	private $closed = false;
	private $status;
	private $conf = array();
	private $file_path = ''; // file path containing the source
	private $file_name = '';
	private $show_output = true;
	private $output = '';
	
	/**
	 * Constructor.
	 * 
	 * @param $connection
	 * Connection object.
	 * Also allows a few other options for partial execution:
	 * - search_path
	 *
	 * @param $input 
	 * A file name prefixed with 'file://' or text to execute.
	 * If empty string, the process is created and the caller must send the input and process the output.
	 *
	 * @param $single_statement
	 * If true, we are setting up to execute 1 SQL statement. 
	 *
	 * @param $show_output
	 * If false, output is not displayed and can be retrieved with the get_output() method.
	 */
	function __construct($connection, $input = '', $single_statement = false, $show_output = true) {
		global $env;
		$this->connection = $connection;
		$this->show_output = $show_output;
				
		$file_prefix = 'file://';
		$this->file_exec = substr($input, 0, strlen($file_prefix)) === $file_prefix;
		if ($this->file_exec) $this->file_path = substr($input, strlen($file_prefix));
		else $this->file_path = $input ? $env['TM_FILEPATH'] : '';
		if ($this->file_path) $this->file_name = basename($this->file_path);
		
		if ($this->single_statement = $single_statement && !$this->file_exec) {
			// assumes caller trimmed the input; \\x is for psql describe and other commands that output tables
			// $this->select1 = preg_match('/^(select |[\\]d[\S]*)/i', $input);
			if (substr($input, 0, 2) == '\\d') $this->select1 = $show_output; // psql describe command from user
			else $this->select1 = preg_match('/^(select|table|with)\s/i', $input);
			$this->exec1 = !$this->select1;
			$this->statement = $input;
		}
		
		$cmd = isset($env['PGEDIT_PSQL_PATH']) ? $env['PGEDIT_PSQL_PATH'] : 'psql';
		if ($this->file_exec) {
			$cmd .= ' --file "' . substr($input, strlen($file_prefix)) . '"';
		} else {
			$cmd .= ' --file -';
		}
		
		if ($this->exec1) $cmd .= ' --pset tuples_only=on';
		else $cmd .= ' --html';
		// if ($this->select1) $cmd .= ' --html';
		
		if (!$this->file_exec) { // setup for partial execution
			$cmd .= ' --no-psqlrc --set ON_ERROR_STOP=1';
			if ($connection->search_path) $this->conf[] = 'SET search_path TO ' . $connection->search_path . ';';
			if ($this->conf) { // setup so that we don't see the startup command results, but everything after
				$cmd .= ' --quiet';
				$this->conf[] = '\set QUIET off'; // have to turn quiet off after startup commands
			}
			if (!$this->single_statement) $this->conf[] = '\set ECHO all';
		}
		tm_line_offset(0-count($this->conf));
		
		if ($connection->host) $cmd .= ' --host ' . $connection->host;
		if ($connection->port) $cmd .= ' --port ' . $connection->port;
		if ($connection->dbname) $cmd .= ' --dbname ' . $connection->dbname;
		if ($connection->username) $cmd .= ' --username ' . $connection->username;
		
		if ($this->open_process($cmd, $input) && $input && $this->do_output()) { // retry if authentication failure
			while ($this->authenticate() && $this->open_process($cmd, $input)) $this->do_output();
		}
	}
	
	

	/**
	 * Destructor.
	 */
	function __destruct () {
		$this->close_process();
	}
	
	
	/**
	 * Start the psql process and setup the streams; returns true on success.
	 */
	function open_process ($cmd, $input) {
		if ($this->connection->password) $cmd = 'env PGPASSWORD="' .  $this->connection->password . '" ' . $cmd;
		
		$descriptorspec = array(
		   0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
		   1 => array('pipe', 'w'),  // stdout is a pipe that the child will write to
		   2 => array('pipe', 'w')   // stderr
		);
		$pipes = array();
		$this->exit_status = -1;
		$this->process = proc_open($cmd, $descriptorspec, $pipes, '/tmp');
		if (is_resource($this->process)) {
			$this->stdin = $pipes[0];
			$this->stdout = $pipes[1];
			$this->stderr = $pipes[2];
			if ($this->conf) fwrite($this->stdin, implode("\n", $this->conf) . "\n");
			if ($input && !$this->file_exec) fwrite($this->stdin, $input);
			if ($input) fclose($this->stdin);
			return true;
		} else return false;
	}
	
	
	/**
	 * Close the process if not already closed.
	 */
	function close_process () {
		if (!$this->closed & is_resource($this->process)) {
			$this->closed = true;
			fclose($this->stdout);
			fclose($this->stderr);
			if ($this->exit_status < 0) $this->exit_status = proc_close($this->process);
			else proc_close($this->process);
		}
	}
	
	
	/**
	 * Update the process status; returns the current exit status.
	 */
	function check_status() {
		$this->status = proc_get_status($this->process);
		if ($this->exit_status < 0 && !$this->status['running']) $this->exit_status = $this->status['exitcode'];
		return $this->exit_status;
	}
	
	/**
	 * Handle password authentication and store passwords in the keychain.
	 */
	function authenticate() {
		if (!$this->connection->password) {
			$this->connection->load_password();
			if ($this->connection->password) return true; // had one, so try it next
		}
		
		$pass = CocoaDialog::request_password('Password for ' . $this->connection->url(), 'psql');
		if ($pass === false) exit(exit_discard);
		else {
			$this->connection->password = $pass;
			$this->connection->save_password();
			return true;
		}
	}
	
	
	/**
	 * Return all accumulated output if show_output was off.
	 */
	function get_output() {
		return $this->show_output ? false : $this->output;
	}



	/**
	 * Process output from psql execution.
	 */
	function do_output() {
		global $src;
		
		if (!$this->show_output) ob_start(); // buffer all output
		
		$html = !$this->exec1;
		$done = false;
		$pw_error = false;
		$line_offset = tm_line_offset();
		
		if ($html) {
			require($src . 'output_html_head.php');
			echo "\n<body>\n";
			flush();
		}
		
		while (!$done) {
			$read = array($this->stdout, $this->stderr);
			$write = array();
			$except = array();
			$ready = stream_select($read, $write, $except, 2);
			
			if ($this->check_status() >= 0) $done = true;
			
			foreach ($read as $stream) {
				if ($stream == $this->stdout) {
					while ($line = fgets($stream)) {
						echo $line;
						if ($html && $line = "</tr>\n") flush();
					}
				}
				elseif ($stream == $this->stderr) {
					while ($line = fgets($stream)) {
						if (stripos($line, 'password') !== false) $pw_error = true;
						else {
							$prefix = '';
							if (preg_match('/^psql:.+:([0-9]+):\s+/', $line, $match)) {
								$line = substr($line, strlen($match[0]));
								if ($html)  {
									$line_ref = $match[1] + $line_offset;
									$prefix = "<a href=\"txmt://open/?url=file://$this->file_path&line=$line_ref\">$this->file_name:$line_ref</a>:  ";
								}
							}
							if ($html) echo "\n<br />" . $prefix . htmlspecialchars($line);
							else echo $line;
							flush();
						}
					}
				}
			}
			
			
		}
		
		$this->close_process();
		if ($html) echo "\n</body>\n</html>\n";
		
		$pw_error = $this->exit_status == 2 && $pw_error;
		
		if (!$this->show_output) {
			if (!$pw_error) $this->output .= ob_get_contents();
			ob_end_clean();
		}
		
		// if authentication failed return true; caller can retry after requesting password
		if ($pw_error) return true;
		elseif (!$this->show_output) return false;
		else exit($html ? exit_show_html : exit_show_tool_tip);
		
	}
	
}