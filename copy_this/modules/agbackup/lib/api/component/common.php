<?php

class config {

    function __construct() {
        include( realpath(dirname(__FILE__)) . '/../../../../../config.inc.php');
    }

}

class common extends StormComponent {

    public $backup_model;

    public function __construct() {
        $this->backup_model = StormModel::getInstance('backup_model');
    }

    public function index() {
        echo "Direct access is not allowed";
    }

    private function get_version() {
        try {
            return @json_decode(@file_get_contents('http://gangelov.net/api/v1/smartbackup/latest_version.json'));
        } catch (Exception $e) {
            return new stdClass();
        }
    }

    public function checkInstall() {
        global $settings;

        if (!$this->passChanged() || !$this->verifyPass($_GET['key']))
            return new Status(403);

        $errors = array();

        if (!class_exists('ZipArchive'))
            $errors[] = array('type' => 'error', 'title' => 'Error!', 'message' => 'Die ZipArchive php extension ist deaktiviert. Ohne diese Erweiterung können keine Backups erstellt werden.!');


        if (isset($settings) && isset($settings['update_checks']) && $settings['update_checks']) {
            // Check for newer version
            $versionObj = $this->get_version();

            if (isset($versionObj) && isset($versionObj->version) &&
                    version_compare(strtolower(SMARTBACKUP_VERSION), strtolower($versionObj->version)) === -1) {
                $errors[] = array('type' => 'error', 'title' => 'Please update SmartBackup!', 'message' => 'There is a new version of SmartBackup! Please log in to your codecanyon profile and download it (it\'s free because you have already purchased it). ' .
                    (isset($versionObj->changelog) && !empty($versionObj->changelog) ? 'Here is what has been changed: ' . "\n" . $versionObj->changelog : '')
                );
            }
        }

        $backups = $this->backup_model->getData();

        if (count($backups) == 0) {
            $errors[] = array('type' => 'warning', 'title' => 'Cron Job Information', 'message' => 'Damit die Backup Jobs regelmäßig ausgeführt werden können müssen Sie folgenden Befehl als Cron alle 5 Minuten ausführen lassen: ' . "\n\n" . 'php ' . realpath(rtrim(Storm::ToAbsolute('../cron.php'))) . ' > /dev/null 2>&1');
        }

        foreach ($backups as $backup) {
            if (isset($backup->errors)) {
                foreach ($backup->errors as $error) {
                    $errors[] = array('type' => 'error', 'title' => '`' . $backup->title . '` fehlgeschlagen', 'message' => 'Fehlermeldung: ' . $error->message);
                }

                unset($backup->errors);
            }

            if (isset($backup->warnings)) {
                foreach ($backup->warnings as $error) {
                    $errors[] = array('type' => 'warning', 'title' => '`' . $backup->title . '` hatte Warnungen', 'message' => $error->message);
                }

                unset($backup->warnings);
            }
        }

        $this->backup_model->storeData($backups);

        $data = new stdClass();
        $data->alerts = $errors;

        echo json_encode($data);
    }

    private function passChanged() {
        require Storm::ToAbsolute('../settings.php');

        return $settings['password'] != '' && $settings['password'] != 'INSERT PASSWORD HERE';
    }

    private function verifyPass($password) {
        require Storm::ToAbsolute('../settings.php');

        return $settings['password'] == $password;
    }

    public function hasPassword() {
        if ($this->passChanged())
            echo '{"result": true}';
        else
            echo '{"result": false}';
    }

    public function checkPassword($password) {
        if (!$this->passChanged()) {
            echo '{"result": false}';
            return;
        }

        echo '{"result": ' . ($this->verifyPass($password) ? 'true' : 'false') . '}';
    }

