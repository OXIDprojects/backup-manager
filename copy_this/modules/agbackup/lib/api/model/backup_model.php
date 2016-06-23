<?php

class backup_model extends StormModel
{
	public function getData()
	{
		// Upgrade backup file
		if (!is_file(Storm::ToAbsolute('../data/backups.php')) && is_file(Storm::ToAbsolute('../data/backups.json')))
		{
			$data = file_get_contents(Storm::ToAbsolute('../data/backups.json'));
			$this->storeData(json_decode($data));
			unlink(Storm::ToAbsolute('../data/backups.json'));
		}

		if (!is_file(Storm::ToAbsolute('../data/backups.php')))
			return array();

		$data = file_get_contents(Storm::ToAbsolute('../data/backups.php'));
		$data = substr($data, strpos($data, '?>') + 2);

		return json_decode($data);
	}

	public function storeData($data)
	{
		$data = '<?php if (!defined(\'STORM_LOADED\')) exit; ?>'. json_encode($data);

		file_put_contents(Storm::ToAbsolute('../data/backups.php'), $data);
	}

	public function getByTitle($data, $title)
	{
		$title = $this->parseTitle($title);

		foreach ($data as $key => $value) {
			if ($this->parseTitle($value->title) == $title) {
				return $value;
			}
		}

		return null;
	}

	public function parseTitle($title)
	{
		return preg_replace("/[^a-zA-Z0-9\-\_]/", "", $title);
	}

	public function isBackupFile($filename, $title)
	{
		return
			strpos($filename, $this->parseTitle($title)) === 0 &&
			preg_match("/\.zip$/", $filename) &&
			$this->extractDate($filename) !== false;
	}

	public function extractDate($filename)
	{
		if (!preg_match('/([0-9]{2}-[0-9]{2}-[0-9]{4})(\s[0-9]{2}-[0-9]{2})?/', $filename, $m))
			return false;

		return strtotime($m[1] . str_replace('-', ':', $m[2]));
	}

	private function toAssoc($obj)
	{
		$result = array();

		foreach ($obj as $key => $value) {
			$result[$key] = $value;
		}

		return $result;
	}

	public function getDestinationDir($obj)
	{
		if ($obj->destType == 'local')
		{
			$dir = new LocalDirectory($obj->destDir);
		}
		else if ($obj->destType == 'ftp')
		{
			$dir = new FtpDirectory($obj->ftpHost, $obj->ftpUser, $obj->ftpPassword, $obj->destDir, $obj->ftpPort);
		}
		else if ($obj->destType == 'sftp')
		{
			if (isset($obj->sftpUsePrivateKey) && $obj->sftpUsePrivateKey)
			{
				$sftpKey_data = $this->getSFTPKey($obj->sftpPrivateKey);

				if ($sftpKey_data == '')
				{
					throw new Exception('Invalid key name ' + $obj->sftpPrivateKey + ' @backup_model::getDestinationDir');
				}

				$dir = new SFtpDirectory($obj->ftpHost, $obj->ftpUser, $obj->ftpPassword, $obj->destDir, $obj->ftpPort, $sftpKey_data);
			}
			else
			{
				$dir = new SFtpDirectory($obj->ftpHost, $obj->ftpUser, $obj->ftpPassword, $obj->destDir, $obj->ftpPort);
			}
		}
		else if ($obj->destType == 'dropbox')
		{
			$account = $this->getDropboxAccount($this->getDropboxData(), $obj->dropboxAccount);

			$db = $this->get_db();
			$db->setToken($this->toAssoc($account->tokens));

			$dir = new DropboxDirectory($db);
		}

		return $dir;
	}

