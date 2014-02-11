<?php

namespace Dplr;

use Dplr\Exception\ConnectionFailedException;
use Dplr\Task\AbstractTask;
use Dplr\TaskReport\AbstractTaskReport;

/**
 * Object oriented deployer based on pssh_extension + libpssh
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class Dplr
{
   /*
    * pssh constants
    *
    * connection status:
    *   PSSH_CONNECTED
    *   PSSH_RUNNING
    *   PSSH_TIMEOUT
    *   PSSH_FAILED
    *   PSSH_SUCCESS
    *
    * task execution result
    *   PSSH_TASK_NONE
    *   PSSH_TASK_ERROR
    *   PSSH_TASK_INPROGRESS
    *   PSSH_TASK_DONE
    *
    * task types
    *   PSSH_TASK_TYPE_COPY
    *   PSSH_TASK_TYPE_EXEC
    *
    */

    protected
        $pssh,
        $psshTaskHandler,

        $servers = array(),
        $tasks = array(),
        $taskReports = array(),

        $connectionTimeout,

        $timers = array();

    public function __construct($user, $publicKey, $privateKey = null, $password = null, $connectionTimeout = 3)
    {
        $this->pssh = pssh_init($user, $publicKey, $privateKey, $password);

        if ($connectionTimeout) {
            $this->connectionTimeout = $connectionTimeout;
        }
    }

    /**
     * Add server for deploying
     *
     * @access public
     * @param mixed $serverName
     * @param mixed $groups (default: null)
     * @return Dplr
     */
    public function addServer($serverName, $groups = null)
    {
        if ($groups && !is_array($groups)) {
            $groups = array($groups);
        }

        $this->servers[$serverName] = $groups;

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
        return array_keys($this->servers);
    }

    /**
     * Return servers list of group
     *
     * @access public
     * @param mixed $group
     * @return array
     */
    public function getServersByGroup($group)
    {
        $servers = array();
        foreach($this->servers as $serverName => $groups) {
            if (in_array($group, $groups)) {
                $servers[] = $serverName;
            }
        }

        return $servers;
    }

    /**
     * Add task for executing on server
     *
     * @access public
     * @param AbstractTask $task
     * @return Dplr
     */
    public function addTask(AbstractTask $task)
    {
        if ($group = $task->getServerGroup()) {
            $contain = false;
            foreach($this->servers as $serverName => $groups) {
                if (in_array($group, $groups)) {
                    $contain = true;
                }
            }

            if (!$contain) {
                throw new \RuntimeException(sprintf('Server group "%s" not defined in current session.', $group));
            }
        }

        $task->setDplr($this);
        $this->tasks[] = $task;

        return $this;
    }

    /**
     * Add task report
     *
     * @access public
     * @param AbstractTaskReport $task
     * @return Dplr
     */
    public function addTaskReport(AbstractTaskReport $taskReport)
    {
        $this->taskReports[] = $taskReport;

        return $this;
    }

    /**
     * Register servers in pssh
     *
     * @access protected
     * @return void
     */
    protected function registerServers()
    {
        if (!sizeof($this->servers)) {
            throw new \UnexpectedValueException('Not defined servers list.');
        }

        foreach($this->servers as $serverName => $groups) {
            pssh_server_add($this->pssh, $serverName);
        }
    }

    /**
     * Do connecting to servers.
     *
     * @access protected
     * @return void
     */
    protected function connectToServers()
    {
        $servers = $this->servers;

        $this->timers['connection'] = new \DateTime();
        do {
            $ret = pssh_connect($this->pssh, $server, $this->connectionTimeout);
        	switch ($ret) {
        		case PSSH_CONNECTED:
        			unset($servers[$server]);
        			break;
        	}
        } while ($ret == PSSH_CONNECTED);
        $this->timers['connection'] = $this->timers['connection']->diff(new \DateTime());

        if (PSSH_SUCCESS !== $ret) {
            throw new ConnectionFailedException($servers);
        }
    }

    protected function prepareTasks()
    {
        if ($this->psshTaskHandler) {
            throw new \LogicException('Tasks already defined.');
        }

        $this->psshTaskHandler = pssh_tasklist_init($this->pssh);

        foreach($this->tasks as $task) {
            $task->addToPsshTaskList($this->psshTaskHandler);
        }
    }

    /**
     * Run tasks on servers
     *
     * @access public
     * @param mixed $callback (default: null)
     * @return void
     */
    public function run()
    {
        $this->registerServers();
        $this->connectToServers();

        //unroll server groups in server lists and add tasks
        $this->prepareTasks();
        $this->runTasks();

        $this->collectTaskReports();
    }

    /**
     * Check that all task executed successfully
     *
     * @access public
     * @return boolean
     */
    public function isSuccessful()
    {
        $result = true;
        foreach($this->taskReports as $report) {
            $result &= $report->isSuccessful();
        }

        return $result;
    }

    /**
     * Return short report about task executing
     *
     * @access public
     * @return array
     */
    public function getReport()
    {
        $result = array(
            'total' => sizeof($this->taskReports),
            'successful' => 0,
            'failed' => 0,
            'timers' => array(
                'connection' => $this->timers['connection']->format('%I:%S'),
                'execution' => $this->timers['execution']->format('%I:%S'),
            ),
        );

        foreach($this->taskReports as $report) {
            if ($report->isSuccessful()) {
                $result['successful']++;
            }
            else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Return failed task reports
     *
     * @access public
     * @return array
     */
    public function getFailed()
    {
        $result = array();
        foreach($this->taskReports as $report) {
            if (!$report->isSuccessful()) {
                $result[] = $report;
            }
        }

        return $result;
    }

    protected function runTasks()
    {
        $this->timers['execution'] = new \DateTime();
        do {
            $return = pssh_tasklist_exec($this->psshTaskHandler, $server);
        }
        while ($return == PSSH_RUNNING);
        $this->timers['execution'] = $this->timers['execution']->diff(new \DateTime());

    }

    protected function collectTaskReports()
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

    public function __destruct()
    {
        if ($this->pssh) {
            pssh_free($this->pssh);
        }
    }
}