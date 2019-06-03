<?php

namespace Dplr;

/**
 * Object oriented deployer based on GoSSHa.
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class Dplr
{
    const DEFAULT_TIMEOUT = 3600;

    const STATE_INIT = 'init';
    const STATE_RUNNING = 'running';

    protected $servers = [];
    protected $tasks = [];
    protected $tasksThread = 0;
    protected $reports = [];
    protected $timers = [];

    protected $user;
    protected $publicKey;
    protected $gosshaPath;

    protected $defaultTimeout;

    // dplr state
    protected $state;

    public function __construct($user, $gosshaPath, $publicKey = null)
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->gosshaPath = $gosshaPath;

        $this->resetTasks();
        $this->state = self::STATE_INIT;

        $this->defaultTimeout = self::DEFAULT_TIMEOUT;
    }

    protected function resetTasks()
    {
        $this->tasks = [[]];
        $this->tasksThread = 0;
    }

    protected function checkState()
    {
        if (self::STATE_RUNNING == $this->state) {
            throw new \RuntimeException('Dplr is already running.');
        }
    }

    /**
     * Returns default timeout for tasks.
     *
     * @return int
     */
    public function getDefaultTimeout()
    {
        return $this->defaultTimeout;
    }

    /**
     * Set default timeout for tasks.
     *
     * @param int $timeout
     *
     * @return Dplr
     */
    public function setDefaultTimeout($timeout)
    {
        $this->defaultTimeout = (int) $timeout;

        return $this;
    }

    /**
     * Add server for deploying.
     *
     * @param mixed $serverName
     * @param mixed $groups     (default: null)
     *
     * @return Dplr
     */
    public function addServer($serverName, $groups = null)
    {
        $this->checkState();

        if ($groups && !is_array($groups)) {
            $groups = [$groups];
        }

        $this->servers[$serverName] = $groups;

        return $this;
    }

    /**
     * Return servers list.
     *
     * @return array
     */
    public function getServers()
    {
        return array_keys($this->servers);
    }

    /**
     * Return servers list of group.
     *
     * @param string $group
     *
     * @return array
     */
    public function getServersByGroup($group)
    {
        $servers = [];
        foreach ($this->servers as $serverName => $groups) {
            if (in_array($group, $groups)) {
                $servers[] = $serverName;
            }
        }

        return $servers;
    }

    /**
     * Check server group existing.
     *
     * @param string $group
     *
     * @return bool
     */
    public function hasGroup($group)
    {
        foreach ($this->servers as $serverName => $groups) {
            if (in_array($group, $groups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creating new thread.
     *
     * @return Dplr
     */
    public function newThread()
    {
        // if current thread is empty, use it
        if (!count($this->tasks[$this->tasksThread])) {
            return $this;
        }

        ++$this->tasksThread;
        $this->tasks[$this->tasksThread] = [];

        return $this;
    }

    /**
     * Adding command task.
     *
     * @param string $command
     * @param string $serverGroup (default: null)
     * @param int    $timeout     (default: null)
     *
     * @return Dplr
     */
    public function command($command, $serverGroup = null, $timeout = null)
    {
        if (!is_string($command)) {
            throw new \InvalidArgumentException('Command must be string');
        }

        if ($serverGroup && !is_string($serverGroup)) {
            throw new \InvalidArgumentException('Server group must be string');
        }

        $servers = null;
        if ($serverGroup) {
            $servers = $this->getServersByGroup($serverGroup);
            if (!count($servers)) {
                throw new \InvalidArgumentException(sprintf('Not found servers for group "%s"', $serverGroup));
            }
        }

        $this->checkState();

        $data = [
            'Action' => 'ssh',
            'Cmd' => $command,
            'Hosts' => $serverGroup ? $servers : $this->getServers(),
            'Timeout' => ((int) $timeout > 0 ? (int) $timeout : $this->defaultTimeout) * 1000,
        ];

        $this->tasks[$this->tasksThread][] = new Task($data);

        return $this;
    }

    /**
     * Adding uploading task.
     *
     * @param string $localFile
     * @param string $remoteFile
     * @param string $serverGroup (default: null)
     * @param int    $timeout     (default: null)
     *
     * @return Dplr
     */
    public function upload($localFile, $remoteFile, $serverGroup = null, $timeout = null)
    {
        if (!is_string($localFile)) {
            throw new \InvalidArgumentException('Local file must be string');
        }

        if (!is_string($remoteFile)) {
            throw new \InvalidArgumentException('Remote file must be string');
        }

        if ($serverGroup && !is_string($serverGroup)) {
            throw new \InvalidArgumentException('Server group must be string');
        }

        $servers = null;
        if ($serverGroup) {
            $servers = $this->getServersByGroup($serverGroup);
            if (!count($servers)) {
                throw new \InvalidArgumentException(sprintf('Not found servers for group "%s"', $serverGroup));
            }
        }

        $this->checkState();

        $data = [
            'Action' => 'scp',
            'Source' => $localFile,
            'Target' => $remoteFile,
            'Hosts' => $serverGroup ? $servers : $this->getServers(),
            'Timeout' => ((int) $timeout > 0 ? (int) $timeout : $this->defaultTimeout) * 1000,
        ];

        $this->tasks[$this->tasksThread][] = new Task($data);

        return $this;
    }

    /**
     * Run tasks on servers.
     *
     * @param callable $callback (default: null)
     *
     * @return Dplr
     */
    public function run(callable $callback = null)
    {
        $this->state = self::STATE_RUNNING;

        $this->runTasks($callback);

        $this->state = self::STATE_INIT;

        return $this;
    }

    /**
     * Check that all task executed successfully.
     *
     * @return bool
     */
    public function isSuccessful()
    {
        foreach ($this->reports as $report) {
            if (!$report->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return short report about task executing.
     *
     * @return array
     */
    public function getReport()
    {
        $result = [
            'total' => sizeof($this->reports),
            'successful' => 0,
            'failed' => 0,
            'timers' => [
                'execution' => $this->timers['execution']->format('%H:%I:%S'),
            ],
        ];

        foreach ($this->reports as $report) {
            if ($report->isSuccessful()) {
                ++$result['successful'];
            } else {
                ++$result['failed'];
            }
        }

        return $result;
    }

    /**
     * Return failed task reports.
     *
     * @return array
     */
    public function getFailed()
    {
        return array_filter($this->reports, function ($item) {
            return !$item->isSuccessful();
        });
    }

    /**
     * Return all reports.
     *
     * @return array
     */
    public function getReports()
    {
        return $this->reports;
    }

    protected function runTasks($callback = null)
    {
        $max = 0;
        // clear empty threads and search max thread
        foreach ($this->tasks as $i => $thread) {
            if (!count($thread)) {
                unset($this->tasks[$i]);
            } else {
                if (count($thread) > $max) {
                    $max = count($thread);
                }
            }
        }

        // reset reports
        $this->reports = [];

        if ($this->publicKey) {
            $pl = sprintf('%s -l %s -i %s', $this->gosshaPath, $this->user, $this->publicKey);
        } else {
            $pl = sprintf('%s -l %s', $this->gosshaPath, $this->user);
        }

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = array_fill(0, count($this->tasks), []);
        $processes = [];

        // run GoSSHa
        foreach ($this->tasks as $i => $thread) {
            $processes[$i] = proc_open($pl, $descriptorspec, $pipes[$i]);
            if (!is_resource($processes[$i])) {
                throw new \RuntimeException('Can not run GoSSHa.');
            }
        }

        $this->timers['execution'] = new \DateTime();

        // run tasks
        for ($j = 0; $j < $max; ++$j) {
            // send command
            $k = 0;
            foreach ($this->tasks as $i => $thread) {
                if (!isset($thread[$j])) {
                    continue;
                }

                $task = $thread[$j];
                if ($callback) {
                    call_user_func($callback, ($k > 0 ? "\n" : '') . (string) $task . ' ');
                }
                fwrite($pipes[$i][0], $task->getJson() . "\n");
                ++$k;
            }

            // read replies
            foreach ($this->tasks as $i => $thread) {
                if (!isset($thread[$j])) {
                    continue;
                }

                $task = $thread[$j];
                while (false !== ($stdout = fgets($pipes[$i][1]))) {
                    $data = json_decode($stdout, true);

                    if ('Reply' === $data['Type']) {
                        $report = new TaskReport($data, $task);
                        $this->reports[] = $report;

                        if ($callback) {
                            call_user_func($callback, $report->isSuccessful() ? '.' : 'E');
                        }
                    } elseif ('UserError' === $data['Type']) {
                        $this->reports[] = new TaskReport($data, $task);

                        if ($callback) {
                            call_user_func($callback, 'J');
                        }
                    } elseif ('FinalReply' === $data['Type']) {
                        $hosts = $data['TimedOutHosts'];
                        if (count($hosts)) {
                            foreach ($hosts as $host => $v) {
                                $d = [
                                    'Type' => TaskReport::TYPE_TIMEOUT,
                                    'Success' => false,
                                    'Hostname' => $host,
                                    'ErrorMsg' => "Command execution reached timeout.\n",
                                ];
                                $this->reports[] = new TaskReport($d, $task);

                                if ($callback) {
                                    call_user_func($callback, 'T');
                                }
                            }
                        }
                    }

                    // next task
                    if (in_array($data['Type'], ['FinalReply', 'UserError'])) {
                        break;
                    }
                }
            }

            if ($callback) {
                call_user_func($callback, "\n");
            }
        }

        $this->timers['execution'] = $this->timers['execution']->diff(new \DateTime());

        foreach ($pipes as $p) {
            foreach ($p as $pipe) {
                fclose($pipe);
            }
        }

        foreach ($processes as $process) {
            proc_close($process);
        }

        $this->resetTasks();
    }
}
