<?php

class NonExistantDirectoryException extends Exception
{
	public function __construct($path)
	{
		parent::__construct("The directory doesn't exist: " . $path);
	}
}