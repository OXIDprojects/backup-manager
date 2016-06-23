<?php

class DropboxDirectory implements IDirectory
{
	private $dropbox, $oauth;
	private $sizeCache = array();

	public function __construct($oauth)
	{
		$this->oauth = $oauth;
		$this->dropbox = new Dropbox_API($oauth, 'sandbox');
	}

	public function GetPath($file = '')
	{
		return $file;
	}

	public function GetDirs($dir = '')
	{
		$data = $this->dropbox->getMetaData($dir);

		$result = array();
		foreach ($data['contents'] as $item)
		{
			if ($item['is_dir'])
			{
				$result[] = basename($item['path']);
			}
			else
			{
				$this->sizeCache[trim($item['path'], '/\\')] = $item['bytes'];
			}
		}

		return $result;
	}
	public function GetFiles($dir = '')
	{
		$data = $this->dropbox->getMetaData($dir);

		$result = array();
		foreach ($data['contents'] as $item)
		{
			if (!$item['is_dir'])
			{
				$result[] = basename($item['path']);
				$this->sizeCache[trim($item['path'], '/\\')] = $item['bytes'];
			}
		}

		return $result;
	}

	public function GetSize($path)
	{
		if (isset($this->sizeCache[trim($path, '/\\')]))
			return $this->sizeCache[trim($path, '/\\')];
		else
		{
			$data = $this->dropbox->getMetaData($path);
			$this->sizeCache[trim($path, '/\\')] = $data['bytes'];

			return $data['bytes'];
		}
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
		return count($this->GetDirs($dir)) > 0;
	}
	public function HasFiles($dir = '')
	{
		return count($this->GetFiles($dir)) > 0;
	}

	public function GetProtocol()
	{
		return 'dropbox';
	}

	/* File/dir operations */
	public function AddFile($from, $to, $mode = 0777)
	{
		$this->dropbox->putFileChunked($to, $from);

		return true;
	}
	public function RemoveFile($path)
	{
		$this->dropbox->delete($path);

		return true;
	}

	public function AddDir($path, $mode = 0777)
	{
		$this->dropbox->createFolder($path);

		return true;
	}
	public function RemoveDir($path)
	{
		$this->dropbox->delete($path);

		return true;
	}

	public function GetFile($path, $to)
	{
		$http = fopen($this->GetLink($path), 'rb');
		$file = fopen($to, 'wb');

		while (!feof($http)) {
			fwrite($file, fread($http, 5 * 1024 * 1024));
		}

		fclose($http);
		fclose($file);

		return true;
	}

	public function GetLink($path)
	{
		$media = $this->dropbox->media($path);

		return $media['url'];
	}
}