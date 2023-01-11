<?php

declare(strict_types=1);

namespace Dplr;

class Task implements \JsonSerializable
{
    public const ACTION_SSH = 'ssh';
    public const ACTION_SCP = 'scp';
    public const ACTIONS = [self::ACTION_SSH, self::ACTION_SCP];

    protected $parameters = [];
    protected $onSuccess;
    protected $onFailure;

    public function __construct(
        array $parameters,
        callable $onSuccess = null,
        callable $onFailure = null
    ) {
        if (!isset($parameters['Action'])) {
            throw new \InvalidArgumentException('Not found `Action` parameter.');
        }
        if (!in_array($parameters['Action'], self::ACTIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" not allowed. Allowed actions: %s.',
                $parameters['Action'],
                implode(', ', self::ACTIONS)
            ));
        }

        $this->parameters = $parameters;
        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
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

    public function jsonSerialize(): array
    {
        return $this->parameters;
    }

    public function callOnSuccess(): void
    {
        if (null !== $this->onSuccess) {
            call_user_func($this->onSuccess);
        }
    }

    public function callOnFailure(): void
    {
        if (null !== $this->onFailure) {
            call_user_func($this->onFailure);
        }
    }
}
