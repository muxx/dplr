## dplr

Object oriented deployer based on [pssh_extension](https://github.com/badoo/pssh_extension) + [libpssh](https://github.com/badoo/libpssh) which allows to execute tasks simultaneously and in parallel. Simple, fast, really fast. 

* [Installation](#installation)
* [Documentation](#documentation)
    * [Initialization](#initialization)
    * [Register servers](#register-servers)
    * [Register tasks](#register-tasks)
    * [Running](#running)
    * [Result processing](#result-processing)

## Usage

Example of usage:
```php
require 'vendor/autoload.php';

$dplr = new Dplr\Dplr('ssh-user', '/path/to/public.key', '/path/to/private.key');

$dplr
    ->addServer('front1.site.ru', 'front')
    ->addServer('front2.site.ru', 'front')
    ->addServer('job1.site.ru', ['job', 'master'])
    ->addServer('job2.site.ru', 'job')
    ;
}

const PATH = '/home/webmaster/product';

$dplr
    ->upload('/path/to/local_file1', '/path/to/remote_file1', 'front')
    ->command(PATH . '/app/console cache:clear')
    ->download('/path/to/remote_file2', '/path/to/local_file2', 'master')
    ;

$dplr->run(function($step) {
    echo $step;
});

if (!$dplr->isSuccessful()) {
    echo "Deploy completed with errors.\n";
    foreach($dplr->getFailed() as $task) {
        echo $task->getErrorOutput() . "\n";
    }
}
else {
    echo "Deploy completed successfully.\n";
}

$report = $dplr->getReport();
echo sprintf(
    "Tasks: %s total, %s successful, %s failed.\nTime of connection: %s\nTime of execution: %s\n",
    $report['total'],
    $report['successful'],
    $report['failed'],
    $report['timers']['connection'],
    $report['timers']['execution']
);
```
<a name="installation"></a>
## Installation

Use composer to install **dplr**:
```
"require": {
    "muxx/dplr": "dev-master"
}
```
**Important**: `dplr` requires php extension [pssh](https://github.com/badoo/pssh_extension) and library [libpssh](https://github.com/badoo/libpssh). [This article](https://github.com/muxx/dplr/wiki/Install-ssh2,-libpssh-and-pssh-extension) describes their installation.

<a name="documentation"></a>
## Documentation

<a name="initialization"></a>
### Initialization

Initialization of ssh authorization by key:
```php
$dplr = new Dplr\Dplr('ssh-user', '/path/to/public.key', '/path/to/private.key');
```

Initialization of ssh authorization by password:
```php
$dplr = new Dplr\Dplr('ssh-user', '/path/to/public.key', NULL, 'ssh-pas$word');
```

<a name="register-servers"></a>
### Register servers

Add multiply servers with adding in different group. Adding to groups allows you to execute tasks on servers of certain group.
```php
$dplr->addServer('1.2.3.4'); // Add server IP 1.2.3.4 without adding to group
$dplr->addServer('1.2.3.5', 'app'); // Add server IP 1.2.3.5 with adding to group 'app'
$dplr->addServer('1.2.3.6', ['app', 'cache']); // Add server IP 1.2.3.6 with adding to groups 'app' and 'cache'
$dplr->addServer('1.2.3.7:2222', ['cache']); // Add server IP 1.2.3.7 and ssh port 2222 with adding to group 'cache'
```

<a name="register-tasks"></a>
### Register tasks

`dplr` allows to register three types of tasks:
- Command executing
- Upload local file to remote server
- Download file from remote server to local

```php
$local = __DIR__;
$path = '/home/webmaster/project';

$dplr
    ->upload("$local/share/parameters.yml", "$path/app/config/parameters.yml")
    ->command("cd $path && ./app/console cache:clear --env=prod --no-debug", 'app', 15)
    ->download("$path/web/index.php", "$local/share/index.php", null, 10)
    ;
```

In example above file `parameters.yml` will be upload on all servers simultaneously and in parallel. Second task executes only on servers from group `app` (`1.2.3.5` and `1.2.3.6`) simultaneously. For second and third tasks defined execution timeouts (15 and 10 seconds correspondently).

<a name="running"></a>
### Running

Running is simple:
```php
$dplr->run();
```

Define callback if you want to show steps of execution:
```php
$dplr->run(function($step) {
    echo $step;
});

/*
    Output
    --
    Register servers...
    Connect to servers...
    Prepare tasks...
    Run tasks...
    CPY /home/webmaster/test/share/parameters.yml -> /home/webmaster/project/app/config/parameters.yml....
    CMD cd /home/webmaster/project && ./app/console doctrine:migration:migrate --env=prod --no-debug..
    Build report...
*/
```

Each dot at the end of task lines means executing of the one action (upload, command, download) on the one server.

<a name="result-processing"></a>
### Result processing

You can get the execution review or detail information about each task execution.

Display report:

```php
$report = $dplr->getReport();
echo sprintf(
    "Tasks: %s total, %s successful, %s failed.\nTime of connection: %s\nTime of execution: %s\n",
    $report['total'],
    $report['successful'],
    $report['failed'],
    $report['timers']['connection'],
    $report['timers']['execution']
));

/*
    Output
    --
    Tasks: 163 total, 163 successful, 0 failed.
    Time of connection: 00:10
    Time of execution: 08:25
*/
```

Detail information about each task:
```php
foreach($dplr->getTaskReports() as $task) {
    echo sprintf(
        "%s\n    Status: %s\n    Exit status: %s\n",
        $task,
        $task->getStatus(),
        $task->getExitStatus()
    ));
}

/*
    Output
    --
    CPY /home/webmaster/test/share/parameters.yml -> /home/webmaster/project/app/config/parameters.yml (54.194.27.92)
        Status: 3
        Exit status: -1
    CMD cd /home/webmaster/project && ./app/console doctrine:migration:migrate --env=prod --no-debug (54.194.27.92)
        Status: 3
        Exit status: 0
*/
```

Each element in arrays returned by `$dplr->getFailed()` and `$dplr->getTaskReport()` is instance of `Dplr\TaskReport\AbstractTaskReport` and has methods:
- `isSuccessful()` - task executing is successful
- `getServer()` - server where task executed
- `getStatus()` - status of task (can be `PSSH_TASK_ERROR` = 1, `PSSH_TASK_INPROGRESS` = 2, `PSSH_TASK_DONE` = 3)
- `getExitStatus()` - exit status of task (only for command tasks)
- `getOutput()` - output of successful task (only for command tasks)
- `getErrorOutput()` - output of error task (only for command tasks)
