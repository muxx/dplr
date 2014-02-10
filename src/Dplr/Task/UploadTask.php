<?php

namespace Dplr\Task;

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