	public function getArchives($obj)
	{
		$dir = $this->getDestinationDir($obj);

		$archives = array();
		$files = $dir->GetFiles();
		foreach ($files as $name)
		{
			if ($this->isBackupFile($name, $obj->title))
			{
				$ar = array();
				$ar['name'] = $name;
				$ar['date'] = strftime("%d.%m.%Y %H:%M", $this->extractDate($name));
				$ar['size'] = $dir->GetSize($name);

				$archives[] = $ar;
			}
		}

		if (!usort($archives, array($this, 'sort_archives')))
		{
			throw new Exception("The sorting function failed. This wasn't supposed to happen ever");
		}

		return $archives;
	}

	public function removeArchive($obj, $fileName)
	{
		$dir = $this->getDestinationDir($obj);

		$dir->RemoveFile($fileName);
	}

	private function getWeekDay($num)
	{
		switch ($num)
		{
			case 0:
				return 'monday';
			case 1:
				return 'tuesday';
			case 2:
				return 'wednesday';
			case 3:
				return 'thursday';
			case 4:
				return 'friday';
			case 5:
				return 'saturday';
			case 6:
				return 'sunday';

			default:
				throw new Exception("Invalid week day: ". $num);
		}
	}

	public function getBackupJobsToStart($data)
	{
		$result = array();

		$now = strtotime("now");

		foreach ($data as $backup)
		{
			if (isset($backup->InProgress) && ($backup->InProgress && isset($backup->LastBackup) && $now - $backup->LastBackup < 60 * 60 * 1 ))
				continue;

			if (!isset($backup->LastBackup) || !$backup->LastBackup)
				$backup->LastBackup = 0;

			if ($backup->type == 'daily')
			{
				$backupTime = strtotime(strftime("%d.%m.%Y") . ' ' . $backup->time);
			}
			else if ($backup->type == 'xhours')
			{
				$backupTime = strtotime('+ '.$backup->xhours.' hours', $backup->LastBackup);
				// Clear the seconds data
				$backupTime = strtotime(strftime("%d.%m.%Y %H:%M", $backupTime));
			}
			else if ($backup->type == 'xdays')
			{
				$backupTime = strtotime('+ '.$backup->xdays.' days', $backup->LastBackup);
				$backupTime = strtotime(strftime("%d.%m.%Y", $backupTime) . ' ' . $backup->time);
			}
			else if ($backup->type == 'weekly')
			{
				if ($backup->LastBackup == 0)
				{
					$backup->LastBackup = strtotime('previous '. $this->getWeekDay($backup->weekDay));
					$backup->LastBackup = strtotime(strftime("%d.%m.%Y", $backup->LastBackup) . ' ' . $backup->time);
				}

				$backupTime = strtotime('next '. $this->getWeekDay($backup->weekDay), $backup->LastBackup);
				$backupTime = strtotime(strftime("%d.%m.%Y", $backupTime) . ' ' . $backup->time);
			}
			else if ($backup->type == 'monthly')
			{
				if ($backup->LastBackup == 0)
				{
					$backup->LastBackup = strtotime('previous month');
					$backup->LastBackup = strtotime($backup->day . '.' . strftime("%m.%Y", $backup->LastBackup) . ' ' . $backup->time);
				}

				$backupTime = strtotime('next month', $backup->LastBackup);
				$backupTime = strtotime($backup->day . '.' . strftime("%m.%Y", $backupTime) . ' ' . $backup->time);
			}
			else
			{
				throw new Exception("Unknown backup type: ". $backup->type);
			}

			if ($backupTime <= $now && $backup->LastBackup < $backupTime)
				$result[] = $backup;
		}

		return $result;
	}

	private function sort_archives($a, $b)
	{
		return strtotime($a['date']) - strtotime($b['date']);
	}

	public function clearOlderArchives($backup)
	{
		if ($backup->keeplastxenabled)
		{
			$archives = $this->getArchives($backup);

			if (count($archives) <= $backup->keeplastx)
			{
				return 0;
			}

			if (!usort($archives, array($this, 'sort_archives')))
			{
				throw new Exception("The sorting function failed. This wasn't supposed to happen ever");
			}

			$removedCount = 0;
			for ($i = 0; $i < count($archives) - $backup->keeplastx; $i++)
			{
				$this->removeArchive($backup, $archives[$i]['name']);
				$removedCount++;
			}

			return $removedCount;
		}

		return true;
	}

