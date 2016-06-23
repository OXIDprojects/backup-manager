<?php

class NonReadableDirectoryException extends Exception
{
	public function __construct($path)
	{
		parent::__construct("The directory isn't readable: " . $path);
	}
}