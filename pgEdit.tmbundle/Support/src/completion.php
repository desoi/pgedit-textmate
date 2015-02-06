<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * PostgreSQL completion support.
 */

$env = array_key_exists('TM_BUNDLE_SUPPORT', $_SERVER) ? $_SERVER : $_ENV;

$line = $env['TM_CURRENT_LINE'];
$pos = $env['TM_LINE_INDEX'];

$res = '';

// Indent if there is nothing but whitespace in front of the cursor.
if (!$pos || trim(substr($line, 0, $pos)) == '') {
	if ($env['TM_SOFT_TABS'] !== 'YES') $res = "\t";
	elseif ($size = (int)$env['TM_TAB_SIZE']) $res = str_repeat(' ', $size);
	else $res = "\t";
} else {
	$src = $env['TM_BUNDLE_SUPPORT'] . '/src/';
	require($src . 'util.php');
	
	
	$word = $env['TM_CURRENT_WORD'];
	$conn = file_connection();
	$cmd = "\\dd $word*\n\\df $word*\n\\dtv $word*"; // object, functions, tables/views; dd only shows things with comments
	$psql = new PsqlProcess($conn, $cmd, true, false); 
	$out = $psql->get_output(); $res = $out;
	$matches = array();
	// all must return a table with schema and name as the first two columns
	if (preg_match_all('/^[^|]*\| ([^|]*\S) +\|.*$/im', $out, $pmatch)) $matches = array_unique($pmatch[1]);
	$size = count($matches);
	if ($size == 1) $res = substr(reset($matches), strlen($word));
	elseif ($size > 1) {
		$res = Dialog::popup($matches, strlen($word));
		if (!$res) exit(exit_discard);
	}
}

if ($res) echo $res;
else {
	echo 'No completion found.';
	exit(exit_show_tool_tip);
}
