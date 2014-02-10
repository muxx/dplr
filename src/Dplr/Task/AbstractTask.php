<?php

namespace Dplr\Task;

/**
 * Task
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
abstract class AbstractTask
{
    protected
        $serverGroup,
        $timeout,

        //task result information
        $status,
        $exitStatus,
        $stdOut,
        $stdErr;

    public function __construct($serverGroup = null, $timeout = null)
    {
        if ($serverGroup) {
            $this->setServerGroup($serverGroup);
        }
        $this->setTimeout($timeout);
    }

    /**
     * Return serverGroup for task
     *
     * @access public
     * @return string
     */
    public function getServerGroup()
    {
        return $this->serverGroup;
    }

    /**
     * Set server group for task
     *
     * @access public
     * @param mixed $server
     * @return Task
     */
    public function setServerGroup($serverGroup)
    {
        $this->serverGroup = $serverGroup;

        return $this;
    }

    /**
     * Return timeout of task execution
     *
     * @access public
     * @return integer
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set timeout of task execution
     *
     * @access public
     * @param mixed $timeout = null
     * @return Task
     */
    public function setTimeout($timeout = null)
    {
        $this->timeout = $timeout;

        return $this;
    }

    abstract public function getPsshType();
}