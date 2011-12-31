<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Support for online help and documentation. 
 * 
 * To rebuild the command index and the regex used to identify statement starts, update sql-commands.html
 * and then call the help command (press F1 on a SQL line). This also writes the regex used to identify 
 * statement lines in data/statement-start-regex.txt.
 **/

class Help
{	
	/**
	 * Return a path in the data folder.
	 */
	private static function data_path ($file_name) {
		global $src;
		return $src . '/data/' . $file_name;
	}
	
	/**
	 * Build index for SQL command help from HTML document.
	 */
	private static function parse_sql_commands() {
		$src = self::data_path('sql-commands.html');
		$dest = self::data_path('sql-commands.php');
		
		$src_stamp = filemtime($src);
		$dest_stamp = filemtime($dest);
		
		$res = array();
		
		// dest does not exist or needs updating because source is newer
		if ($src_stamp && (!$dest_stamp || ($src_stamp > $dest_stamp))) {
			$doc = file_get_contents($src);
			preg_match_all('/<dt\s*><a\s*href="(.+)"\s*>(.*)<\/a\s*>.*<\/dt\s*>/im', $doc, $match);
			$files = $match[1];
			$names = $match[2];
			if (is_array($files) && is_array($names)) {
				$commands = array();
				$index = array();
				foreach($names as $key => $cmd) {
					$commands[$cmd] = $files[$key];
					$words = explode(' ', $cmd);
					$word1 = $words[0];
					array_shift($words);
					$index[$word1][] = $words;
				}
				
				// These are not listed separately in the docs.
				$commands['TABLE'] = $commands['SELECT'];
				$commands['WITH'] = $commands['SELECT'];
				$index['TABLE'][] = array();
				$index['WITH'][] = array();
				
				$res['commands'] = $commands;
				$res['index'] = $index;
				file_put_contents($dest, serialize($res));
				

				// Build begin regex of words that can start a SQL statement.
				// Used in grammar to identify meta.statement.pgsql.
				$start_words = array_keys($index);
				sort($start_words);
				$start_words = array_map('strtolower', $start_words);
				$regex = "'(?i)^(" . implode('|', $start_words) . ")';";
				file_put_contents(self::data_path('statement-start-regex.txt'), $regex);				
			}
		} 
		elseif ($dest_stamp) $res = unserialize(file_get_contents($dest));
		
		if (!$res) fatal_error('Failed to parse SQL commands from source file.', $src);
		else return $res;
	}
	
	
	/**
	 * Try to figure out what SQL command documentation to show for the context.
	 */
	static function sql_command($context) {
		$context = strtoupper(trim($context));
		$context = rtrim($context, ';');
		if (!$context) return '';
		
		$data = self::parse_sql_commands();
		$index = $data['index'];
		
		$find = '';
		$context = explode(' ', $context);
		$word1 = array_shift($context);
		
		if (array_key_exists($word1, $index)) {
			$rest = $index[$word1];
			$find = $word1;
			if ($rest[0] || count($rest) > 1) { // if empty array, only 1 word
				while($context && $rest) {
					$matches = array();
					$word = array_shift($context);
					foreach ($rest as $key => $val) {
						if (!$val) unset($rest[$key]); // all input used
						elseif ($word == $val[0]) $matches[] = $key;
					}
					if ($matches) {
						$find .= ' ' . $rest[$matches[0]][0];
						foreach ($rest as $key => $val) {
							if (in_array($key, $matches)) array_shift($rest[$key]);
							else unset($rest[$key]);
						}
					}
									
				}
			}
		}
		
		$commands = $data['commands'];
		if ($find && array_key_exists($find, $commands)) return $commands[$find];
		else return '';
	}
	
}