<?php

namespace Dplr\Task;

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

    public function getPsshType()
    {
        return PSSH_TASK_TYPE_COPY;
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
}