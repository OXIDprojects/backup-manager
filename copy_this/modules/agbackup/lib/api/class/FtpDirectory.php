<?php

class FtpDirectory implements IDirectory
{
	private $ftp, $cd;
	private $host, $user, $pass, $dir, $port;

	public static function ParseUrl($url)
	{
		if (!preg_match('/^(?:ftp:\/\/)?(([^:]*)(:(.*))?)?@(.*?)(:([0-9]+))?(\/.*)?$/i', $url, $match))
		{
			throw new ConnectionException("Unknown host. Invalid url");
		}

		$host = $match[5];
		$user = $match[2];
		$pass = $match[4];
		$dir  = $match[8];
		$port = $match[7];

		if ($dir == '')
			$dir = '/';
		if ($port == '')
			$port = 21;

		return array(
			'host' => $host,
			'user' => rawurldecode($user),
			'pass' => rawurldecode($pass),
			'dir' => $dir,
			'port' => $port
		);
	}

	public function __construct($host, $user, $pass, $dir = '/', $port = 21)
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->dir = $dir;
		$this->port = $port;

		$this->ftp = @ftp_connect($host, $port);
		$this->cd  = $dir;

		if ($this->ftp === false)
		{
			throw new ConnectionException($host);
		}

		$login = @ftp_login($this->ftp, $user, $pass);
		if ($login === false)
		{
			throw new LoginException($host, $user);
		}

		if (@ftp_pasv($this->ftp, true) === false)
		{
			throw new ConnectionException($host);
		}

		$this->readable = @ftp_chdir($this->ftp, $this->cd);
	}

	public function __destruct()
	{
		@ftp_close($this->ftp);
	}

	public function GetLocalPath($path)
	{
		return rtrim($this->cd, '/') .'/'. trim($path, '/');
	}

	public function GetPath($path = '')
	{
		return 'ftp://'. ($this->user != '' ? rawurlencode($this->user) . ($this->pass != '' ? ':'. rawurlencode($this->pass) : '') : '') .'@'. $this->host . ($this->port != 21 ? ':'. $this->port : '') .'/'. preg_replace("/^\//", "", $this->GetLocalPath($path));
	}

	public function GetSize($path)
	{
		return ftp_size($this->ftp, $this->GetLocalPath($path));
	}

	public function OpenDir($dir)
	{
		$ftp = self::ParseUrl($this->GetPath($dir));

		return new FtpDirectory($ftp['host'], $ftp['user'], $ftp['pass'], $ftp['dir'], $ftp['port']);
	}

	public function IsDir($path)
	{
		return !preg_match('/\./', $this->GetLocalPath($path));
		//return (@ftp_size($this->ftp, $path) == -1);
	}

	public function GetDirs($dir = '')
	{
		$parent = $this->GetLocalPath($dir);
		$files = @ftp_nlist($this->ftp, $parent);

		if ($files === false)
		{
			return array();
		}

	    $result = array();
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..')
				continue;

			if ($this->IsDir($file))
			{
				$result[] = basename($file);
			}
		}

		return $result;
	}

	public function GetFiles($dir = '')
	{
		$parent = $this->GetLocalPath($dir);
		$files = @ftp_nlist($this->ftp, $parent);

		if ($files === false)
		{
			return array();
		}

	    $result = array();
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..')
				continue;

			if (!$this->IsDir($file))
			{
				$result[] = basename($file);
			}
		}

		return $result;
	}

	public function IsReadable($path)
	{
		if ($this->IsDir($path))
		{
			// Directory
			return @ftp_chdir($this->ftp, $this->GetLocalPath($path));
		}
		else
		{
			// File
			//TODO
			return true;
		}
	}

	public function IsWritable($path)
	{
		if ($this->IsDir($path))
		{
			// Directory
			if (@ftp_put($this->ftp, $this->GetLocalPath('.is_writable_test_file'), Storm::ToAbsolute('do-not-remove'), FTP_ASCII))
			{
				@ftp_delete($this->ftp, $this->GetLocalPath('.is_writable_test_file'));

				return true;
			}
		}
		else
		{
			// File
			//TODO
			return true;
		}

		return false;
	}

	public function HasSubdirs($dir = '')
	{
		return count($this->GetDirs($dir)) > 0;
	}

	public function HasFiles($dir = '')
	{
		return count($this->GetFiles($dir)) > 0;
	}

	public function GetProtocol()
	{
		return 'ftp';
	}

	public function AddFile($from, $to, $mode = 0777)
	{
		$res = ftp_put($this->ftp, $this->GetLocalPath($to), $from, FTP_BINARY);

		if (!$res)
		{
			throw new Exception("Error uploading file to ftp server: ". $from ." -> ". $to);
		}

		return $res;
	}
	public function RemoveFile($path)
	{
		$res = ftp_delete($this->ftp, $this->GetLocalPath($path));

		if (!$res)
		{
			throw new Exception("Error removing file from FTP server: ". $path);
		}

		return $res;
	}

	public function AddDir($path, $mode = 0777)
	{
		$uploaded = ftp_mkdir($this->ftp, $this->GetLocalPath($path));
		$chmoded  = ftp_chmod($this->ftp, $mode, $this->GetLocalPath($path));

		if (!$uploaded)
		{
			throw new Exception("Error creating dir on FTP server: ". $path);
		}

		return $uploaded && $chmoded;
	}
	public function RemoveDir($path)
	{
		$res = ftp_rmdir($this->ftp, $this->GetLocalPath($path));

		if (!$res)
		{
			throw new Exception("Error removing dir from FTP server: ". $path);
		}

		return $res;
	}

	public function GetFile($path, $to)
	{
		$res = ftp_get($this->ftp, $to, $path, FTP_BINARY);

		if (!$res)
		{
			throw new Exception("Error downloading file from FTP server: ". $path ." -> ". $to);
		}

		return $res;
	}
}