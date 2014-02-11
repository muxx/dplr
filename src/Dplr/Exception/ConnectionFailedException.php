<?php

namespace Dplr\Exception;

/**
 * Connection failed exception
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class ConnectionFailedException extends \RuntimeException
{
    private $servers;

    public function __construct(array $servers)
    {
        $servers = array_keys($servers);

        parent::__construct(
            sprintf(
                'Failed to connect to servers: %s.',
                '"' . implode('", "', $servers)
            )
        );

        $this->servers = $servers;
    }

    public function getServers()
    {
        return $this->servers;
    }
}