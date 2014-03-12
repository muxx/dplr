<?php

namespace Dplr;

use Dplr\Exception\ConnectionFailedException;
use Dplr\Task\AbstractTask;
use Dplr\Task\CommandTask;
use Dplr\Task\DownloadTask;
use Dplr\Task\UploadTask;

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

    const STATE_REGISTER_SERVERS = "Register servers...\n";
    const STATE_CONNECT_TO_SERVERS = "Connect to servers...\n";
    const STATE_PREPARE_TASKS = "Prepare tasks...\n";
    const STATE_RUN_TASKS = "Run tasks...\n";
    const STATE_BUILD_REPORT = "Build report...\n";

    protected $pssh;
    protected $tasksArePrepared = false;

    protected $servers     = array();
    protected $tasks       = array();
    protected $taskReports = array();
    protected $timers      = array();

    protected $connTimeout;

    public function __construct(
        $user,
        $publicKey,
        $privateKey = null,
        $password = null,
        $connTimeout = 3,
        $connOptions = null
    )
    {
        $this->pssh = pssh_init($user, $publicKey, $privateKey, $password, $connOptions);

        if ($connTimeout) {
            $this->connTimeout = $connTimeout;
        }
    }

    /**
     * Add server for deploying
     *
     * @access public
     * @param  mixed $serverName
     * @param  mixed $groups     (default: null)
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
     * @param  mixed $group
     * @return array
     */
    public function getServersByGroup($group)
    {
        $servers = array();
        foreach ($this->servers as $serverName => $groups) {
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
     * @param  AbstractTask $task
     * @return Dplr
     */
    public function addTask(AbstractTask $task)
    {
        if ($group = $task->getServerGroup()) {
            $contain = false;
            foreach ($this->servers as $serverName => $groups) {
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
     * Alias for adding CommandTask
     *
     * @access public
     * @param  mixed $command     (default: null)
     * @param  mixed $serverGroup (default: null)
     * @param  mixed $timeout     (default: null)
     * @return Dplr
     */
    public function command($command = null, $serverGroup = null, $timeout = null)
    {
        return $this->addTask(new CommandTask($command, $serverGroup, $timeout));
    }

    /**
     * Alias for adding UploadTask
     *
     * @access public
     * @param  mixed $localFile   (default: null)
     * @param  mixed $remoteFile  (default: null)
     * @param  mixed $serverGroup (default: null)
     * @param  mixed $timeout     (default: null)
     * @return Dplr
     */
    public function upload($localFile = null, $remoteFile = null, $serverGroup = null, $timeout = null)
    {
        return $this->addTask(new UploadTask($localFile, $remoteFile, $serverGroup, $timeout));
    }

    /**
     * Alias for adding DownloadTask
     *
     * @access public
     * @param  mixed $remoteFile  (default: null)
     * @param  mixed $localFile   (default: null)
     * @param  mixed $serverGroup (default: null)
     * @param  mixed $timeout     (default: null)
     * @return Dplr
     */
    public function download($remoteFile = null, $localFile = null, $serverGroup = null, $timeout = null)
    {
        return $this->addTask(new DownloadTask($remoteFile, $localFile, $serverGroup, $timeout));
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

        //define servers which involved in tasks
        $servers = [];
        foreach ($this->tasks as $task) {
            $servers = array_merge($servers, $task->getServers());
        }
        array_unique($servers);

        foreach ($this->servers as $serverName => $groups) {
            if (!in_array($serverName, $servers)) {
                continue;
            }

            if (strpos($serverName, ':') !== false) {
                list($serverName, $port) = explode(':', $serverName);
                pssh_server_add($this->pssh, $serverName, $port);
            } else {
                pssh_server_add($this->pssh, $serverName);
            }
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
            $ret = pssh_connect($this->pssh, $server, $this->connTimeout);
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

    /**
     * Init pssh tasks lists
     *
     * @access protected
     * @return void
     */
    protected function prepareTasks()
    {
        if ($this->tasksArePrepared) {
            throw new \LogicException('Tasks already prepared.');
        }

        foreach ($this->tasks as $task) {
            $task->init($this->pssh);
        }

        $this->tasksArePrepared = true;
    }

    /**
     * Run tasks on servers
     *
     * @access public
     * @param  mixed $callback (default: null)
     * @return void
     */
    public function run($callback = null)
    {
        if ($callback && !is_callable($callback)) {
            throw new \InvalidArgumentException(sprintf('run() expects parameter 1 to be callable, %s given.', gettype($callback)));
        }

        if ($callback) {
            call_user_func($callback, self::STATE_REGISTER_SERVERS);
        }
        $this->registerServers();

        if ($callback) {
            call_user_func($callback, self::STATE_CONNECT_TO_SERVERS);
        }
        $this->connectToServers();

        //unroll server groups in server lists and add tasks
        if ($callback) {
            call_user_func($callback, self::STATE_PREPARE_TASKS);
        }
        $this->prepareTasks();

        if ($callback) {
            call_user_func($callback, self::STATE_RUN_TASKS);
        }
        $this->runTasks($callback);

        if ($callback) {
            call_user_func($callback, self::STATE_BUILD_REPORT);
        }
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
        foreach ($this->taskReports as $report) {
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

        foreach ($this->taskReports as $report) {
            if ($report->isSuccessful()) {
                $result['successful']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
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
     * Return failed task reports
     *
     * @access public
     * @return array
     */
    public function getFailed()
    {
        $result = array();
        foreach ($this->taskReports as $report) {
            if (!$report->isSuccessful()) {
                $result[] = $report;
            }
        }

        return $result;
    }

    protected function runTasks($callback = null)
    {
        $this->timers['execution'] = new \DateTime();

        foreach ($this->tasks as $task) {
            $task->run($callback);
        }

        $this->timers['execution'] = $this->timers['execution']->diff(new \DateTime());
    }

    protected function collectTaskReports()
    {
        foreach ($this->tasks as $task) {
            $task->collectResults();
            $this->taskReports = array_merge(
                $this->taskReports,
                $task->getTaskReports()
            );
        }
    }

    public function __destruct()
    {
        if ($this->pssh) {
            pssh_free($this->pssh);
        }
    }
}
