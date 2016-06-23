<?php

class archives extends StormComponent
{
	private $backup_model;

	function __construct()
	{
		$this->backup_model = StormModel::getInstance('backup_model');
		$this->log_model = StormModel::getInstance('log_model');
	}

	private function passChanged()
	{
		require Storm::ToAbsolute('../settings.php');

		return $settings['password'] != '' && $settings['password'] != 'INSERT PASSWORD HERE';
	}

	private function verifyPass($password)
	{
		require Storm::ToAbsolute('../settings.php');

		return $settings['password'] == $password;
	}

	public function _call($name, $params)
	{
		if (!$this->passChanged() || !isset($_GET['key']) || !$this->verifyPass($_GET['key']))
			return new Status(403);
	}

	public function index()
	{
		echo "Direct access is not allowed";
	}

	public function remove($title, $fileName)
	{
		$title = $this->backup_model->parseTitle($title);
		$data = $this->backup_model->getByTitle($this->backup_model->getData(), $title);

		if (!$data || !$this->backup_model->verifyArchive($data, $fileName))
		{
			return new Status(400);
		}

		$this->backup_model->removeArchive($data, $fileName);

		return "{'success': true}";
	}

	public function download($title, $fileName)
	{
		$title = $this->backup_model->parseTitle($title);
		$data = $this->backup_model->getByTitle($this->backup_model->getData(), $title);

		if (!$data || !$this->backup_model->verifyArchive($data, $fileName))
		{
			return new Status(400);
		}

		$dir = $this->backup_model->getDestinationDir($data);

		if ($dir->GetProtocol() == 'dropbox')
		{
			$path = $dir->GetLink($fileName);
			header('Location: '. $path);
			exit;
		}
		else if ($dir->GetProtocol() == 'sftp')
		{
			echo 'unsupported';
			exit;
		}
		else
		{
			$path = $dir->GetPath($fileName);
		}

		header('Content-Description: File Transfer');
	    header('Content-Type: application/octet-stream');
	    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
	    header('Content-Transfer-Encoding: binary');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: private');
	    header('Content-Length: ' . filesize($path));
	    flush();
	    readfile($path);
	}

	private function return_kbytes($size_str)
	{
	    switch (substr ($size_str, -1))
	    {
	        case 'M': case 'm': return (int)$size_str * 1024;
	        case 'K': case 'k': return (int)$size_str;
	        case 'G': case 'g': return (int)$size_str * 1048576;
	        default: return $size_str;
	    }
	}

	public function restore($title, $fileName, $database__bool = true, $files__bool = true)
	{
		$title = $this->backup_model->parseTitle($title);
		$data = $this->backup_model->getByTitle($this->backup_model->getData(), $title);

		if (!$data || !$this->backup_model->verifyArchive($data, $fileName))
		{
			return new Status(400);
		}

		try
		{
			@set_time_limit(0);

			if ($this->return_kbytes(ini_get('memory_limit')) < 256 * 1024)
			{
				@ini_set('memory_limit', '256M');
			}
		}
		catch (Exception $e) { }

		try
		{
			$this->backup_model->restore($data, $fileName, $database__bool, $files__bool);
		}
		catch (Exception $e)
		{
			$this->log_model->Log(Storm::ToAbsolute('../logs/'. $title .'.txt'), "Error restoring archive: " . $e->__toString());

			return new Status(500);
		}

		echo '{"success": true}';
	}
}