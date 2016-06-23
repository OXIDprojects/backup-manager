<?php

class backups extends StormComponent {

    private $backup_model;

    function __construct() {
        $this->backup_model = StormModel::getInstance('backup_model');
        $this->log_model = StormModel::getInstance('log_model');
    }

    private function passChanged() {
        require Storm::ToAbsolute('../settings.php');

        return $settings['password'] != '' && $settings['password'] != 'INSERT PASSWORD HERE';
    }

    private function verifyPass($password) {
        require Storm::ToAbsolute('../settings.php');

        return $settings['password'] == $password;
    }

    public function _call($name, $params) {
        if (!$this->passChanged() || !isset($_GET['key']) || !$this->verifyPass($_GET['key']))
            return new Status(403);
    }

    public function index() {
        echo "Direct access is not allowed";
    }

    private function toBool($str) {
        if ($str === true || strtolower($str) === 'true' || $str === 1 || $str === '1')
            return true;
        else
            return false;
    }

    public function add($overwrite__bool = false) {
        $data = $this->backup_model->getData();

        if (!is_array($data))
            $data = array();

        if (!$overwrite__bool && $this->backup_model->getByTitle($data, $_POST['title']))
            return new Status(409);

        if (!$overwrite__bool)
            $obj = new stdClass();
        else
            $obj = $this->backup_model->getByTitle($data, $_POST['title']);

        if ($obj == null)
            return new Status(409);

        if ($_POST['destType'] != 'dropbox' && ( empty($_POST['destDir']) || empty($_POST['title'])))
            return new Status(400);

        $obj->title = $_POST['title'];
        $obj->sourceIncluded = isset($_POST['sourceIncluded']) ? $_POST['sourceIncluded'] : array();
        $obj->sourceExcluded = isset($_POST['sourceExcluded']) ? $_POST['sourceExcluded'] : array();
        $obj->ignores = $_POST['ignores'];
        $obj->destType = $_POST['destType'];
        $obj->destDir = $_POST['destDir'];

        if (isset($_POST['dropboxAccount']))
            $obj->dropboxAccount = (int) $_POST['dropboxAccount'];
        else
            $obj->dropboxAccount = 0;

        if ($obj->destType == 'ftp' || $obj->destType == 'sftp') {
            $defaultPort = 21;
            if ($obj->destType == 'sftp')
                $defaultPort = 22;

            preg_match("/^(.+?)(\:([0-9]+))?$/", preg_replace("/^s?ftp\:\/\//i", "", $_POST['ftpHost']), $m);
            $obj->ftpHost = $m[1];
            $obj->ftpPort = !empty($m[3]) ? (int) $m[3] : $defaultPort;
            $obj->ftpUser = $_POST['ftpUser'];
            $obj->ftpPassword = $_POST['ftpPassword'];

            $obj->sftpUsePrivateKey = $this->toBool($_POST['sftpUsePrivateKey']);
            $obj->sftpPrivateKey = $obj->sftpUsePrivateKey ? $_POST['sftpPrivateKey'] : '';
        }
        else {
            $obj->ftpHost = '';
            $obj->ftpPort = 21;
            $obj->ftpUser = '';
            $obj->ftpPassword = '';

            $obj->sftpUsePrivateKey = false;
            $obj->sftpPrivateKey = '';
        }

        $obj->type = $_POST['type'];
        $obj->time = $_POST['time'];
        $obj->day = $_POST['day'];
        $obj->weekDay = $_POST['weekDay'];
        $obj->xdays = (int) $_POST['xdays'];
        $obj->xhours = (int) $_POST['xhours'];

        $obj->keeplastxenabled = $this->toBool($_POST['keeplastxenabled']);
        $obj->keeplastx = (int) $_POST['keeplastx'];

        $obj->emailMe = $this->toBool($_POST['emailMe']);
        $obj->email = $_POST['email'];

        // Database stuff
        $obj->hasDatabase = $this->toBool($_POST['hasDatabase']);
        if ($obj->hasDatabase && isset($_POST['database']) && is_array($_POST['database']) && count($_POST['database']) > 0) {
            $obj->database = $_POST['database'];

            // Fix string -> bool conversion
            foreach ($obj->database['databases'] as &$database) {
                $database['selected'] = $this->toBool($database['selected']);
            }
        }

        if (!$overwrite__bool)
            $data[] = $obj;

        $this->backup_model->storeData($data);
    }

    public function getArchives($title) {
        $data = $this->backup_model->getData();
        $obj = $this->backup_model->getByTitle($data, $title);

        if ($obj == null)
            return new Status(400);

        $result = $this->backup_model->getArchives($obj);

        $result = self::appendParams($result, array(
                    'nodownload' => ($obj->destType == 'sftp')
        ));

        echo json_encode($result);
    }

    private static function appendParams($array, $params) {
        $result = array();

        foreach ($array as $key => $value) {
            foreach ($params as $paramKey => $paramValue) {
                $value[$paramKey] = $paramValue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function get($title = '') {
        $data = $this->backup_model->getData();

        if (!empty($title)) {
            $data = $this->backup_model->getByTitle($data, $title);

            if ($data == null) {
                return new Status(400);
            }
        }

        echo json_encode($data);
    }

    public function remove($title) {
        $title = $this->backup_model->parseTitle($title);
        $data = $this->backup_model->getData();

        $found = false;
        $newData = array();
        foreach ($data as $k => $backup) {
            if ($this->backup_model->ParseTitle($backup->title) != $title) {
                $newData[] = $backup;
            } else {
                $found = true;
            }
        }

        if (!$found) {
            return new Status(400);
        }

        $this->backup_model->storeData($newData);
    }

    private function return_kbytes($size_str) {
        switch (substr($size_str, -1)) {
            case 'M': case 'm': return (int) $size_str * 1024;
            case 'K': case 'k': return (int) $size_str;
            case 'G': case 'g': return (int) $size_str * 1048576;
            default: return $size_str;
        }
    }

    public function backup($title) {
        $title = $this->backup_model->parseTitle($title);
        $data = $this->backup_model->getData();

        $backup = $this->backup_model->getByTitle($data, $title);

        if ($backup == null) {
            return new Status(400);
        }

        $logfile = Storm::ToAbsolute('/../logs/' . $this->backup_model->parseTitle($backup->title) . '.txt');
        $startTime = strtotime('now');

        try {
            @set_time_limit(0);

            if ($this->return_kbytes(ini_get('memory_limit')) < 256 * 1024) {
                @ini_set('memory_limit', '256M');
            }
        } catch (Exception $e) {
            
        }

        try {
            $this->log_model->Log($logfile, "Starting manual backup...");
            $warnings = $this->backup_model->backup($backup);
            $this->log_model->Log($logfile, "Backup successful");

            echo json_encode(array(
                'warnings' => $warnings,
                'success' => true
            ));
        } catch (Exception $e) {
            $backup->errors[] = array(
                'start' => $startTime,
                'end' => strtotime('now'),
                'success' => false,
                'message' => $e->__toString()
            );
            $this->log_model->Log($logfile, "Manual backup failed. Exception: " . $e->__toString());

            return new Status(500);
        }

        $this->backup_model->storeData($data);
    }

}
