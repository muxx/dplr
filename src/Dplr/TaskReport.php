<?php

namespace Dplr;

class TaskReport
{
    const TYPE_USER_ERROR = 'UserError';
    const TYPE_REPLY = 'Reply';
    const TYPE_TIMEOUT = 'Timeout';

    protected $data;
    protected $task;

    public function __construct(array $data, Task $task = null)
    {
        if (!isset($data['Type'])) {
            throw new \InvalidArgumentException('Not found `Type` parameter.');
        }
        if (!in_array($data['Type'], self::getTypes())) {
            throw new \InvalidArgumentException(sprintf(
                'Type "%s" not allowed. Allowed types: %s',
                $data['Type'],
                self::getTypes()
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

    public function isSuccessful()
    {
        return self::TYPE_REPLY === $this->getType() && (bool) $this->data['Success'];
    }

    public static function getTypes()
    {
        return [self::TYPE_REPLY, self::TYPE_USER_ERROR, self::TYPE_TIMEOUT];
    }

    public function setTask(Task $task)
    {
        $this->task = $task;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getHost()
    {
        return $this->data['Hostname'];
    }

    public function getType()
    {
        return $this->data['Type'];
    }

    public function getOutput()
    {
        if (!isset($this->data['Stdout'])) {
            return;
        }

        return $this->data['Stdout'];
    }

    public function getErrorOutput()
    {
        if (isset($this->data['Stderr'])) {
            return $this->data['Stderr'] ?: $this->data['Stdout'];
        } elseif (isset($this->data['ErrorMsg'])) {
            return $this->data['ErrorMsg'];
        }

        return;
    }
}
