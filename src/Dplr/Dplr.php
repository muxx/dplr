<?php

namespace Dplr;

/**
 * Object oriented deployer based on GoSSHa
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class Dplr
{
    const DEFAULT_TIMEOUT = 3600;

    protected $servers = [];
    protected $tasks = [];
    protected $reports = [];
    protected $timers = [];

    protected $user;
    protected $publicKey;
    protected $gosshaPath;

    public function __construct($user, $gosshaPath, $publicKey = null)
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->gosshaPath = $gosshaPath;
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
     * Adding command task
     *
     * @param  string $command
     * @param  string $serverGroup (default: null)
     * @param  int    $timeout     (default: 3600)
     * @return Dplr
     */
    public function command($command, $serverGroup = null, $timeout = self::DEFAULT_TIMEOUT)
    {
        $data = [
            'Action' => 'ssh',
            'Cmd' => $command,
            'Hosts' => $serverGroup ? $this->getServersByGroup($serverGroup) : $this->getServers(),
        ];

        if ($timeout) {
            $data['Timeout'] = (int) $timeout * 1000;
        }

        $this->tasks[] = new Task($data);

        return $this;
    }

    /**
     * Adding uploading task
     *
     * @access public
     * @param  string $localFile
     * @param  string $remoteFile
     * @param  string $serverGroup (default: null)
     * @param  int    $timeout     (default: 3600)
     * @return Dplr
     */
    public function upload($localFile, $remoteFile, $serverGroup = null, $timeout = self::DEFAULT_TIMEOUT)
    {
        $data = [
            'Action' => 'scp',
            'Source' => $localFile,
            'Target' => $remoteFile,
            'Hosts' => $serverGroup ? $this->getServersByGroup($serverGroup) : $this->getServers(),
        ];

        if ($timeout) {
            $data['Timeout'] = (int) $timeout * 1000;
        }

        $this->tasks[] = new Task($data);

        return $this;
    }

    /**
     * Run tasks on servers
     *
     * @access public
     * @param  callable $callback (default: null)
     * @return Dplr
     */
    public function run(callable $callback = null)
    {
        $this->runTasks($callback);

        return $this;
    }

    /**
     * Check that all task executed successfully
     *
     * @access public
     * @return boolean
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
     * Return short report about task executing
     *
     * @access public
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
                $result['successful']++;
            } else {
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
        return array_filter($this->reports, function ($item) {
            return !$item->isSuccessful();
        });
    }

    /**
     * Return all reports
     *
     * @access public
     * @return array
     */
    public function getReports()
    {
        return $this->reports;
    }

    protected function runTasks($callback = null)
    {
        if ($this->publicKey) {
            $pl = sprintf('%s -l %s -i %s', $this->gosshaPath, $this->user, $this->publicKey);
        } else {
            $pl = sprintf('%s -l %s', $this->gosshaPath, $this->user);
        }

        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        // run GoSSHa
        $process = proc_open($pl, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Can not run GoSSHa.');
        }

        $this->timers['execution'] = new \DateTime();

        // run tasks
        if (sizeof($this->tasks)) {
            foreach ($this->tasks as $task) {
                // send command
                if ($callback) {
                    call_user_func($callback, (string) $task . ' ');
                }
                fwrite($pipes[0], $task->getJson() . "\n");

                // read replies
                while (($stdout = fgets($pipes[1])) !== false) {
                    $data = json_decode($stdout, true);

                    if ($data['Type'] === 'Reply') {
                        $report = new TaskReport($data, $task);
                        $this->reports[] = $report;

                        if ($callback) {
                            call_user_func($callback, $report->isSuccessful() ? '.' : 'E');
                        }
                    } elseif ($data['Type'] === 'UserError') {
                        $this->reports[] = new TaskReport($data, $task);

                        if ($callback) {
                            call_user_func($callback, 'U');
                        }
                    } elseif ($data['Type'] === 'FinalReply') {
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
                        if ($callback) {
                            call_user_func($callback, "\n");
                        }
                        break;
                    }
                }
            }
        }

        $this->timers['execution'] = $this->timers['execution']->diff(new \DateTime());

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($process);
    }
}
