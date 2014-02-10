<?php

namespace Dplr\Task;

/**
 * Task which execute command on remote server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class CommandTask extends AbstractTask
{
    protected $command;

    public function __construct($command = null, $serverGroup = null, $timeout = null)
    {
        if ($command) {
            $this->setCommand($command);
        }
        parent::__construct($serverGroup, $timeout);
    }

    public function getPsshType()
    {
        return PSSH_TASK_TYPE_EXEC;
    }

    /**
     * Return task command
     *
     * @access public
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Set command for task
     *
     * @access public
     * @param mixed $command
     * @return CommandTask
     */
    public function setCommand($command)
    {
        $this->command = $command;

        return $this;
    }

}