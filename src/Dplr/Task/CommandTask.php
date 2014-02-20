<?php

namespace Dplr\Task;

use Dplr\TaskReport\CommandTaskReport;

/**
 * Task which execute command(s) on remote server(s)
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class CommandTask extends AbstractTask
{
    protected $command;

    public function __construct($command, $serverGroup = null, $timeout = null)
    {
        $this->setCommand($command);

        parent::__construct($serverGroup, $timeout);
    }

    public function __toString()
    {
        $command = $this->getCommand();

        return 'CMD ' . (is_array($command) ? implode(' | ', $command) : $command);
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
     * @access protected
     * @return integer
     */
    protected function initTaskList()
    {
        $commands = $this->getCommand();
        if (!is_array($commands)) {
            $commands = array($commands);
        }

        foreach($commands as $command) {
            foreach($this->getServers() as $server) {
                if (!pssh_tasklist_add($this->psshTaskHandler, $server, $command, $this->getTimeout())) {
                    throw new \RuntimeException(sprintf('Failed to add command task "%s" for server %s', $command, $server));
                }

                $this->taskReports[] = new CommandTaskReport($command, $server);
            }
        }

        return true;
    }
}