<?php

class ZipDirectory implements IDirectory
{
	private $zip, $path, $closed = false;

	public function __construct($path, $mode = 0)
	{
		$this->zip = new ZipArchivePlus();
		$r = $this->zip->open($path, $mode);

		if ($r !== true)
			throw new Exception("Error creating or opening zip file: " . $r . ", " . $path . ", " . $mode);
			
		$this->path = $path;
	}

	public function flush()
	{
		$this->closed = true;

		if (!$this->zip->close()) {
			throw new Exception("Error while saving the zip file @zip->close");
		}
	}

	public function isClosed()
	{
		return $this->closed;
	}

	public function GetPath($file = '')
	{
		return 'zip://' . $this->path . ($file == '' ? '' : '#' . $file);
	}

	public function GetSize($path)
	{
		$stats = $this->zip->statName($path);

		return $stats['size'];
	}

	private function count_slashes($str)
	{
		$n = 0;
		for ($i = 0; $i < strlen($str); $i++)
		{ 
			if ($str[$i] == '/')
				$n++;
		}

		return $n;
	}

	private function is_direct_child($dir, $subdir)
	{
		$dir = rtrim($dir, '/');
		$subdir = rtrim($subdir, '/');

		if ($dir == '')
			$dir_count = -1;
		else
			$dir_count = $this->count_slashes($dir);

		if ($dir_count == $this->count_slashes($subdir) - 1 && ($dir == '' || strpos($subdir, $dir) === 0))
			return true;

		return false;
	}

	private function is_child($dir, $subdir)
	{
		$dir = rtrim($dir, '/');
		$subdir = rtrim($subdir, '/');

		if (strlen($subdir) > strlen($dir) && ($dir == '' || strpos($subdir, $dir) === 0))
			return true;

		return false;
	}

	public function GetDirs($dir = '')
	{
		$result = array();
		$num = $this->zip->numFiles;
		for ($i = 0; $i < $num; $i++)
		{
			$entity = $this->zip->statIndex($i);

			if ($entity['size'] == 0 && substr($entity['name'], -1, 1) == '/' && $this->is_direct_child($dir, $entity['name']))
			{
				$result[] = basename($entity['name']);
			}
		}

		return $result;
	}
	public function GetFiles($dir = '')
	{
		$result = array();
		$num = $this->zip->numFiles;
		for ($i = 0; $i < $num; $i++)
		{
			$entity = $this->zip->statIndex($i);

			if ($entity['size'] !== 0 && substr($entity['name'], -1, 1) != '/' && $this->is_direct_child($dir, $entity['name']))
			{
				$result[] = basename($entity['name']);
			}
		}

		return $result;
	}

	public function IsReadable($path)
	{
		return true;
	}
	public function IsWritable($path)
	{
		return true;
	}
	
	public function HasSubdirs($dir = '')
	{
		$num = $this->zip->numFiles;
		for ($i = 0; $i < $num; $i++)
		{
			$entity = $this->zip->statIndex($i);

			if ($entity['size'] == 0 && substr($entity['name'], -1, 1) == '/' && $this->is_direct_child($dir, $entity['name']))
			{
				return true;
			}
		}

		return false;
	}
	public function HasFiles($dir = '')
	{
		$num = $this->zip->numFiles;
		for ($i = 0; $i < $num; $i++)
		{
			$entity = $this->zip->statIndex($i);

			if ($entity['size'] !== 0 && substr($entity['name'], -1, 1) != '/' && $this->is_direct_child($dir, $entity['name']))
			{
				return true;
			}
		}

		return false;
	}

	public function GetProtocol()
	{
		return 'zip';
	}

	/* File/dir operations */
	public function AddFile($from, $to, $mode = 0777)
	{
		return $this->zip->addFile($from, $to);
	}
	public function RemoveFile($path)
	{
		return $this->zip->deleteName($path);
	}

	public function AddDir($path, $mode = 0777)
	{
		return $this->zip->addEmptyDir($path);
	}
	public function RemoveDir($path)
	{
		$num = $this->zip->numFiles;
		for ($i = 0; $i < $num; $i++)
		{
			$entity = $this->zip->statIndex($i);

			if ($this->is_child($path, $entity['name']) || trim($path, '/') == trim($entity['name'], '/') )
			{
				$this->zip->deleteIndex($entity['index']);
			}
		}
	}

	public function GetFile($path, $to)
	{
		throw new Exception("Not yet implemented");
	}
}