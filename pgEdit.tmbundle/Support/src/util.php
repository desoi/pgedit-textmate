<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Common utilities for TextMate commands.
 */


// user settings to figure out...
$max_rows = 1000;


// Misc globals.
$out = '';

/**
 * Textmate exit codes.
 *
 * Exit codes control the output handler used; see TextMate.app/Contents/SharedSupport/Support/lib/bash_init.sh
 */
define('exit_discard', 200);
define('exit_replace_text', 201);
define('exit_replace_document', 202);
define('exit_insert_text', 203);
define('exit_insert_snippet', 204);
define('exit_show_html', 205);
define('exit_show_tool_tip', 206);
define('exit_create_new_document', 207);



/**
 * Regex defines.
 *
 * A digit cannot start an identifier and there are probably a few other exceptions not covered here.
 * See pg docs: sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
 */
define('pg_identifier_regex', '[\w]+|".*"');
define('pg_schema_object_regex','(([\w]+|".+")\.)?([\w]+|".+")');


/**
 * Autoload from classes as needed.
 */
function __autoload($class_name) {
	global $src;
	require_once($src . 'classes/' . $class_name . '.php');
	// require_once(dirname(__FILE__) . '/classes/' . $class_name . '.php');
}


/**
 * Return a URL in phpPgAdmin.
 */
function pg_admin_url($subpath = '') {
	global $env;
	
	static $base;
	if (!isset($base)) $base = trim($env['PGEDIT_ADMIN_URL'], ' /') . '/';
	
	return $base . $subpath;
}

/**
 * Determine if command input is from user selected text.
 */
function tm_text_is_selected() {
	global $env;
	return isset($env['TM_SELECTED_TEXT']);
}

$tm_line_offset = 0;

/**
 * Get or set the line offset of the tm input.
 */
function tm_line_offset($delta=0) {
	global $tm_line_offset;
	if ($delta) $tm_line_offset = (int)$delta + $tm_line_offset;
	return $tm_line_offset;
}


/**
 * Get tm standard input. Also adjusts the tm line offset based on the selection or current line.
 */
function tm_stdin($trim = true) {
	static $tm_stdin;
	global $env;
	
	if(!isset($tm_stdin)) {
		$stdin = fopen('php://stdin', 'r');
		$tm_stdin = '';
		while (!feof($stdin)) $tm_stdin .= fread($stdin, 8192);
		if ($trim) $tm_stdin = trim($tm_stdin);
		$line = isset($env['TM_INPUT_START_LINE']) ? $env['TM_INPUT_START_LINE'] : $env['TM_LINE_NUMBER'];
		tm_line_offset((int)$line - 1);
	}
	return $tm_stdin;
}


/**
 * Returns the connection for the current file, prompting if it does not exist.
 * 
 * Execution is halted if connection spec is not returned.
 */
function file_connection($file_path='') {
	global $env;
	
	$path = $file_path;
	
	if (!$path) $path = isset($env['TM_FILEPATH']) ? $env['TM_FILEPATH'] : '';
	$conn = Connection::for_path($path);
	if ($conn) return $conn;
	else exit(exit_discard);
}


/**
 * Standard psql execution with default output methods. Returns psql exit status.
 */
function psql_execute($sql, $connection=null) {	
	$single_statement = !tm_text_is_selected();
	$conn = $connection ? $connection : file_connection();
	
	if ($conn) {
		$obj = new PsqlProcess($conn, $sql, $single_statement);
		return $obj->exit_status;
	} else return -1;
}


/**
 * Show and error message and exit.
 */
function fatal_error($message, $details='') {
	if ($message) Dialog::alert($message, $details);
	exit(exit_discard);
}


