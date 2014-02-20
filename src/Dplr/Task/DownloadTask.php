<?php

namespace Dplr\Task;

use Dplr\TaskReport\DownloadTaskReport;

/**
 * Task which download file from remote server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class DownloadTask extends AbstractTask
{
    protected
        $localFile,
        $remoteFile;

    public function __construct($remoteFile = null, $localFile = null, $serverGroup = null, $timeout = null)
    {
        if ($remoteFile) {
            $this->setRemoteFile($remoteFile);
        }
        if ($localFile) {
            $this->setLocalFile($localFile);
        }
        parent::__construct($serverGroup, $timeout);
    }

    public function __toString()
    {
        $str =
            'CPR '
            . ($this->getRemoteFile() ? $this->getRemoteFile() : '')
            . ' -> '
            . ($this->getLocalFile() ? $this->getLocalFile() : '')
            ;

        return $str;
    }

    /**
     * Return local file for uploading
     *
     * @access public
     * @return string
     */
    public function getLocalFile()
    {
        return $this->localFile;
    }

    /**
     * Set local file for uploading
     *
     * @access public
     * @param mixed $localFile
     * @return UploadTask
     */
    public function setLocalFile($localFile)
    {
        $this->localFile = $localFile;

        return $this;
    }

    /**
     * Return remote file for uploading
     *
     * @access public
     * @return string
     */
    public function getRemoteFile()
    {
        return $this->remoteFile;
    }

    /**
     * Set remote file for uploading
     *
     * @access public
     * @param mixed $remoteFile
     * @return UploadTask
     */
    public function setRemoteFile($remoteFile)
    {
        $this->remoteFile = $remoteFile;

        return $this;
    }

    /**
     * Add task to pssh task list for server
     *
     * @access public
     * @abstract
     * @return integer
     */
    protected function initTaskList()
    {
        foreach($this->getServers() as $server) {
            $result = pssh_copy_from_server(
                $this->psshTaskHandler,
                $server,
                $this->getRemoteFile(),
                $this->getLocalFile(),
                $this->getTimeout()
            );

            if (!$result) {
                throw new \RuntimeException(sprintf('Failed to add download task for server %s', $server));
            }

            $this->taskReports[] = new DownloadTaskReport(
                $this->getRemoteFile(),
                $this->getLocalFile(),
                $server
            );
        }

        return true;
    }
}