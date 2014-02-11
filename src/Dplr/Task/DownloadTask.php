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
    protected function _addToPsshTaskList(&$psshTaskList, $server)
    {
        $result = pssh_copy_from_server(
            $psshTaskList,
            $server,
            $this->getRemoteFile(),
            $this->getLocalFile(),
            $this->getTimeout()
        );

        if ($result) {
            return new DownloadTaskReport(
                $this->getRemoteFile(),
                $this->getLocalFile(),
                $server
            );
        }

        return false;
    }
}