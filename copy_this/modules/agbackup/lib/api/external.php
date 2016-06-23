<?php
if (!defined("STORM_LOADED"))
{

	// Load Storm Framework
	require_once dirname(__FILE__).'/framework.php';
	Storm::Init();

	// For external scripts
	define("STORM_LOADED", true);


	require_once "Dropbox/autoload.php";

}
?>