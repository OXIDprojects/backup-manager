<?php

require_once dirname(__FILE__).'/phpseclib.php';

class SFtpDirectory implements IDirectory
{
	private $sftp, $cd;
	private $host, $user, $pass, $dir, $port;

	public static function ParseUrl($url)
	{
		if (!preg_match('/^(?:sftp:\/\/)?(([^:]*)(:(.*))?)?@(.*?)(:([0-9]+))?(\/.*)?$/i', $url, $match))
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
			$port = 22;

		return array(
			'host' => $host,
			'user' => rawurldecode($user),
			'pass' => rawurldecode($pass),
			'dir' => $dir,
			'port' => $port
		);
	}

	public function __construct($host, $user, $pass, $dir = '/', $port = 22, $privateKey = '')
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->dir = $dir;
		$this->port = $port;

		$this->sftp = new Net_SFTP($host, $port);
		$this->cd  = $dir;

		if (!empty($privateKey))
		{
			$rsa_key_auth = new Crypt_RSA();

			if (!empty($pass))
			{
				$rsa_key_auth->setPassword($pass);
			}
			$rsa_key_auth->loadKey($privateKey);

			$login_result = @$this->sftp->login($user, $rsa_key_auth);
		}
		else
		{
			$login_result = @$this->sftp->login($user, $pass);
		}

		if (!$login_result)
		{
			throw new LoginException($host, $user);
		}

		$this->readable = $this->sftp->chdir($dir);
	}

	public function GetLocalPath($path)
	{
		return rtrim($this->cd, '/') .'/'. trim($path, '/');
	}

	public function GetPath($path = '')
	{
		return 'sftp://'. ($this->user != '' ? rawurlencode($this->user) . ($this->pass != '' ? ':'. rawurlencode($this->pass) : '') : '') .'@'. $this->host . ($this->port != 22 ? ':'. $this->port : '') .'/'. preg_replace("/^\//", "", $this->GetLocalPath($path));
	}


	public function GetDirs($dir = '')
	{
		$parent = $this->GetLocalPath($dir);
		$files = $this->sftp->rawlist($parent);

		if ($files === false)
		{
			return array();
		}

		$result = array();
		foreach ($files as $file => $info)
		{
			if ($file == '.' || $file == '..')
				continue;

			if ($info['type'] == NET_SFTP_TYPE_DIRECTORY)
			{
				$result[] = $file;
			}
		}

		return $result;
	}
	public function GetFiles($dir = '')
	{
		$parent = $this->GetLocalPath($dir);
		$files = $this->sftp->rawlist($parent);

		if ($files === false)
		{
			return array();
		}

	    $result = array();
		foreach ($files as $file => $info)
		{
			if ($file == '.' || $file == '..')
				continue;

			if ($info['type'] == NET_SFTP_TYPE_REGULAR)
			{
				$result[] = $file;
			}
		}

		return $result;
	}

	public function GetSize($path)
	{
		return $this->sftp->size($path);
	}

	public function IsReadable($path)
	{
		if ($this->IsDir($path))
		{
			// Directory
			$readable = $this->sftp->chdir($this->GetLocalPath($path));

			$this->readable = $this->sftp->chdir($this->cd);

			return $readable;
		}
		else
		{
			// File
			//TODO
			return true;
		}
	}

	public function IsDir($path)
	{
		return !preg_match('/\./', $this->GetLocalPath($path));
		//return (@ftp_size($this->ftp, $path) == -1);
	}

	public function IsWritable($path)
	{
		if ($this->IsDir($path))
		{
			// Directory
			if (@$this->sftp->put($this->GetLocalPath('.is_writable_test_file'), 'This file is used by SmartBackup to test if the directory is readable. You can remove it.'))
			{
				@$this->sftp->delete($this->GetLocalPath('.is_writable_test_file'));

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
		return 'sftp';
	}

	/* File/dir operations */
	public function AddFile($from, $to, $mode = 0777)
	{
		$res = $this->sftp->put($this->GetLocalPath($to), $from, NET_SFTP_LOCAL_FILE);

		if (!$res)
		{
			throw new Exception("Error uploading file to SFTP server: ". $from ." -> ". $to);
		}

		return $res;
	}
	public function RemoveFile($path)
	{
		$res = $this->sftp->delete($this->GetLocalPath($path));

		if (!$res)
		{
			throw new Exception("Error removing file from SFTP server: ". $path);
		}

		return $res;
	}

	public function AddDir($path, $mode = 0777)
	{
		$uploaded = $this->sftp->mkdir($this->GetLocalPath($path));
		$chmoded  = $this->sftp->chmod($mode, $this->GetLocalPath($path));

		if (!$uploaded)
		{
			throw new Exception("Error creating dir on SFTP server: ". $path);
		}

		return $uploaded && $chmoded;
	}
	public function RemoveDir($path)
	{
		$res = $this->sftp->rmdir($this->GetLocalPath($path));

		if (!$res)
		{
			throw new Exception("Error removing dir from SFTP server: ". $path);
		}

		return $res;
	}

	public function GetFile($path, $to)
	{
		$res = $this->sftp->get($path, $to);

		if (!$res)
		{
			throw new Exception("Error downloading file from SFTP server: ". $path ." -> ". $to);
		}

		return $res;
	}
}