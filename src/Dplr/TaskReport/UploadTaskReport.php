<?php

namespace Dplr\TaskReport;

/**
 * Information about upload task copying local file to some server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class UploadTaskReport extends AbstractTaskReport
{
    protected $localFile, $remoteFile;

    public function __construct($localFile = null, $remoteFile = null, $server = null)
    {
        if ($localFile) {
            $this->setLocalFile($localFile);
        }
        if ($remoteFile) {
            $this->setRemoteFile($remoteFile);
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
     * @param  mixed            $localFile
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
     * @param  mixed            $remoteFile
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
            'CPY '
            . ($this->getLocalFile() ? $this->getLocalFile() : '')
            . ' -> '
            . ($this->getRemoteFile() ? $this->getRemoteFile() : '')
            . ($this->getServer() ? ' (' . $this->getServer() . ')' : '')
            ;

        return $str;
    }
}
