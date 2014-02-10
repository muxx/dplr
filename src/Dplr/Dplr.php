<?php

namespace Dplr;

/**
 * Object oriented deployer based on pssh_extension + libpssh
 *
 * @author Ilyas Salikhov <me@salikhovilyas.ru>
 */
class Dplr
{
   /*
    * pssh constants
    *
    * connection status:
    *   PSSH_CONNECTED
    *   PSSH_RUNNING
    *   PSSH_TIMEOUT
    *   PSSH_FAILED
    *   PSSH_SUCCESS
    *
    * task execution result
    *   PSSH_TASK_NONE
    *   PSSH_TASK_ERROR
    *   PSSH_TASK_INPROGRESS
    *   PSSH_TASK_DONE
    *
    * task types
    *   PSSH_TASK_TYPE_COPY
    *   PSSH_TASK_TYPE_EXEC
    *
    */

    protected
        $pssh,
        $psshTasks,

        $servers = array(),
        $tasks = array();

    public function __construct($user, $publicKey, $privateKey = null, $password = null)
    {
        $this->pssh = pssh_init($user, $publicKey, $privateKey, $password);
    }

    /**
     * Add server for deploying
     *
     * @access public
     * @param mixed $serverName
     * @param mixed $groups (default: null)
     * @return void
     */
    public function addServer($serverName, $groups = null)
    {
        if ($groups && !is_array($groups)) {
            $groups = array($groups);
        }

        $this->servers[] = array(
            'server_name' => $serverName,
            'groups' => $groups,
        );

        return $this;
    }

    public function addTask(Task\AbstractTask $task)
    {
        if ($group = $task->getServerGroup()) {
            $contain = false;
            foreach($this->servers as $server) {
                if (in_array($group, $server['groups'])) {
                    $contain = true;
                }
            }

            if (!$contain) {
                throw new \Exception(sprintf('Server group "%s" is not defined in current session.', $group));
            }
        }

        $this->tasks[] = $task;

        return $this;
    }

    public function __destruct()
    {
        if ($this->pssh) {
            pssh_free($this->pssh);
        }
    }
}