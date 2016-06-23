<?php

class ConnectionException extends Exception
{
	public function __construct($host)
	{
		parent::__construct("Can't connect to host: " . $host);
	}
}