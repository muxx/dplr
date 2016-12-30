## dplr

Object oriented deployer based on [GoSSHa](https://github.com/YuriyNasretdinov/GoSSHa) which allows to execute tasks simultaneously and in parallel. Simple and fast. 

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

$dplr = new Dplr\Dplr('ssh-user', '/path/to/GoSSHa');

$dplr
    ->addServer('front1.site.ru', 'front')
    ->addServer('front2.site.ru', 'front')
    ->addServer('job1.site.ru', ['job', 'master'])
    ->addServer('job2.site.ru', 'job')
;

const PATH = '/home/webmaster/product';

$dplr
    ->upload('/path/to/local_file1', '/path/to/remote_file1', 'front')
    ->command(PATH . '/app/console cache:clear')
;

$dplr->run(function($step) {
    echo $step;
});

if (!$dplr->isSuccessful()) {
    echo "Deploy completed with errors.\n";
    foreach($dplr->getFailed() as $item) {
        echo "[ " . $item . " ]\n" . $item->getErrorOutput() . "\n";
    }
}
else {
    echo "Deploy completed successfully.\n";
}

$report = $dplr->getReport();
echo sprintf(
    "Tasks: %s total, %s successful, %s failed.\nTime of execution: %s\n",
    $report['total'],
    $report['successful'],
    $report['failed'],
    $report['timers']['execution']
);
```
<a name="installation"></a>
## Installation

Use composer to install **dplr**:
```
"require": {
    "muxx/dplr": "~1.0"
}
```
**Important**: `dplr` requires [GoSSHa](https://github.com/YuriyNasretdinov/GoSSHa).

<a name="documentation"></a>
## Documentation

<a name="initialization"></a>
### Initialization

Initialization of ssh authorization by key:
```php
$dplr = new Dplr\Dplr('ssh-user', '/path/to/GoSSHa');

// or

$dplr = new Dplr\Dplr('ssh-user', '/path/to/GoSSHa', '/path/to/public.key');
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

`dplr` allows to register two types of tasks:
- Command executing
- Upload local file to remote server

```php
$local = __DIR__;
$path = '/home/webmaster/project';

$dplr
    ->upload("$local/share/parameters.yml", "$path/app/config/parameters.yml")
    ->command("cd $path && ./app/console cache:clear --env=prod --no-debug", 'app', 15)
    ;
```

In example above file `parameters.yml` will be upload on all servers simultaneously and in parallel. Second task executes only on servers from group `app` (`1.2.3.5` and `1.2.3.6`) simultaneously. For second task defined execution timeouts (15 seconds).

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
    CPY /home/webmaster/test/share/parameters.yml -> /home/webmaster/project/app/config/parameters.yml ..T.
    CMD cd /home/webmaster/project && ./app/console doctrine:migration:migrate --env=prod --no-debug .E
*/
```

Each dot at the end of task lines means executing of the one action (upload, command) on the certain server. Mark `E` is indicator of failed executing. Mark `U` is indicator of json parsing error. Mark `T` is indicator of executing timeout.

<a name="result-processing"></a>
### Result processing

You can get the execution review or detail information about each task execution.

Display report:

```php
$report = $dplr->getReport();
echo sprintf(
    "Tasks: %s total, %s successful, %s failed.\nTime of execution: %s\n",
    $report['total'],
    $report['successful'],
    $report['failed'],
    $report['timers']['execution']
);

/*
    Output
    --
    Tasks: 163 total, 163 successful, 0 failed.
    Time of execution: 08:25
*/
```

Detail information about each task:
```php
foreach($dplr->getReports() as $report) {
    echo sprintf(
        "%s\n    Successful: %s\n",
        (string) $report,
        $report->isSuccessful() ? 'true' : 'false'
    );
}

/*
    Output
    --
    CPY /home/webmaster/test/share/parameters.yml -> /home/webmaster/project/app/config/parameters.yml | 54.194.27.92
        Successful: false
    CMD cd /home/webmaster/project && ./app/console doctrine:migration:migrate --env=prod --no-debug | 54.194.27.92
        Successful: true
*/
```

Each element in arrays returned by `$dplr->getFailed()` and `$dplr->getReports()` is instance of `Dplr\TaskReport` and has methods:
- `isSuccessful()` - task executing is successful
- `getHost()` - server where task executed
- `getTask()` - information about task (instance of `Dplr\Task`)
- `getOutput()` - output of task
- `getErrorOutput()` - output of error task
