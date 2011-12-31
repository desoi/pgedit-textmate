<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Execute a SQL line or the selected text.
 */

// some versions of PHP use only $_SERVER
$env = array_key_exists('TM_BUNDLE_SUPPORT', $_SERVER) ? $_SERVER : $_ENV;
$src = $env['TM_BUNDLE_SUPPORT'] . '/src/';
require($src . 'util.php');


$args = getopt('f:');

if (isset($args['f'])) {
	$file = $args['f'];
	$conn = file_connection($file);
	psql_execute('file://' . $file, $conn);
} else {
	$query = tm_stdin(); // if empty user likely tried single line exec with no selection or badly formed sql line
	if (!$query) echo '<p>No SQL statement or selected text found for execution.</p>';
	else psql_execute($query);
}