	private function isIgnored($fileName, $ignores)
	{
		$ignoresArray = explode(';', $ignores);

		foreach ($ignoresArray as $key => $value)
		{
			if (trim($value) == '')
				continue;

			if (preg_match('#'. str_replace('\*', '(.*?)', preg_quote($value, '#')) .'#i', $fileName))
			{
				return true;
			}
		}

		return false;
	}

	private $warnings = array();

	private function walk_and_backup($dir, $zip, $cd, $zipCd, $excluded, $ignores, $isRoot = false)
	{
		if (in_array($cd, $excluded))
			return;

		// If it's at the top selected dir let $dir->GetDirs($cd) throw a NonReadableDirectoryException
		if (!$isRoot && !$dir->IsReadable($cd))
		{
			$this->warnings[] = "The directory '". $zipCd ."' is not readable or has been removed";
			return;
		}

		$zip->AddDir($zipCd);

		foreach ($dir->GetDirs($cd) as $value)
		{
			$this->walk_and_backup($dir, $zip, $cd .'/'. trim($value, '/\\'), $zipCd .'/'. trim($value, '/\\'), $excluded, $ignores);
		}

		foreach ($dir->GetFiles($cd) as $file)
		{
			if (!$this->isIgnored($file, $ignores))
			{
				$filePath = $dir->GetPath($cd .'/'. trim($file, '/\\'));

				if (!is_readable($filePath))
				{
					$this->warnings['nonReadableFiles']['message'] = "Manche Dateien konnten nicht gelesen werden (aufgrund von System Zugriffsbeschränkungn) und wurden nicht in das Backup übernommen.";
					$this->warnings['nonReadableFiles']['list'][] = $filePath;
				}
				else
				{
					$zip->AddFile($filePath, $zipCd .'/'. trim($file, '/\\'));
				}
			}
		}
	}

	public function getDb()
	{
		return new Dropbox_OAuth_Curl(strrev(substr(base64_decode('dzhNOXo3eDndm8Xg4d2l3phdzxOWwxlmMXpiNGQ4'), 15))                                                                                                                                                                                                                                                                                  , substr(strrev(base64_decode('dzhNOXo3eDndm8Xg4d2l3phdzxOWwxlmMXpiNGQ4')), 15));
	}

	private function createTempSQL($backup)
	{
		$settings = $backup->database;

		$mysql = new MysqlDump($settings->host, $settings->port, $settings->user, $settings->password);

		$result = array();
		
                if(is_array($settings->databases)){
                    // Loop over databases
                    foreach ($settings->databases as $database)
                    {
                            $mysql->select_db($database->name);

                            // Figure out which tables to export
                            $tables = array();
                            if (!$database->selected)
                            {
                                    $tables = $database->included;
                            }
                            else
                            {
                                    $allTables = $mysql->get_tables();

                                    if (isset($database->excluded))
                                    {
                                            foreach ($allTables as $table)
                                            {
                                                    if (!in_array($table, $database->excluded))
                                                    {
                                                            $tables[] = $table;
                                                    }
                                            }
                                    }
                                    else
                                    {
                                            $tables = $allTables;
                                    }
                            }

                            // Get temp file handle and export the required tables
                            $tempFileName = tempnam(Storm::ToAbsolute('/../temp/'), $database->name . '-smartbkp');
                            unlink($tempFileName);
                            $tempFileName .= '.tmp';
                            $result[] = array('dbname' => $database->name, 'filename' => $tempFileName);

                            $temp = fopen($tempFileName, 'wb');

                            $mysql->get_dump($temp, $tables, true);

                            fclose($temp);
                    }
                }
                

		return $result;
	}

