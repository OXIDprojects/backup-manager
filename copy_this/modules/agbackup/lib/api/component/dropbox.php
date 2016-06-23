<?php

class dropbox extends StormComponent
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

	public function getAuthorizeUrl()
	{
		session_start();

		require_once 'Dropbox/autoload.php';

		$db = $this->backup_model->get_db();
		$dropbox = new Dropbox_API($db, 'sandbox');

		$tokens = $db->getRequestToken();
		$_SESSION['oauth_tokens'] = $tokens;

		echo json_encode(array(
			'url' => $db->getAuthorizeUrl()
		));
	}

	public function getAuthorizedAccounts()
	{
		session_start();

		$data = $this->backup_model->getDropboxData();

		if (isset($_SESSION['oauth_tokens']))
		{
			require_once 'Dropbox/autoload.php';

			$db = $this->backup_model->get_db();
			$dropbox = new Dropbox_API($db, 'sandbox');
			$db->setToken($_SESSION['oauth_tokens']);

			try
			{
				$tokens = $db->getAccessToken();

				$info = $dropbox->getAccountInfo();

				$account = $this->backup_model->getDropboxAccount($data, $info['uid']);
				if ($account !== null)
				{
					$account->info = $info;
					$account->tokens = $tokens;
				}
				else
				{
					$data[] = array(
						'id' => $info['uid'],
						'info' => $info,
						'tokens' => $tokens
					);
				}

				$this->backup_model->storeDropboxData($data);
			}
			catch (Exception $e)
			{ }
		}

		echo json_encode($data);
	}
}