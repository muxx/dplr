<?php

namespace Dplr\TaskReport;

/**
 * Information about task executed on some server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
abstract class AbstractTaskReport
{
    protected
        //task result information
        $server,
        $status,
        $exitStatus,
        $stdOut,
        $stdErr;

    public function __construct($server = null)
    {
        if ($server) {
            $this->setServer($server);
        }
    }

    public function isSuccessful()
    {
        return (
            PSSH_TASK_DONE == $this->getStatus() &&
            0 >= $this->getExitStatus()
        );
    }

    /**
     * Get task type in pssh
     *
     * @access public
     * @abstract
     * @return integer
     */
    abstract public function getPsshTaskType();

    /**
     * Return server where task executed
     *
     * @access public
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Set server where task executed
     *
     * @access public
     * @param  mixed              $server
     * @return AbstractTaskReport
     */
    public function setServer($server)
    {
        $this->server = $server;

        return $this;
    }

    /**
     * Return task status
     *
     * @access public
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status for task
     *
     * @access public
     * @param  mixed              $status
     * @return AbstractTaskReport
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Return task exit status
     *
     * @access public
     * @return string
     */
    public function getExitStatus()
    {
        return $this->exitStatus;
    }

    /**
     * Set exit status for task
     *
     * @access public
     * @param  mixed              $exitStatus
     * @return AbstractTaskReport
     */
    public function setExitStatus($exitStatus)
    {
        $this->exitStatus = $exitStatus;

        return $this;
    }

    /**
     * Return task output
     *
     * @access public
     * @return string
     */
    public function getOutput()
    {
        return $this->stdOut;
    }

    /**
     * Set output of task
     *
     * @access public
     * @param  mixed              $output
     * @return AbstractTaskReport
     */
    public function setOutput($output)
    {
        $this->stdOut = $output;

        return $this;
    }

    /**
     * Return task error output
     *
     * @access public
     * @return string
     */
    public function getErrorOutput()
    {
        return $this->stdErr;
    }

    /**
     * Set error output of task
     *
     * @access public
     * @param  mixed              $errorOutput
     * @return AbstractTaskReport
     */
    public function setErrorOutput($errorOutput)
    {
        $this->stdErr = $errorOutput;

        return $this;
    }
}
