<?php

declare(strict_types=1);

namespace Dplr;

class Task
{
    public const ACTION_SSH = 'ssh';
    public const ACTION_SCP = 'scp';
    public const ACTIONS = [self::ACTION_SSH, self::ACTION_SCP];

    protected $parameters = [];

    public function __construct(array $parameters)
    {
        if (!isset($parameters['Action'])) {
            throw new \InvalidArgumentException('Not found `Action` parameter.');
        }
        if (!in_array($parameters['Action'], self::ACTIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" not allowed. Allowed actions: %s',
                $parameters['Action'],
                self::ACTIONS
            ));
        }

        $this->parameters = $parameters;
    }

    public function __toString()
    {
        if (self::ACTION_SSH === $this->parameters['Action']) {
            return sprintf('CMD %s', $this->parameters['Cmd']);
        }

        if (self::ACTION_SCP === $this->parameters['Action']) {
            return sprintf('CPY %s -> %s', $this->parameters['Source'], $this->parameters['Target']);
        }

        return '';
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getJson(): string
    {
        $json = json_encode($this->parameters);
        if (false === $json) {
            throw new \InvalidArgumentException('Cannot encode parameters to json.');
        }

        return $json;
    }
}
