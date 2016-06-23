<?php

class quick extends StormComponent
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

	private function toBool($str)
	{
		if ($str === true || strtolower($str) === 'true' || $str === 1 || $str === '1')
			return true;
		else
			return false;
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

	public function backup()
	{
		try
		{
			@set_time_limit(0);

			if ($this->return_kbytes(ini_get('memory_limit')) < 256 * 1024)
			{
				@ini_set('memory_limit', '256M');
			}
		}
		catch (Exception $e) { }

		$logfile = Storm::ToAbsolute('/../logs/quick_backup.txt');

		$data = new stdClass();
		$data->sourceIncluded = isset($_POST['sourceIncluded']) ? $_POST['sourceIncluded'] : array();
		$data->sourceExcluded = isset($_POST['sourceExcluded']) ? $_POST['sourceExcluded'] : array();
		$data->ignores = $_POST['ignores'];

		// Database stuff
		$data->hasDatabase = $this->toBool($_POST['hasDatabase']);
		if ($data->hasDatabase && isset($_POST['database']) && is_array($_POST['database']) && count($_POST['database']) > 0)
		{
			$data->database = $_POST['database'];

			// Fix string -> bool conversion
			foreach ($data->database['databases'] as &$database) {
				$database['selected'] = $this->toBool($database['selected']);
			}

			// Fix conversation to objects (ugly, I know)
			$data->database = json_decode(json_encode($data->database));
		}

		try
		{
			$this->log_model->Log($logfile, "Starting quick backup...");
			$result = $this->backup_model->quickBackup($data);
			$this->log_model->Log($logfile, "Quick backup successful");

			session_start();
			$_SESSION['quickBackupFile'] = $result['file'];

			echo json_encode(array(
					'success' => true
				));
		}
		catch (Exception $e)
		{
			$this->log_model->Log($logfile, "Quick backup failed. Exception: " . $e->__toString());

			//TODO: Pass json data to the caller?

			return new Status(500);
		}
	}

	public function download()
	{
		session_start();

		if (!isset($_SESSION['quickBackupFile']) || !is_file($_SESSION['quickBackupFile']))
		{
			return new Status(400);
		}

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="quick backup '. strftime("%d-%m-%Y %H-%M") .'.zip"');
	    header('Content-Transfer-Encoding: binary');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: private');
	    header('Content-Length: ' . filesize($_SESSION['quickBackupFile']));
	    flush();
	    readfile($_SESSION['quickBackupFile']);

	    @unlink($_SESSION['quickBackupFile']);
	}

}