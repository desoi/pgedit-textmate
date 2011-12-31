<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Class for using TM Dialog command. 
 **/

class Dialog
{

	static function command ($type) {
		global $env;
		return $env['DIALOG'] . ' ' . $type;
	}
	
	
	/**
	 * Wrapper for alert dialog.
	 */
	static function alert($title, $body='', $default_button='OK', $warning_style=false, $alternate_button='', $other_button='') {
		$cmd = self::command('alert');
		$cmd .= " --title '$title'";
		if ($body) $cmd .= " --body '$body'";
		if ($warning_style) $cmd .= ' --alertStyle warning';
		$buttons = array();
		if ($default_button) $buttons[] = $default_button;
		if ($alternate_button) $buttons[] = $alternate_button;
		if ($other_button) $buttons[] = $other_button;
		if (!$buttons) $buttons[] = 'OK';
		foreach($buttons as $key => $val) {
			$index = $key + 1;
			$cmd .= " --button$index '$val'";
		}
		exec($cmd, $output);
		$result = -1;
		foreach($output as $val) {
			if (strpos($val, '<integer>') !== false) { // output is xml; should only be one integer value in the result
				$result = (int)strip_tags($val);
				break;
			}
		}
		if (array_key_exists($result, $buttons)) return $buttons[$result];
		else echo('Failed to find button in dialog result.');
	}
	
	
	static function nib($nib_name, $center=true) {
		global $env;
		$path = $env['TM_BUNDLE_SUPPORT'] . '/nib/' . $nib_name;
		
		$cmd = self::command('nib');
		$cmd .= " --load '$path'";
		if ($center) $cmd .= ' --center';
		
		exec($cmd, $output);
		if ($output) return $output[0]; // should be the token
		else return false;
	}
	
	// need docs -- $items should be key/val
	static function menu($items) {
		$cmd = self::command('menu');
		
		$sep_count = 0;
		$menu = array();
		foreach($items as $item => $value) {
			if (substr($item, 0,  1) == '-') {
				$sep_count++;
				$menu[] = '{separator = ' . $sep_count . ';}';
			} else $menu[] = '{title = "' . $item . '";}';
		}
		$cmd .= ' --items ' . "'(" . implode(',', $menu) . ")'";
		
		exec($cmd, $output);
		$result = false;
		if ($output) {
			$mi = '';
			foreach($output as $val) {
				if (strpos($val, '<string>') !== false) { // output is xml; should only be one string value in the result
					$mi = trim(strip_tags($val));
					break;
				}
			}
			if (array_key_exists($mi, $items)) $result = $items[$mi];
		}
		return $result;
	}
	
	
	/**
	 * Popup menu of completions. Does not seem to be any way to alter the behavior of how this works -- it automatically
	 * does the insertion and exits.
	 *
	 * popup usage:
	 * "$DIALOG" popup --suggestions '( { display = law; }, { display = laws; insert = "(${1:hello}, ${2:again})"; } )'
	 * 
	 * properties:
	 *  display → the string displayed in the pop-up
	 *  match   → the string matched against user input
	 *  insert  → the string inserted after the user presses return
	 */
	static function popup($items, $start_pos=0) {
		$cmd = self::command('popup');
		
		$menu = array();
		if (!$start_pos) 
			foreach($items as $item) $menu[] = '{display = "' . $item . '";}';
		else {
			foreach($items as $item) {
				$menu[] = '{display = "' . $item . '"; match = "' .  substr($item, $start_pos) . '";}';
			}
		}
		$cmd .= ' --suggestions ' . "'(" . implode(',', $menu) . ")'";
		
		exec($cmd, $output);
	}
	
}