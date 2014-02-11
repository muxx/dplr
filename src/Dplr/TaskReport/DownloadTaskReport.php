<?php

namespace Dplr\TaskReport;

/**
 * Information about download task copying remote file from some server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class DownloadTaskReport extends AbstractTaskReport
{
    protected $localFile, $remoteFile;

    public function __construct($remoteFile = null, $localFile = null, $server = null)
    {
        if ($remoteFile) {
            $this->setRemoteFile($remoteFile);
        }
        if ($localFile) {
            $this->setLocalFile($localFile);
        }
        parent::__construct($server);
    }

    public function getPsshTaskType()
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
     * @return UploadTaskReport
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
     * @return UploadTaskReport
     */
    public function setRemoteFile($remoteFile)
    {
        $this->remoteFile = $remoteFile;

        return $this;
    }

    public function __toString()
    {
        $str =
            'CPR '
            . ($this->getRemoteFile() ? $this->getRemoteFile() : '')
            . ' -> '
            . ($this->getLocalFile() ? $this->getLocalFile() : '')
            . ($this->getServer() ? ' (' . $this->getServer() . ')' : '')
            ;

        return $str;
    }
}