	private function normalizeZipPath($cd)
	{
		//$realbase = trim(realpath('/'), '/\\');
		//if (!empty($realbase) && strpos($cd, $realbase) === 0 )
		//{
		//	return trim(substr($cd, strlen($realbase)), '/\\');
		//}
		$parts = explode('/', trim(str_replace('\\', '/', $cd), '/\\'));

		if (strpos($parts[0], ':') > 0)
		{
			unset($parts[0]);
			$cd = implode($parts, '/');
		}

		return trim($cd, '/\\');
	}

	public function verifyArchive($data, $fileName)
	{
		$archives = $this->getArchives($data);

		$valid = false;
		foreach ($archives as $key => $value)
		{
			if ($fileName == $value['name'])
			{
				$valid = true;
			}
		}

		return $valid;
	}

	private function parseWarnings($arr)
	{
		$res = array();

		foreach ($arr as $key => $value)
		{
			if (is_array($value))
			{
				$res[] = $value['message'] . "\n" . implode(",\n", $value['list']);
			}
			else
			{
				$res[] = $value;
			}
		}

		return $res;
	}

	public function quickBackup($data)
	{
		$tempFilePath = tempnam(Storm::ToAbsolute('/../temp/'), 'quickBackup');
		unlink($tempFilePath);
		$tempFilePath .= '.tmp';

		try
		{
			$zip = new ZipDirectory($tempFilePath, ZipArchive::OVERWRITE);

			if ($data->hasDatabase)
			{
				// Create temp sql exports
				$sqls = $this->createTempSQL($data);

				// Create sql files directory
				$zip->AddDir('sql');

				// Add sql files to archive
				foreach ($sqls as $sql)
				{
					$zip->AddFile($sql['filename'], 'sql/'. $sql['dbname'] .'.sql');
				}
			}

			// Backup files
			$dir = new LocalDirectory();

			if ($data->sourceExcluded == null)
				$data->sourceExcluded = array();

			foreach ($data->sourceExcluded as $k => $v)
			{
				$data->sourceExcluded[$k] = trim($v, '/\\');
			}

			$this->warnings = array();
			foreach ($data->sourceIncluded as $currentIncludeDirPath)
			{
				$path = trim($currentIncludeDirPath, '/\\');

				$this->walk_and_backup($dir, $zip, $path, $this->normalizeZipPath($path), $data->sourceExcluded, $data->ignores, true);
			}

			$zip->flush();

			// Remove sql temp files
			if (isset($sqls))
			{
				foreach ($sqls as $sql)
				{
					@unlink($sql['filename']);
				}
			}

			return array(
				'file' => $tempFilePath,
				'warnings' => $this->parseWarnings($this->warnings)
			);
		}
		catch (Exception $e)
		{
			try
			{
				// Flush the temp zip so we can delete it
				// We do this because it will eventually be created when the script ends (zip_close is being called)
				if (isset($zip) && !$zip->isClosed())
				{
					$zip->flush();
				}
			}
			catch (Exception $e) { }

			// Remove any temp files that were parially created (and corrupted due to error)
			if (is_file($tempFilePath))
			{
				@unlink($tempFilePath);
			}

			throw $e;
		}
	}

