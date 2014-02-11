<?php

namespace Dplr\Task;

use Dplr\TaskReport\CommandTaskReport;

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

    /**
     * Add task to pssh task list for server
     *
     * @access public
     * @abstract
     * @return integer
     */
    protected function _addToPsshTaskList(&$psshTaskList, $server)
    {
        if (pssh_tasklist_add($psshTaskList, $server, $this->getCommand(), $this->getTimeout())) {
            return new CommandTaskReport($this->getCommand(), $server);
        }

        return false;
    }
}