dplr
====

Object oriented deployer based on [pssh_extension](https://github.com/badoo/pssh_extension) + [libpssh](https://github.com/badoo/libpssh). Simple, fast, really fast.

Installation
------
Use composer to install **dplr**:
```
"require": {
    "muxx/dplr": "*"
}
```
**Important**: `dplr` requires php extension [pssh](https://github.com/badoo/pssh_extension) and library [libpssh](https://github.com/badoo/libpssh). [This article](https://github.com/muxx/dplr/wiki/Install-ssh2,-libpssh-and-pssh-extension) describes their installation. 

Usage
-----

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
    //upload `local_file1` on servers from group `front`
    ->upload('/path/to/local_file1', '/path/to/remote_file1', 'front')
    //execute command on all servers
    ->command(PATH . '/app/console cache:clear')
    //error command
    ->command('nonexistent_command', 'master')
    //download `remote_file2` from servers assigned group `master`
    ->download('/path/to/remote_file2', '/path/to/local_file2', 'master')
    ;

$dplr->run(function($step) {
    echo "$step...\n";
});

if (!$dplr->isSuccessful()) {
    foreach($dplr->getFailed() as $task) {
        echo $task->getErrorOutput() . "\n";
    }
}
```
