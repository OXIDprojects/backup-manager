<?php

class LoginException extends Exception
{
	public function __construct($host, $user)
	{
		parent::__construct("Couldn't login to " . $host . " with " . $user);
	}
}