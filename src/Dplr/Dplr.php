<?php

declare(strict_types=1);

namespace Dplr;

use DateTime;
use InvalidArgumentException;
use OutOfRangeException;
use RuntimeException;

/**
 * Object oriented deployer based on GoSSHa.
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class Dplr
{
    private const DEFAULT_TIMEOUT = 3600;

    private const STATE_INIT = 'init';
    private const STATE_RUNNING = 'running';

    /**
     * @var array
     */
    protected $servers = [];

    /**
     * @var array
     */
    protected $tasks = [];

    /**
     * @var int
     */
    protected $tasksThread = 0;

    /**
     * @var array
     */
    protected $reports = [];

    /**
     * @var array
     */
    protected $timers = [];

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string|null
     */
    protected $publicKey;

    /**
     * @var string
     */
    protected $gosshaPath;

    /**
     * @var int
     */
    protected $defaultTimeout;

    // dplr state
    protected $state;

    public function __construct(string $user, string $gosshaPath, string $publicKey = null)
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->gosshaPath = $gosshaPath;

        $this->resetTasks();
        $this->state = self::STATE_INIT;

        $this->defaultTimeout = self::DEFAULT_TIMEOUT;
    }

    protected function resetTasks(): void
    {
        $this->tasks = [[]];
        $this->tasksThread = 0;
    }

    public function hasTasks(): bool
    {
        foreach ($this->tasks as $taskThread) {
            if (count($taskThread) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function checkState(): void
    {
        if (self::STATE_RUNNING === $this->state) {
            throw new RuntimeException('Dplr is already running.');
        }
    }

    /*
     * Returns default timeout for tasks.
     */
    public function getDefaultTimeout(): int
    {
        return $this->defaultTimeout;
    }

    /*
     * Set default timeout for tasks.
     */
    public function setDefaultTimeout(int $timeout): self
    {
        $this->defaultTimeout = $timeout;

        return $this;
    }

    /**
     * Add server for deploying.
     *
     * @param string            $serverName
     * @param string|array|null $groups     (default: null)
     */
    public function addServer(string $serverName, $groups = null): self
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
     */
    public function getServers(): array
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
    public function getServersByGroup(string $group): array
    {
        $servers = [];
        foreach ($this->servers as $serverName => $groups) {
            if (in_array($group, $groups, true)) {
                $servers[] = $serverName;
            }
        }

        return $servers;
    }

    /*
     * Check server group existing.
     */
    public function hasGroup(string $group): bool
    {
        foreach ($this->servers as $serverName => $groups) {
            if (in_array($group, $groups, true)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Creating new thread.
     */
    public function newThread(): self
    {
        // if current thread is empty, use it
        if (!count($this->tasks[$this->tasksThread])) {
            return $this;
        }

        ++$this->tasksThread;
        $this->tasks[$this->tasksThread] = [];

        return $this;
    }

    /*
     * Adding command task.
     */
    public function command(string $command, string $serverGroup = null, int $timeout = null): self
    {
        $servers = null;
        if (null !== $serverGroup) {
            $servers = $this->getServersByGroup($serverGroup);
            if (!count($servers)) {
                throw new InvalidArgumentException(sprintf('Not found servers for group "%s"', $serverGroup));
            }
        }

        $this->checkState();

        $data = [
            'Action' => Task::ACTION_SSH,
            'Cmd' => $command,
            'Hosts' => $serverGroup ? $servers : $this->getServers(),
            'Timeout' => ($timeout > 0 ? $timeout : $this->defaultTimeout) * 1000,
        ];

        $this->tasks[$this->tasksThread][] = new Task($data);

        return $this;
    }

    /*
     * Adding uploading task.
     */
    public function upload(string $localFile, string $remoteFile, string $serverGroup = null, int $timeout = null): self
    {
        $servers = null;
        if (null !== $serverGroup) {
            $servers = $this->getServersByGroup($serverGroup);
            if (!count($servers)) {
                throw new InvalidArgumentException(sprintf('Not found servers for group "%s"', $serverGroup));
            }
        }

        $this->checkState();

        $data = [
            'Action' => Task::ACTION_SCP,
            'Source' => $localFile,
            'Target' => $remoteFile,
            'Hosts' => $serverGroup ? $servers : $this->getServers(),
            'Timeout' => ($timeout > 0 ? $timeout : $this->defaultTimeout) * 1000,
        ];

        $this->tasks[$this->tasksThread][] = new Task($data);

        return $this;
    }

    /*
     * Run tasks on servers.
     */
    public function run(callable $callback = null): self
    {
        $this->state = self::STATE_RUNNING;

        $this->runTasks($callback);

        $this->state = self::STATE_INIT;

        return $this;
    }

    /*
     * Check that all task executed successfully.
     */
    public function isSuccessful(): bool
    {
        foreach ($this->reports as $report) {
            if (!$report->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /*
     * Return short report about task executing.
     */
    public function getReport(): array
    {
        $result = [
            'total' => count($this->reports),
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
     * @return array<TaskReport>
     */
    public function getFailed(): array
    {
        return array_filter($this->reports, static function (TaskReport $item) {
            return !$item->isSuccessful();
        });
    }

    /**
     * Return all reports.
     *
     * @return array<TaskReport>
     */
    public function getReports(): array
    {
        return $this->reports;
    }

    public function getSingleReportOutput(): ?string
    {
        if (!count($this->reports)) {
            throw new OutOfRangeException('Not found task reports.');
        }

        if (count($this->reports) > 1) {
            throw new OutOfRangeException('There are more than one task report.');
        }

        /** @var TaskReport $report */
        $report = $this->reports[0];

        return $report->getOutput();
    }

    protected function runTasks(callable $callback = null): void
    {
        $max = 0;
        // clear empty threads and search max thread
        foreach ($this->tasks as $i => $thread) {
            if (!count($thread)) {
                unset($this->tasks[$i]);
            } elseif (count($thread) > $max) {
                $max = count($thread);
            }
        }

        // reset reports
        $this->reports = [];

        if ($this->publicKey) {
            $pl = sprintf('%s -l %s -i %s', $this->gosshaPath, $this->user, $this->publicKey);
        } else {
            $pl = sprintf('%s -l %s', $this->gosshaPath, $this->user);
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = array_fill(0, count($this->tasks), []);
        $processes = [];

        // run GoSSHa
        foreach ($this->tasks as $i => $thread) {
            $processes[$i] = proc_open($pl, $descriptorSpec, $pipes[$i]);
            if (!is_resource($processes[$i])) {
                throw new RuntimeException('Can not run GoSSHa.');
            }
        }

        $this->timers['execution'] = new DateTime();

        // run tasks
        for ($j = 0; $j < $max; ++$j) {
            // send command
            $k = 0;
            foreach ($this->tasks as $i => $thread) {
                if (!isset($thread[$j])) {
                    continue;
                }

                /** @var Task $task */
                $task = $thread[$j];
                if ($callback) {
                    $callback(($k > 0 ? "\n" : '') . $task . ' ');
                }
                fwrite($pipes[$i][0], json_encode($task, JSON_THROW_ON_ERROR) . "\n");
                ++$k;
            }

            // read replies
            foreach ($this->tasks as $i => $thread) {
                if (!isset($thread[$j])) {
                    continue;
                }

                $task = $thread[$j];
                while (false !== ($stdout = fgets($pipes[$i][1]))) {
                    $data = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

                    if ('Reply' === $data['Type']) {
                        $report = new TaskReport($data, $task);
                        $this->reports[] = $report;

                        if ($callback) {
                            $callback($report->isSuccessful() ? '.' : 'E');
                        }
                    } elseif ('UserError' === $data['Type']) {
                        $this->reports[] = new TaskReport($data, $task);

                        if ($callback) {
                            $callback('J');
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
                                    $callback('T');
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
                $callback("\n");
            }
        }

        $this->timers['execution'] = $this->timers['execution']->diff(new DateTime());

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
