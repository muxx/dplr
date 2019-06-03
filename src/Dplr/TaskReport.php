<?php

declare(strict_types=1);

namespace Dplr;

class TaskReport
{
    public const TYPE_USER_ERROR = 'UserError';
    public const TYPE_REPLY = 'Reply';
    public const TYPE_TIMEOUT = 'Timeout';

    public const TYPES = [
        self::TYPE_USER_ERROR,
        self::TYPE_REPLY,
        self::TYPE_TIMEOUT,
    ];

    /**
     * @var array
     */
    protected $data;

    /**
     * @var Task
     */
    protected $task;

    public function __construct(array $data, Task $task = null)
    {
        if (!isset($data['Type'])) {
            throw new \InvalidArgumentException('Not found `Type` parameter.');
        }
        if (!in_array($data['Type'], self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type "%s" not allowed. Allowed types: %s',
                $data['Type'],
                self::TYPES
            ));
        }

        $this->data = $data;

        if ($task) {
            $this->task = $task;
        }
    }

    public function __toString()
    {
        if (!$this->getHost()) {
            return (string) $this->task;
        }

        return sprintf('%s | %s', (string) $this->task, $this->getHost());
    }

    public function isSuccessful(): bool
    {
        return self::TYPE_REPLY === $this->getType() && (bool) $this->data['Success'];
    }

    public function setTask(Task $task)
    {
        $this->task = $task;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getHost(): string
    {
        return $this->data['Hostname'];
    }

    public function getType(): string
    {
        return $this->data['Type'];
    }

    public function getOutput(): ?string
    {
        if (!isset($this->data['Stdout'])) {
            return null;
        }

        return $this->data['Stdout'];
    }

    public function getErrorOutput(): ?string
    {
        if (isset($this->data['Stderr'])) {
            return $this->data['Stderr'] ?: $this->data['Stdout'];
        }

        if (isset($this->data['ErrorMsg'])) {
            return $this->data['ErrorMsg'];
        }

        return null;
    }
}
