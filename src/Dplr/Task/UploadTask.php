<?php

namespace Dplr\Task;

use Dplr\TaskReport\UploadTaskReport;

/**
 * Task which upload file on remote server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class UploadTask extends AbstractTask
{
    protected
        $localFile,
        $remoteFile;

    public function __construct($localFile = null, $remoteFile = null, $serverGroup = null, $timeout = null)
    {
        if ($localFile) {
            $this->setLocalFile($localFile);
        }
        if ($remoteFile) {
            $this->setRemoteFile($remoteFile);
        }
        parent::__construct($serverGroup, $timeout);
    }

    public function __toString()
    {
        $str =
            'CPY '
            . ($this->getLocalFile() ? $this->getLocalFile() : '')
            . ' -> '
            . ($this->getRemoteFile() ? $this->getRemoteFile() : '')
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
            $result = pssh_copy_to_server(
                $this->psshTaskHandler,
                $server,
                $this->getLocalFile(),
                $this->getRemoteFile(),
                $this->getTimeout()
            );

            if (!$result) {
                throw new \RuntimeException(sprintf('Failed to add upload task for server %s', $server));
            }

            $this->taskReports[] = new UploadTaskReport(
                $this->getLocalFile(),
                $this->getRemoteFile(),
                $server
            );
        }

        return true;
    }
}