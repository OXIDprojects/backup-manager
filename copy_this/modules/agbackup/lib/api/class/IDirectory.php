<?php

interface IDirectory
{
	function GetPath($file = '');

	function GetDirs($dir = '');
	function GetFiles($dir = '');

	function GetSize($path);

	function IsReadable($path);
	function IsWritable($path);
	
	function HasSubdirs($dir = '');
	function HasFiles($dir = '');

	function GetProtocol();

	/* File/dir operations */
	function AddFile($from, $to, $mode = 0777);
	function RemoveFile($path);

	function AddDir($path, $mode = 0777);
	function RemoveDir($path);

	function GetFile($path, $to);
}