	public function backup($backup)
	{
		$fileName = $this->parseTitle($backup->title) . ' ' . strftime("%d-%m-%Y %H-%M") . '.zip';

		$tempFilePath = tempnam(Storm::ToAbsolute('/../temp/'), 'smartbkpZip');
		unlink($tempFilePath);
		$tempFilePath .= '.tmp';

		try
		{
			$zip = new ZipDirectory($tempFilePath, ZipArchive::OVERWRITE);

			if ($backup->hasDatabase)
			{
				// Create temp sql exports
				$sqls = $this->createTempSQL($backup);

				// Create sql files directory
				$zip->AddDir('sql');

				// Add sql files to archive
				foreach ($sqls as $sql)
				{
					$zip->AddFile($sql['filename'], 'sql/'. $sql['dbname'] .'.sql');
				}
			}

			// Backup files
			$dir = new LocalDirectory();

			if ($backup->sourceExcluded == null)
				$backup->sourceExcluded = array();

			foreach ($backup->sourceExcluded as $k => $v)
			{
				$backup->sourceExcluded[$k] = trim($v, '/\\');
			}

			$this->warnings = array();
			foreach ($backup->sourceIncluded as $currentIncludeDirPath)
			{
				$path = trim($currentIncludeDirPath, '/\\');

				$this->walk_and_backup($dir, $zip, $path, $this->normalizeZipPath($path), $backup->sourceExcluded, $backup->ignores, true);
			}

			$zip->flush();

			if ($backup->destType == 'local')
			{
				// Move the zip to the destination directory
				rename($tempFilePath, rtrim(str_replace('\\', '/', realpath($backup->destDir)), '/\\') .'/'. $fileName);
			}
			else
			{
				$dest = $this->getDestinationDir($backup);

				$dest->AddFile($tempFilePath, $fileName);

				// Remove the temp file
				@unlink($tempFilePath);
			}

			// Remove sql temp files
			if (isset($sqls))
			{
				foreach ($sqls as $sql)
				{
					@unlink($sql['filename']);
				}
			}

			return $this->parseWarnings($this->warnings);
		}
		catch (Exception $e)
		{
			try
			{
				// Flush the temp zip so we can delete it
				// We do this because it will eventually be created when the script ends (zip_close is being called)
				if (isset($zip) && !$zip->isClosed())
				{
					@$zip->flush();
				}
			}
			catch (Exception $e1) { }
			catch (ErrorException $e1) { }

			// Remove any temp files that were parially created (and corrupted due to error)
			if (is_file($tempFilePath))
			{
				@unlink($tempFilePath);
			}

			throw $e;
		}
	}

