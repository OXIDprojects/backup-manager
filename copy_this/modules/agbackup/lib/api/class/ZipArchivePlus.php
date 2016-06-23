<?php

class ZipArchivePlus extends ZipArchive
{
    protected $filePath = null;
    protected $currentFileCount = 0;
    protected $maxCurrentFileCount = 900;

    private $flushFailed = false;
    
    public function open($fileName, $flags = null)
    {
        $this->filePath = $fileName;
        $this->currentFileCount = 0;

        return parent::open($fileName, $flags);
    }

    public function close()
    {
        $this->filePath = null;
        $this->currentFileCount = 0;

        return parent::close();
    }

    public function flushFiles()
    {
        $archiveFileName = $this->filePath;

        if ($this->flushFailed || !$this->close())
        {
            return false;
        }

        return $this->open($archiveFileName, self::CREATE);
    }

    public function addFile($fileName, $localName = null, $start = 0, $length = 0)
    {
        if ($this->currentFileCount >= $this->maxCurrentFileCount)
        {
            $res = $this->flushFiles();

            if ($res !== true)
            {
                $this->flushFailed = true;

                throw new Exception("Failed to flush the zip archive contents (". $res .")");
            }
        }

        if ($localName !== null)
        {
            $added = parent::addFile($fileName, $localName);
        }
        else
        {
            $added = parent::addFile($fileName, $localName, $start, $length);
        }

        if ($added)
        {
            $this->currentFileCount++;
        }

        return $added;
    }
}
