<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/phpseclib');

require_once 'Net/SSH2.php';
require_once 'Net/SFTP.php';

// Needed for public key authentication
require_once 'Crypt/RSA.php';