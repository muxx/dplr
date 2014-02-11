<?php

namespace Dplr\TaskReport;

/**
 * Information about command task executed on some server
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class CommandTaskReport extends AbstractTaskReport
{
    protected $command;

    public function __construct($command = null, $server = null)
    {
        if ($command) {
            $this->setCommand($command);
        }

        parent::__construct($server);
    }

    public function __toString()
    {
        $str =
            'CMD'
            . ($this->getCommand() ? ' ' . $this->getCommand() : '')
            . ($this->getServer() ? ' (' . $this->getServer() . ')' : '')
            ;

        return $str;
    }

    public function getPsshTaskType()
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
     * Set task command
     *
     * @access public
     * @param mixed $command
     * @return AbstractTaskReport
     */
    public function setCommand($command)
    {
        $this->command = $command;

        return $this;
    }
}
