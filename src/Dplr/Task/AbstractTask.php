<?php

namespace Dplr\Task;

use Dplr\Dplr;
use Dplr\TaskReport\AbstractTaskReport;

/**
 * Task
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
abstract class AbstractTask
{
    protected
        $dplr,
        $serverGroup,
        $timeout;

    public function __construct($serverGroup = null, $timeout = null)
    {
        if ($serverGroup) {
            $this->setServerGroup($serverGroup);
        }
        $this->setTimeout($timeout);
    }

    /**
     * Set deployer
     *
     * @access public
     * @param Dplr $dplr
     * @return AbstractTask
     */
    public function setDplr(Dplr $dplr)
    {
        $this->dplr = $dplr;

        return $this;
    }

    /**
     * Get deployer
     *
     * @access public
     * @return Dplr
     */
    public function getDplr()
    {
        return $this->dplr;
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
     * Return servers list
     *
     * @access public
     * @return array
     */
    public function getServers()
    {
        if (!$this->dplr) {
            throw new \RuntimeException('Deployer not defined for task.');
        }

        if ($this->getServerGroup()) {
            $servers = $this->dplr->getServersByGroup($this->getServerGroup());
        }
        else {
            $servers = $this->dplr->getServers();
        }

        return $servers;
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

    /**
     * Add task to pssh task list
     *
     * @access public
     * @abstract
     * @return integer
     */
    public function addToPsshTaskList(&$psshTaskList)
    {
        if (!$this->dplr) {
            throw new \RuntimeException('Deployer not defined for task.');
        }
        if (!is_resource($psshTaskList)) {
            throw new \InvalidArgumentException(sprintf('addToPsshTaskList() expects parameter 1 to be resource, %s given.', gettype($psshTaskList)));
        }

        foreach($this->getServers() as $server) {
            if ($report = $this->_addToPsshTaskList($psshTaskList, $server)) {
                $this->dplr->addTaskReport($report);
            }
        }
    }

    /**
     * Add task to pssh task list for server
     *
     * @access protected
     * @abstract
     * @param mixed &$psshTaskList
     * @param array &$taskReports
     * @param mixed $server
     * @return AbstractTaskReport|false
     */
    abstract protected function _addToPsshTaskList(&$psshTaskList, $server);
}