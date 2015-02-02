<?php

namespace Dplr\TaskReport;

/**
 * Information about callback task
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class CallbackTaskReport extends AbstractTaskReport
{
    protected $name;

    public function __construct($name = null, $server = null)
    {
        if ($name) {
            $this->setName($name);
        }

        parent::__construct($server);
    }

    public function __toString()
    {
        $str =
            'CBK'
            . ($this->getName() ? ' ' . $this->getName() : '')
            . ($this->getServer() ? ' (' . $this->getServer() . ')' : '')
            ;

        return $str;
    }

    public function getPsshTaskType()
    {
        return PSSH_TASK_TYPE_CALLBACK;
    }

    /**
     * Return task callback name
     *
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set task callback name
     *
     * @access public
     * @param  mixed              $name
     * @return AbstractTaskReport
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
