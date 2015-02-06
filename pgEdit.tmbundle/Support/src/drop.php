<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Formulate DROP command from the CREATE statement.
 */

// some versions of PHP use only $_SERVER
$env = array_key_exists('TM_BUNDLE_SUPPORT', $_SERVER) ? $_SERVER : $_ENV;
$src = $env['TM_BUNDLE_SUPPORT'] . '/src/';
require($src . 'util.php');

$conn = file_connection();
$stmt = tm_stdin();

$schema_object_regex = pg_schema_object_regex;

// Update this for new versions by paging through the CREATE commands in the postgres documentation.
$types = 'AGGREGATE|CAST|CONSTRAINT\s+TRIGGER|CONVERSION|DATABASE|DOMAIN|FOREIGN\s+DATA\s+WRAPPER|FUNCTION|GROUP|INDEX|LANGUAGE|OPERATOR|OPERATOR\s+CLASS|OPERATOR\s+FAMILY|ROLE|RULE|SCHEMA|SEQUENCE|SERVER|TABLE|TABLESPACE|TEXT\s+SEARCH\s+CONFIGURATION|TEXT\s+SEARCH\s+DICTIONARY|TEXT\s+SEARCH\s+PARSER|TEXT\s+SEARCH\s+TEMPLATE|TRIGGER|TYPE|USER|USER\s+MAPPING|VIEW';
$keys = 'AUTHORIZATION|CONCURRENTLY|DEFAULT|GLOBAL|OR|LOCAL|PROCEDURAL|REPLACE|TEMPORARY|TEMP|TRUSTED|UNIQUE|UNLOGGED';
$regex = "/^CREATE\s+(?:(?:$keys)\s+)*($types)\s+(\(|$schema_object_regex)/i"; // last capture is open paren or name (cast has no name)
$match = array();
if (!preg_match($regex, $stmt, $match)) {
	$cmd = CocoaDialog::request_text('Enter SQL Statement', 'DROP ', 'Execute SQL', 'Execute');
	if ($cmd === false) exit(exit_discard);
	else psql_execute($cmd, $conn);
} else {
	$type = strtoupper($match[1]);
	$name = $match[2]; // regex above still needs lots of work -- can have schema and double quotes
	if ($name == '(') $name = ''; // match on cast -- no name, just open paren
	$cmd = 'DROP ' . $type . ' ' . $name;
	
	switch ($type) {
		case 'FUNCTION': // functions: need to remove DEFAULTs from parameter list
		case 'CAST':
			// one of these keys must be after the param list; we'll look for it rather than parsing the parameter list
			if ($type == 'CAST') $keys = 'WITH|WITHOUT';
			else $keys = 'RETURNS|WINDOW|IMMUTABLE|STABLE|VOLATILE|CALLED|STRICT|EXTERNAL|SECURITY|COST|ROWS|SET|AS|WITH';
			$regex = "/(\([^).]*\))\s*\b($keys)\b/ims";
			if (preg_match($regex, $stmt, $match)) $cmd .= $match[1];
			break;
		case 'TRIGGER':
			$regex = "/\s+(?:ON)\s+($schema_object_regex)/im";
			if (preg_match($regex, $stmt, $match)) $cmd .= ' ON ' . $match[1];
			break;
	}
	
	$result = Dialog::alert('Execute DROP Statement?', $cmd . ';', 'Drop', true, 'Cancel', 'Cascade');
	switch ($result) {
		case 'Cascade':
			$cmd .= ' CASCADE';
		case 'Drop':
			$cmd .= ';';
			psql_execute($cmd, $conn);
			break;
		default:
			exit(exit_discard);
	}
}