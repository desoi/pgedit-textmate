<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * File utilities.
 **/


class File
{
	
	/**
	 * Run xattr command and return the result.
	 */
	private static function xattr_exec($file, $flags='', $attribute='', $value=null) {
		$att = $attribute ? 'com.pgedit.' . $attribute : '';
		$cmd = "xattr $flags $att";
		if ($value) $cmd .= ' ' . escapeshellarg($value);
		$cmd .= ' ' . escapeshellarg($file);
		$cmd .= ' 2> /dev/null'; // ignore error message if getting an attribute that does not exist
		$res = exec($cmd, $output);
		if ($output) return $output[0];
		else return null;
	}
	
	
	/**
	 * Get file extended attribute. 
	 */
	static function xattr($file, $attribute) {
		return self::xattr_exec($file, '-p', $attribute);
	}
	
	/**
	 * Set file extended attribute. 
	 */
	static function xattr_set($file, $attribute, $value) {
		return self::xattr_exec($file, '-w', $attribute, $value);
	}
	
}