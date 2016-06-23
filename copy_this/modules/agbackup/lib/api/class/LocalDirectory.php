<?php

class LocalDirectory implements IDirectory
{
	private $cd;

	public function __construct($dir = null)
	{
		if ($dir === null)
		{
			$this->cd = '';
		}
		else
		{
			$this->cd = str_replace('\\', '/', realpath($dir));

			if (!$this->cd || !is_dir($this->cd))
			{
				throw new NonExistantDirectoryException($this->cd);
			}
		}
	}

	public static function GetTopReadableParent($of)
	{
		$last = null;
		$dir = realpath($of);

		while (is_readable($dir) && $last != realpath($dir))
		{
			$last = realpath($dir);
			// Realpath can be forbidden if open_basedir restrictions are in effect
			$dir = str_replace('\\', '/', $dir . '/../');
		}

		return $last;
	}

	private function normalizePath($cd)
	{
		$parts = explode('/', trim(str_replace('\\', '/', $cd), '/\\'));

		if (strpos($parts[0], ':') > 0)
		{
			unset($parts[0]);
			$cd = implode($parts, '/');
		}

		return trim($cd, '/\\');
	}

	public function GetPath($file = '')
	{
		$file = str_replace('\\', '/', $file);

		// Fix '/C:/[...]'
		if ($this->normalizePath($file) == trim($file, '/\\'))
		{
			$file = realpath($this->cd .'/' . $file);
		}
		else
		{
			$file = realpath($file);
		}

		return str_replace('\\', '/', $file);
	}

	public function GetSize($path)
	{
		return filesize($this->GetPath($path));
	}

	public function OpenDir($name)
	{
		return new LocalDirectory(($this->cd ? $this->cd .'/' : ''). $name);
	}

	public function GetDirs($path = '')
	{
		$parent = $this->GetPath($path);
		$dir = @opendir($parent);

		if ($dir === false)
		{
			throw new NonReadableDirectoryException($parent);
		}

		$result = array();
		while (($name = readdir($dir)) !== false)
		{
			if ($name == '.' || $name == '..')
			{
				continue;
			}

			if (is_dir($parent.'/'.$name))
			{
				$result[] = $name;
			}
		}

		closedir($dir);
                
                sort($result);

		return $result;
	}

	public function GetFiles($path = '')
	{
		$parent = $this->GetPath($path);
		$dir = @opendir($parent);

		if ($dir === false)
		{
			throw new NonReadableDirectoryException($parent);
		}

		$result = array();
		while (($name = readdir($dir)) !== false)
		{
			if ($name == '.' || $name == '..')
			{
				continue;
			}

			if (is_file($parent.'/'.$name))
			{
				$result[] = $name;
			}
		}

		closedir($dir);

		return $result;
	}

	public function IsReadable($dir)
	{
		return is_readable($this->GetPath($dir));
	}

	public function IsWritable($dir)
	{
		return is_writable($this->GetPath($dir));
	}

	public function HasSubdirs($dir = '')
	{
		$parent = $this->GetPath($dir);
		$dir = @opendir($parent);

		if ($dir === false)
		{
			throw new NonReadableDirectoryException($parent);
		}

		while (($name = readdir($dir)) !== false)
		{
			if ($name == '.' || $name == '..')
			{
				continue;
			}

			if (is_dir($parent.'/'.$name))
			{
				return true;
			}
		}

		closedir($dir);

		return false;
	}

	public function HasFiles($dir = '')
	{
		$parent = $this->GetPath($dir);
		$dir = @opendir($parent);

		if ($dir === false)
		{
			throw new NonReadableDirectoryException($parent);
		}

		while (($name = readdir($dir)) !== false)
		{
			if ($name == '.' || $name == '..')
			{
				continue;
			}

			if (is_file($parent.'/'.$name))
			{
				return true;
			}
		}

		closedir($dir);

		return false;
	}

	public function GetProtocol()
	{
		return 'local';
	}

	public function AddFile($source, $dest, $mode = 0777)
	{
		return copy($source, $this->GetPath($dest)) && chmod($this->GetPath($dest), $mode);
	}

	public function RemoveFile($path)
	{
		return unlink($this->GetPath($path));
	}

	public function AddDir($path, $mode = 0777)
	{
		return mkdir($this->GetPath($path), $mode, true);
	}

	public function RemoveDir($path)
	{
		return rmdir($this->GetPath($path));
	}

	public function GetFile($path, $to)
	{
		return copy($path, $to);
	}
}