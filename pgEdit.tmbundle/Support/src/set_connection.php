<?php

// Copyright 2010-2011 by John DeSoi, Ph.D. All rights reserved. See help file license terms.

/**
 * Assoicate a connection with a file path.
 */

// some versions of PHP use only $_SERVER
$env = array_key_exists('TM_BUNDLE_SUPPORT', $_SERVER) ? $_SERVER : $_ENV;
$src = $env['TM_BUNDLE_SUPPORT'] . '/src/';
require($src . 'util.php');


Connection::for_path($env['TM_FILEPATH'], true, true);
exit(exit_discard);