	private function importSql($backup, $database, $sql)
	{
		$settings = $backup->database;
		$mysql = new MysqlDump($settings->host, $settings->port, $settings->user, $settings->password, $database);

		return $mysql->execute($sql);
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
		$dir = trim($dir, '/');
		$subdir = trim($subdir, '/');

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

	private function remove_dir($dir, $exception = false)
	{
		$objects = scandir($dir);

     	foreach ($objects as $file)
     	{
			if ($file == "." || $file == "..")
			{
				continue;
			}

	    	if(is_file($file))
	    	{
	    		if ($exception)
	        	{
	        		throw new Exception('Some files were left in the temp directory: '. $file);
	        	}

	        	unlink($file);
	        }
	        else
	        {
	        	$this->remove_dir(rtrim($dir, '/\\') . '/' . ltrim($file, '/\\'));

	        }
	    }

	    rmdir($dir);
	}

	public function restore($backup, $fileName, $importSql = true, $restoreFiles = true)
	{
		$dir = $this->getDestinationDir($backup);

		if ($dir->GetProtocol() !== 'local')
		{
			$zipPath = Storm::ToAbsolute('/../temp/smartbkpRestore'. time() .'.zip');

			$dir->GetFile($fileName, $zipPath);
		}
		else
		{
			$zipPath = $dir->GetPath($fileName);
		}

		try
		{
			$zip = new ZipArchive();
			$zip->open($zipPath, ZipArchive::CREATE);

			// Extract and import sql files
			if ($importSql && $backup->hasDatabase)
			{
				$sqlDirStats = $zip->statName('sql/');
				$ignores[] = $sqlDirStats['index'];

				// The '/sql' dir exists
				if ($sqlDirStats !== false)
				{
					// For each sql file
					for ($i = 0; $i < $zip->numFiles; $i++)
					{
						$path = $zip->getNameIndex($i);

						if (!$this->is_direct_child('sql', $path))
						{
							continue;
						}

						$stat = $zip->statIndex($i);
						if ($stat['size'] === 0)
						{
							continue;
						}

						$sqlFileName = basename($path);
						$ext = pathinfo($sqlFileName, PATHINFO_EXTENSION);
                                                $name = pathinfo($sqlFileName, PATHINFO_FILENAME);
                                                
						if ($ext != 'sql')
						{
							continue;
						}

						$this->importSql($backup, $name, $zip->getFromIndex($i));
					}
				}
			}

			if ($restoreFiles)
			{
				$use_temp = (bool)ini_get( 'open_basedir' );
				$temp_dir = Storm::ToAbsolute('../temp/restore'. time());

				// Extract directories and files
				for ($i = 0; $i < $zip->numFiles; $i++)
				{
					$path = $zip->getNameIndex($i);

					if ($this->is_child('sql', $path) || rtrim($path, '/\\') == 'sql')
					{
						continue;
					}

					$stat = $zip->statIndex($i);

					if ($stat['size'] == 0 && substr($stat['name'], -1, 1) == '/')
					{
						// Directory
						//if (!file_exists($localPath))
						//{
						//	mkdir($localPath, 0777, true);
						//}
					}
					else
					{
						// File
						if (!$use_temp)
						{
							$zip->extractTo(realpath('/'), $path);
						}
						else
						{
							$zip->extractTo($temp_dir, $path);
							@mkdir('/'. ltrim(dirname($path), '/\\'), 0777, true);
							rename($temp_dir . '/' . ltrim($path, '/\\'), '/'. ltrim($path, '/\\'));
						}
					}
				}

				if ($use_temp)
				{
					$this->remove_dir($temp_dir, true);
				}
			}

			$zip->close();

			if ($dir->GetProtocol() !== 'local' && is_file($zipPath))
			{
				@unlink($zipPath);
			}
		}
		catch (Exception $e)
		{
			if ($dir->GetProtocol() !== 'local' && is_file($zipPath))
			{
				@unlink($zipPath);
			}

			throw $e;
		}
	}

	public function getDropboxData()
	{
		// Upgrade dropbox file
		if (!is_file(Storm::ToAbsolute('../data/dropbox.php')) && is_file(Storm::ToAbsolute('../data/dropbox.json')))
		{
			$data = file_get_contents(Storm::ToAbsolute('../data/dropbox.json'));
			$this->storeDropboxData(json_decode($data));
			unlink(Storm::ToAbsolute('../data/dropbox.json'));
		}

		if (!is_file(Storm::ToAbsolute('../data/dropbox.php')))
		{
			return array();
		}

		$data = file_get_contents(Storm::ToAbsolute('../data/dropbox.php'));
		$data = substr($data, strpos($data, '?>') + 2);

		return json_decode($data);
	}

	public function getDropboxAccount($data, $id)
	{
		foreach ($data as $dropbox)
		{
			if ($dropbox->id == $id)
			{
				return $dropbox;
			}
		}

		return null;
	}

	public function storeDropboxData($data)
	{
		$data = '<?php if (!defined(\'STORM_LOADED\')) exit; ?>'. json_encode($data);

		file_put_contents(Storm::ToAbsolute('../data/dropbox.php'), $data);
	}

	public function getSFTPKeys()
	{
		if (!is_file(Storm::ToAbsolute('../data/sftpkeys.php')))
		{
			return array();
		}

		$data = file_get_contents(Storm::ToAbsolute('../data/sftpkeys.php'));
		$data = substr($data, strpos($data, '?>') + 2);

		return json_decode($data);
	}

	public function getSFTPKey($name)
	{
		$keys = $this->getSFTPKeys();

		foreach ($keys as $key)
		{
			if ($key->name == $name)
				return $key->data;
		}

		return '';
	}

	public function storeSFTPKeys($data)
	{
		$data = '<?php if (!defined(\'STORM_LOADED\')) exit; ?>'. json_encode($data);

		file_put_contents(Storm::ToAbsolute('../data/sftpkeys.php'), $data);
	}
}