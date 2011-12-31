<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Documentation features. 
 */

// some versions of PHP use only $_SERVER
$env = array_key_exists('TM_BUNDLE_SUPPORT', $_SERVER) ? $_SERVER : $_ENV;
$src = $env['TM_BUNDLE_SUPPORT'] . '/src/';
require($src . 'util.php');

$args = getopt('d:');
$doc = $args['d'];

// allow override with environment variable
$base = isset($env['PGEDIT_POSTGRES_DOCS_URL']) ? 
	$env['PGEDIT_POSTGRES_DOCS_URL'] : 'http://www.postgresql.org/docs/current/interactive/';

$selection = isset($env['TM_SELECTED_TEXT']) ? $env['TM_SELECTED_TEXT'] : '';

$path = '';
switch ($doc) {
	case 'sql-commands.html':
		$context = $selection ? $selection : $env['TM_CURRENT_LINE'];
		$path = Help::sql_command($context);
		if (!$path) $path = $doc; // show all
		break;
	case 'sql-language':
		$topics = array(
			'SQL Syntax' => 'sql-syntax.html',
			'Data Definition' => 'ddl.html',
			'Data Manipulation' => 'dml.html',
			'Queries' => 'queries.html',
			'Data Types' => 'datatype.html',
			'Functions and Operators' => 'functions.html',
			'Type Conversion' => 'typeconv.html',
			'Indexes' => 'indexes.html',
			'Full Text Search' => 'textsearch.html',
			'Concurrency Control' => 'mvcc.html',
			'Performance Tips' => 'performance-tips.html',
			);
		$path = Dialog::menu($topics);
		break;
	default:
		$path = $doc;
	
}

if (!$path) exit(exit_discard);
else {
	$url = $base . $path;
	echo "<meta http-equiv='Refresh' content='0;URL=$url'>";
	exit(exit_show_html);
}