    private function getTree($root, $dir, $password, $type, $urlParams = '') {
        $ar = array();

        foreach ($dir->GetDirs() as $subdir) {
            $obj = new stdClass();
            $obj->name = $subdir;

            if ($dir->GetProtocol() == 'local') {
                $obj->path = rtrim($root, '/\\') . '/' . trim($subdir, '/\\');
                $obj->readable = $dir->IsReadable($subdir);
            } else if ($dir->GetProtocol() == 'ftp' || $dir->GetProtocol() == 'sftp') {
                $obj->path = $dir->GetLocalPath($subdir);
                $obj->readable = true;
            }

            $obj->writable = $dir->IsWritable($subdir);

            if ($obj->readable) {
                try {
                    if ($dir->GetProtocol() == 'local')
                        $obj->hasChildren = $dir->HasSubdirs($subdir);
                    else
                        $obj->hasChildren = true;
                } catch (NonReadableDirectoryException $e) {
                    $obj->readable = false;
                    $obj->writable = false;
                    $obj->hasChildren = false;
                }
            } else
                $obj->hasChildren = false;

            $obj->childrenUrl = 'api/index.php?path=/getDirTree&key=' . rawurlencode($password) . '&type=' .
                    rawurlencode($type) . $urlParams . '&root=' . preg_replace("#^ftp\:\/\/#i", "", $dir->GetPath($subdir));

            $ar[] = $obj;
        }

        return $ar;
    }

    public function getOxidDatabase($key) {

        if (!$this->passChanged() || !$this->verifyPass($key))
            return new Status(403);

        $config = new config();

        $databaseSettings = array(
            'host' => $config->dbHost,
            'port' => 3306,
            'user' => $config->dbUser,
            'password' => $config->dbPwd,
            'databases' => array($config->dbName),
            'tables' => array()
        );

        echo json_encode($databaseSettings);
    }

    public function getDirTree($key, $root = null, $type = 'local', $sftpKey = '') {
        if (!$this->passChanged() || !$this->verifyPass($key))
            return new Status(403);

        if ($root == null) {
            $c = new config();
            $root = LocalDirectory::GetTopReadableParent(dirname(__FILE__));//$c->sShopDir; //LocalDirectory::GetTopReadableParent(dirname(__FILE__));
        }

        if (!empty($sftpKey)) {
            $sftpKey_data = $this->backup_model->getSFTPKey($sftpKey);

            if ($sftpKey_data == '') {
                throw new Exception('Falscher Schlüssel Name @common::getDirTree');
            }
        }

        if ($type == 'ftp') {
            $ftpAddr = FtpDirectory::ParseUrl($root);

            try {
                $ftp = new FtpDirectory($ftpAddr['host'], $ftpAddr['user'], $ftpAddr['pass'], $ftpAddr['dir'], $ftpAddr['port']);
            } catch (ConnectionException $e) {
                echo "conn";
                return new Status(400);
            } catch (LoginException $e) {
                echo "log";
                return new Status(400);
            }

            $data = $this->getTree($root, $ftp, $key, $type);
        } else if ($type == 'sftp') {
            $sftpAddr = SFtpDirectory::ParseUrl($root);

            // try
            // {
            $sftp = new SFtpDirectory($sftpAddr['host'], $sftpAddr['user'], $sftpAddr['pass'], $sftpAddr['dir'], $sftpAddr['port'], $sftpKey_data);
            // }
            // catch (ConnectionException $e)
            // {
            // 	echo "conn";
            // 	return new Status(400);
            // }
            // catch (LoginException $e)
            // {
            // 	echo "log";
            // 	return new Status(400);
            // }

            $urlParams = '&sftpKey=' . rawurlencode($sftpKey);

            $data = $this->getTree($root, $sftp, $key, $type, $urlParams);
        } else {
            $data = $this->getTree($root, new LocalDirectory($root), $key, $type);
        }

        if ($data !== false) {
            echo json_encode($data);
        } else {
            return new Status(400);
        }
    }

    public function getDatabaseTree($host, $user, $password, $port = 3306) {
        if (!$this->passChanged() || !$this->verifyPass($_GET['key']))
            return new Status(403);

        $db = new MysqlDump($host, $port, $user, $password);
        $databases = $db->get_databases();

        $result = array();
        foreach ($databases as $database) {
            $obj = new stdClass();
            $obj->name = $database;
            $obj->path = $database;
            $obj->readable = true;

            $db->select_db($database);
            $tables = $db->get_tables();

            $obj->children = array();
            foreach ($tables as $table) {
                $tbl = new stdClass();
                $tbl->name = $table;
                $tbl->path = $database . '/' . $table;
                $tbl->readable = true;
                $tbl->hasChildren = false;

                $obj->children[] = $tbl;
            }

            $result[] = $obj;
        }

        echo json_encode($result);
    }

}
