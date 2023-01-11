<?php

namespace Dplr\Tests;

use Dplr\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testNoAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not found `Action` parameter.');

        new Task([]);
    }

    public function testInvalidAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action "aaa" not allowed. Allowed actions: ssh, scp.');

        new Task(['Action' => 'aaa']);
    }

    public function testSshTask(): void
    {
        $task = new Task([
            'Action' => 'ssh',
            'Cmd' => 'ls -al',
        ]);

        $this->assertEquals('CMD ls -al', (string) $task);
        $this->assertJsonStringEqualsJsonString('{"Action":"ssh","Cmd":"ls -al"}', json_encode($task));
    }

    public function testScpTask(): void
    {
        $task = new Task([
            'Action' => 'scp',
            'Source' => '/home/user1/1.txt',
            'Target' => '/home/user2/2.txt',
        ]);

        $this->assertEquals('CPY /home/user1/1.txt -> /home/user2/2.txt', (string) $task);
        $this->assertJsonStringEqualsJsonString(
            '{"Action":"scp","Source":"/home/user1/1.txt","Target":"/home/user2/2.txt"}',
            json_encode($task)
        );
    }

    public function testCallbacks(): void
    {
        $success = false;
        $failure = false;

        $task = new Task(
            [
                'Action' => 'scp',
                'Source' => '/home/user1/1.txt',
                'Target' => '/home/user2/2.txt',
            ],
            function () use (&$success) {
                $success = true;
            },
            function () use (&$failure) {
                $failure = true;
            }
        );

        $this->assertEquals('CPY /home/user1/1.txt -> /home/user2/2.txt', (string) $task);
        $this->assertJsonStringEqualsJsonString(
            '{"Action":"scp","Source":"/home/user1/1.txt","Target":"/home/user2/2.txt"}',
            json_encode($task)
        );
        $task->callOnSuccess();
        $this->assertTrue($success);
        $task->callOnFailure();
        $this->assertTrue($failure);
    }
}
