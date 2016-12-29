<?php

namespace Dplr;

class Task
{
    const ACTION_SSH = 'ssh';
    const ACTION_SCP = 'scp';

    protected $parameters = [];

    public function __construct(array $parameters)
    {
        if (!isset($parameters['Action'])) {
            throw new \InvalidArgumentException('Not found `Action` parameter.');
        }
        if (!in_array($parameters['Action'], self::allowedActions())) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" not allowed. Allowed actions: %s',
                $parameters['Action'],
                self::allowedActions()
            ));
        }

        $this->parameters = $parameters;
    }

    public function __toString()
    {
        if ($this->parameters['Action'] == self::ACTION_SSH) {
            return sprintf("CMD %s", $this->parameters['Cmd']);
        } elseif ($this->parameters['Action'] == self::ACTION_SCP) {
            return sprintf("CPY %s -> %s", $this->parameters['Source'], $this->parameters['Target']);
        }

        return "";
    }

    public static function allowedActions()
    {
        return [self::ACTION_SSH, self::ACTION_SCP];
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getJson()
    {
        return json_encode($this->parameters);
    }
}
