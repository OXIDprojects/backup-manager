<?php
define('SMARTBACKUP_VERSION', '1.1.0');

@ini_set('log_errors', 1);
@ini_set('display_errors', 0);
@error_reporting(E_ALL | E_NOTICE);
@ini_set('error_log', dirname(__FILE__).'/../logs/php_errors.txt');

// Throw exceptions instead of errors
set_error_handler(create_function('$a, $b, $c, $d', 'throw new ErrorException($b, 0, $a, $c, $d);'), E_ERROR | E_RECOVERABLE_ERROR | E_USER_ERROR);

// Log uncaught exceptions
function exception_handler($exception) {
	error_log($exception->__toString());
}
set_exception_handler('exception_handler');

// Include and initialize Storm Framework
require_once dirname(__FILE__).'/framework.php';
Storm::Init();


define('STORM_LOADED', true);
define('IS_DEBUG', true);

require_once dirname(__FILE__).'/../settings.php';
//date_default_timezone_set($settings['timezone']);

Storm::LoadComponent("common");
Storm::LoadComponent("backups");
Storm::LoadComponent("archives");
Storm::LoadComponent("dropbox");
Storm::LoadComponent("quick");
Storm::LoadComponent("privatekeys");

VirtualPages::SetDefault("common");

if (isset($_GET['path']))
	VirtualPages::SetPath($_GET['path']);
else
	VirtualPages::SetPath();

require_once "Dropbox/autoload.php";

try
{
	// Let VirtualPages call the appropriate component/method based on URL and configuration
	VirtualPages::ProcessPath();
}
catch ( Exception $e )
{
	header('HTTP/1.1 500 Internal Server Error');
	throw $e;

//	if ( IS_DEBUG )
//		echo $e;
}
?>