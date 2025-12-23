<?php

namespace Dplr;

class TaskList
{
    /** @var PreProcessedTask[] */
    private array $tasks = [];

    /*
     * Adding command task.
     */
    public function command(
        string $command,
        ?string $serverGroup = null,
        ?int $timeout = null,
        ?callable $onSuccess = null,
        ?callable $onFailure = null,
    ): self {
        $this->tasks[] = new PreProcessedCommand(
            $command,
            $serverGroup,
            $timeout,
            $onSuccess,
            $onFailure,
        );

        return $this;
    }

    /*
     * Adding uploading task.
     */
    public function upload(
        string $localFile,
        string $remoteFile,
        ?string $serverGroup = null,
        ?int $timeout = null,
        ?callable $onSuccess = null,
        ?callable $onFailure = null,
    ): self {
        $this->tasks[] = new PreProcessedUpload(
            $localFile,
            $remoteFile,
            $serverGroup,
            $timeout,
            $onSuccess,
            $onFailure,
        );

        return $this;
    }

    /** @return PreProcessedTask[] */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function isEmpty(): bool
    {
        return [] === $this->tasks;
    }
}
