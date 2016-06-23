<?php

class privatekeys extends StormComponent
{
	private $backup_model, $log_model;

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

	public function addKey()
	{
		$keys = $this->backup_model->getSFTPKeys();

		if (empty($_POST['name']) || empty($_POST['data']))
		{
			throw new Exception('No `name` or `data` fields @privatekeys::addKey()');
		}

		$keys[] = array(
			'name' => $_POST['name'],
			'data' => $_POST['data']
		);

		$this->backup_model->storeSFTPKeys($keys);
	}

	public function getKeys()
	{
		$keys = $this->backup_model->getSFTPKeys();

		$data = array();
		foreach ($keys as $key)
		{
			$data[] = $key->name;
		}

		echo json_encode($data);
	}
}