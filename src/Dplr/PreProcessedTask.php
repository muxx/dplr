<?php

namespace Dplr;

abstract class PreProcessedTask
{
    protected $timeout;
    protected $serverGroup;
    protected $onSuccess;
    protected $onFailure;

    public function __construct(
        ?string $serverGroup = null,
        ?int $timeout = null,
        ?callable $onSuccess = null,
        ?callable $onFailure = null,
    ) {
        $this->serverGroup = $serverGroup;
        $this->timeout = $timeout;
        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getServerGroup(): ?string
    {
        return $this->serverGroup;
    }

    public function getOnSuccess(): ?callable
    {
        return $this->onSuccess;
    }

    public function getOnFailure(): ?callable
    {
        return $this->onFailure;
    }
}
