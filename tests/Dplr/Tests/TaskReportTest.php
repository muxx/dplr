<?php

namespace Dplr\Tests;

use Dplr\Task;
use Dplr\TaskReport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaskReportTest extends TestCase
{
    public function testNoType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not found `Type` parameter.');

        new TaskReport([]);
    }

    public function testInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Type "aaa" not allowed. Allowed types: UserError, Reply, Timeout.');

        new TaskReport(['Type' => 'aaa']);
    }

    public function testNotSuccessfulSystem()
    {
        $taskReport = new TaskReport(['Type' => TaskReport::TYPE_TIMEOUT]);
        $this->assertFalse($taskReport->isSuccessful());

        $taskReport = new TaskReport(['Type' => TaskReport::TYPE_USER_ERROR]);
        $this->assertFalse($taskReport->isSuccessful());
    }

    public function testNotSuccessful()
    {
        $taskReport = new TaskReport(
            [
                'Type' => TaskReport::TYPE_REPLY,
                'Success' => false,
                'Stdout' => 'output',
                'Stderr' => 'error',
            ],
            new Task(['Action' => 'ssh', 'Cmd' => 'ls -al'])
        );

        $this->assertFalse($taskReport->isSuccessful());
        $this->assertEquals('error', $taskReport->getErrorOutput());
        $this->assertEquals('output', $taskReport->getOutput());
    }

    public function testSuccessful()
    {
        $taskReport = new TaskReport(
            [
                'Type' => TaskReport::TYPE_REPLY,
                'Success' => true,
                'Stdout' => 'output',
            ],
            new Task(['Action' => 'ssh', 'Cmd' => 'ls -al'])
        );

        $this->assertTrue($taskReport->isSuccessful());
        $this->assertNull($taskReport->getErrorOutput());
        $this->assertEquals('output', $taskReport->getOutput());
    }

    public function testNotSuccessfulWithoutStdout()
    {
        $taskReport = new TaskReport(
            [
                'Type' => TaskReport::TYPE_REPLY,
                'Success' => false,
                'Stderr' => 'error',
            ],
            new Task(['Action' => 'ssh', 'Cmd' => 'ls -al'])
        );

        $this->assertFalse($taskReport->isSuccessful());
        $this->assertEquals('error', $taskReport->getErrorOutput());
        $this->assertNull($taskReport->getOutput());
    }

    public function testNotSuccessfulWithoutStderr()
    {
        $taskReport = new TaskReport(
            [
                'Type' => TaskReport::TYPE_REPLY,
                'Success' => false,
            ],
            new Task(['Action' => 'ssh', 'Cmd' => 'ls -al'])
        );

        $this->assertFalse($taskReport->isSuccessful());
        $this->assertNull($taskReport->getErrorOutput());
        $this->assertNull($taskReport->getOutput());
    }
}
