<?php

namespace Dplr;

class PreProcessedCommand extends PreProcessedTask
{
    protected $cmd;

    public function __construct(
        string $cmd,
        ?string $serverGroup = null, ?int $timeout = null, ?callable $onSuccess = null, ?callable $onFailure = null)
    {
        parent::__construct($serverGroup, $timeout, $onSuccess, $onFailure);

        $this->cmd = $cmd;
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }
}
