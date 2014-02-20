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
        $psshTaskHandler,
        $taskReports = array(),
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
     * Init pssh task list
     *
     * @access public
     * @param mixed $pssh
     * @return void
     */
    public function init($pssh)
    {
        if ($this->psshTaskHandler) {
            throw new \LogicException('Task is already inited.');
        }
        if (!$this->dplr) {
            throw new \RuntimeException('Deployer not defined for task.');
        }

        $this->psshTaskHandler = pssh_tasklist_init($pssh);
        $this->initTaskList();
    }

    /**
     * Run task
     *
     * @access public
     * @param mixed $callback
     * @return void
     */
    public function run($callback)
    {
        if ($callback) {
            call_user_func($callback, $this->__toString());
        }

        while (pssh_tasklist_exec($this->psshTaskHandler, $server) == PSSH_RUNNING) {
            if ($callback) {
                call_user_func($callback, '.');
            }
        }

        if ($callback) {
            call_user_func($callback, "\n");
        }
    }

    public function collectResults()
    {
        for ($i = 0, $t = pssh_tasklist_first($this->psshTaskHandler); $t; $i++, $t = pssh_tasklist_next($this->psshTaskHandler)) {
            $report = $this->taskReports[$i];

            if ($report->getPsshTaskType() != pssh_task_type($t)) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'collectTaskReports() expects type %s for task "%s", %s given.',
                        $report->getPsshTaskType(),
                        (string)$report,
                        pssh_task_type($t)
                    )
                );
            }

            $report
                ->setStatus(pssh_task_status($t))
                ->setExitStatus(pssh_task_exit_status($t))
                ->setOutput(pssh_task_stdout($t))
                ->setErrorOutput(pssh_task_stderr($t))
                ;
        }
    }

    /**
     * Return task executing reports
     *
     * @access public
     * @return void
     */
    public function getTaskReports()
    {
        return $this->taskReports;
    }

    /**
     * Add task to pssh task list for server
     *
     * @access protected
     * @abstract
     * @return boolean
     */
    abstract protected function initTaskList();
}