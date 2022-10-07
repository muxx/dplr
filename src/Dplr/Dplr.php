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

    protected $servers = [];

    protected $tasks = [];

    protected $multipleThread = -1;

    protected $reports = [];

    protected $timers = [];

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string|null
     */
    protected $publicKey;

    /** @var int */
    protected $maxSSHAgentConnections;

    /** @var int */
    protected $maxConcurrency;

    /**
     * @var string
     */
    protected $gosshaPath;

    protected $defaultTimeout = self::DEFAULT_TIMEOUT;

    protected $state = self::STATE_INIT;

    public function __construct(
        string $user,
        string $gosshaPath,
        string $publicKey = null,
        int $maxSSHAgentConnections = 128,
        int $maxConcurrency = 0
    ) {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->gosshaPath = $gosshaPath;
        $this->maxSSHAgentConnections = $maxSSHAgentConnections;
        $this->maxConcurrency = $maxConcurrency;

        $this->resetTasks();
    }

    protected function resetTasks(): void
    {
        $this->tasks = [
            [],
        ];
        $this->multipleThread = -1;
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
     * @param string|array|null $groups (default: null)
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

    /*
     * Return servers list.
     */
    public function getServers(): array
    {
        return array_keys($this->servers);
    }

    /*
     * Return servers list of group.
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
     * Start the multiple command chain which have to execute in parallel.
     */
    public function multi(): self
    {
        $this->multipleThread = 0;

        return $this;
    }

    /*
     * End the multiple command chain
     */
    public function end(): self
    {
        $this->multipleThread = -1;

        return $this;
    }

    private function addTask(Task $task): self
    {
        if (-1 === $this->multipleThread) {
            $this->tasks[0][] = $task;

            return $this;
        }

        // fill previous steps by empty tasks to sync with the main thread
        if ($this->multipleThread > 0) {
            // init thread if not exists
            if (!isset($this->tasks[$this->multipleThread])) {
                $this->tasks[$this->multipleThread] = [];
            }

            $mainThreadCount = count($this->tasks[0]);
            $currentThreadCount = count($this->tasks[$this->multipleThread]);
            if ($mainThreadCount <= $currentThreadCount) {
                throw new RuntimeException(sprintf('Thread #%d is bigger than main thread', $this->multipleThread));
            }

            if ($currentThreadCount < $mainThreadCount - 1) {
                for ($i = $currentThreadCount; $i < $mainThreadCount - 1; ++$i) {
                    $this->tasks[$this->multipleThread][] = null;
                }
            }
        }

        $this->tasks[$this->multipleThread++][] = $task;

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

        $this->addTask(new Task($data));

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

        $this->addTask(new Task($data));

        return $this;
    }

    /*
     * Run tasks on servers.
     */
    public function run(callable $callback = null): self
    {
        $this->state = self::STATE_RUNNING;

        $this->runTasks($callback);
        $this->resetTasks();

        $this->state = self::STATE_INIT;

        return $this;
    }

    /**
     * Check that all task executed successfully.
     *
     * @phpstan-impure
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
            $pl = sprintf(
                '%s -l %s -i %s -m %d -c %d',
                $this->gosshaPath,
                $this->user,
                $this->publicKey,
                $this->maxConcurrency,
                $this->maxSSHAgentConnections
            );
        } else {
            $pl = sprintf(
                '%s -l %s -m %d -c %d',
                $this->gosshaPath,
                $this->user,
                $this->maxConcurrency,
                $this->maxSSHAgentConnections
            );
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = array_fill(0, count($this->tasks), []);
        $processes = [];

        // run GoSSHa instance for each thread
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
                if (!isset($thread[$j]) || !$thread[$j]) {
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
                if (!isset($thread[$j]) || !$thread[$j]) {
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
    }
}
