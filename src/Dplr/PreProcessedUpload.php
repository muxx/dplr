<?php

namespace Dplr;

class PreProcessedUpload extends PreProcessedTask
{
    protected $source;
    protected $target;

    public function __construct(
        string $source,
        string $target,
        ?string $serverGroup = null, ?int $timeout = null, ?callable $onSuccess = null, ?callable $onFailure = null)
    {
        parent::__construct($serverGroup, $timeout, $onSuccess, $onFailure);

        $this->source = $source;
        $this->target = $target;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTarget(): string
    {
        return $this->target;
    }
}
