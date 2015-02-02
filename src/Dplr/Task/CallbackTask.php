<?php

namespace Dplr\Task;

use Dplr\TaskReport\CallbackTaskReport;

/**
 * Task which execute callback function for each server in group
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class CallbackTask extends AbstractTask
{
    protected $name;
    protected $callback;

    public function __construct($name, callable $callback, $serverGroup = null, $timeout = null)
    {
        $this->setName($name);
        $this->setCallback($callback);

        parent::__construct($serverGroup, $timeout);
    }

    public function __toString()
    {
        return 'CBK ' . $this->name;
    }

    /**
     * Return task callback
     *
     * @access public
     * @return callable
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Set callback for task
     *
     * @access public
     * @param  callable     $callback
     * @return CallbackTask
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Return task name
     *
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set name for task
     *
     * @access public
     * @param  string       $name
     * @return CallbackTask
     */
    public function setName($name)
    {
        $this->name = (string) $name;

        return $this;
    }

    /**
     * Init pssh task list
     *
     * @access public
     * @param  mixed $pssh
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

        $this->psshTaskHandler = true;
        $this->initTaskList();
    }

    /**
     * Add task to list
     *
     * @access protected
     * @return integer
     */
    protected function initTaskList()
    {
        $i = 0;
        foreach ($this->getServers() as $server) {
            $this->taskReports[$i++] = new CallbackTaskReport($this->name, $server);
        }

        return true;
    }

    /**
     * Run task
     *
     * @access public
     * @param  mixed $callback
     * @return void
     */
    public function run($callback)
    {
        if ($callback) {
            call_user_func($callback, $this->__toString());
        }

        $i = 0;
        $c = $this->callback;

        foreach ($this->getServers() as $server) {
            $report = $this->taskReports[$i++];

            try {
                $result = $c($server);

                $report
                    ->setStatus(PSSH_TASK_DONE)
                    ->setOutput($result)
                ;
            } catch (\Exception $e) {
                $report
                    ->setStatus(PSSH_TASK_ERROR)
                    ->setErrorOutput($e->getMessage())
                ;
            }

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
    }
}
