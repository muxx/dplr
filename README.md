dplr
====

Object oriented deployer based on [pssh_extension](https://github.com/badoo/pssh_extension) + [libpssh](https://github.com/badoo/libpssh). Simple, fast, asynchronous.

Usage
-----

Example of usage:
```php
require 'vendor/autoload.php';

use Dplr\Dplr, Dplr\Task;

$dplr = new Dplr('ssh-user', '/path/to/public.key', '/path/to/private.key');

$dplr
    ->addServer('front1.site.ru', 'front')
    ->addServer('front2.site.ru', 'front')
    ->addServer('job1.site.ru', ['job', 'master'])
    ->addServer('job2.site.ru', 'job')
    ;
}

const PATH = '/home/webmaster/product';

$dplr
    //upload `local_file1` on servers from group `front`
    ->addTask(new Task\UploadTask('/path/to/local_file1', '/path/to/remote_file1', 'front'))
    //execute command on all servers
    ->addTask(new Task\CommandTask(PATH . '/app/console cache:clear'))
    //error command
    ->addTask(new Task\CommandTask('nonexistent_command', 'master'))
    //download `remote_file2` from servers assigned group `master`
    ->addTask(new Task\DownloadTask('/path/to/remote_file2', '/path/to/local_file2', 'master'))
    ;

$dplr->run();

if ($dplr->isSuccessful()) {
    //all goods
}
else {
    //some errors

    foreach($dplr->getFailed() as $task) {
        //echo "$task\n" . $task->getErrorOutput() . "\n\n";
    }
}

print_r($dplr->getReport());
/*
Array
(
    [total] => 8
    [successful] => 7
    [failed] => 1
    [timers] => Array
        (
            [connection] => 00:11
            [execution] => 00:01
        )

)
*/
```
