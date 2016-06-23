<?php

//
// Please do not modify these four lines of code
//
if (!defined('STORM_LOADED'))
{
	header('HTTP/1.1 404 Not Found', true, 404);
	exit;
}

include( realpath(dirname(__FILE__)) . '/../passwort.php');


//
// Modify the settings below
//
$settings = array(
    'password' => $passwort,
    'update_checks' => false
);