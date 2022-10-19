<?php

namespace Dplr\Tests;

use Dplr\Dplr;
use Dplr\TaskReport;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

class DplrTest extends TestCase
{
    private const GOSSHA_PATH = 'SSH_AUTH_SOCK= /usr/local/bin/GoSSHa';
    private const SSH_KEY = '/root/.ssh/id_rsa';
    private const USER = 'root';

    public function testSuccessful(): void
    {
        $d = self::getDplr();

        $this->assertEquals(3600, $d->getDefaultTimeout());
        $d->setDefaultTimeout(60);
        $this->assertEquals(60, $d->getDefaultTimeout());

        $d->command('ls -a', 'job');
        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertTrue($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals("CMD ls -a .\n", $output);

        $report = $d->getReport();
        $this->assertEquals(1, $report['total']);
        $this->assertEquals(1, $report['successful']);
        $this->assertEquals(0, $report['failed']);

        $this->assertCount(0, $d->getFailed());

        /** @var TaskReport $report */
        foreach ($d->getReports() as $report) {
            $this->assertTrue($report->isSuccessful());
        }
        $this->assertEquals(".\n..\n.ssh\n", $d->getSingleReportOutput());
    }

    public function testExceptionOnSingleReportOutputWithSeveralTasks(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('There are more than one task report.');

        $d = self::getDplr();
        $d
            ->command('ls -al', 'app')
            ->run()
        ;

        $this->assertTrue($d->isSuccessful());
        $d->getSingleReportOutput();
    }

    public function testOneThread(): void
    {
        $d = self::getDplr();

        $this->assertTrue($d->hasGroup('job'));
        $this->assertTrue($d->hasGroup('all'));
        $this->assertFalse($d->hasGroup('not_exists'));

        $d
            ->upload(self::getFixturesPath() . '/files/1.txt', '1.txt', 'job')
            ->command('ls -a', 'all')
            ->command('cat 2.txt', 'job')
            ->command('rm 1.txt', 'job')
        ;

        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertFalse($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals(
            'CPY ' . self::getFixturesPath() . "/files/1.txt -> 1.txt .\nCMD ls -a ...\nCMD cat 2.txt E\nCMD rm 1.txt .\n",
            $output
        );

        $report = $d->getReport();
        $this->assertEquals(6, $report['total']);
        $this->assertEquals(5, $report['successful']);
        $this->assertEquals(1, $report['failed']);

        $reports = $d->getReports();
        for ($i = 1; $i <= 3; ++$i) {
            /** @var TaskReport $report */
            $report = $reports[$i];
            if ('remote_3' === $report->getHost()) {
                $this->assertEquals(".\n..\n.ssh\n1.txt\n", $report->getOutput());
            } else {
                $this->assertEquals(".\n..\n.ssh\n", $report->getOutput());
            }
        }

        /** @var TaskReport $report */
        foreach ($d->getReports() as $report) {
            if ('cat 2.txt' === $report->getTask()->getParameters()['Cmd']) {
                $this->assertFalse($report->isSuccessful());
            } else {
                $this->assertTrue($report->isSuccessful());
            }
        }

        $this->assertCount(1, $d->getFailed());
    }

    public function testMultiThread(): void
    {
        $d = self::getDplr();

        $d
            ->command('ps', 'job')
            ->multi()
                ->command('ls -a', 'job')
                ->command('cd /var', 'app')
            ->end()
            ->multi()
                ->command('pwd', 'all')
                ->command('echo "abba"', 'app')
            ->end()
        ;

        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertTrue($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals(
            "CMD ps .\nCMD ls -a \nCMD cd /var ...\nCMD pwd \nCMD echo \"abba\" .....\n",
            $output
        );

        $report = $d->getReport();
        $this->assertEquals(9, $report['total']);
        $this->assertEquals(9, $report['successful']);
        $this->assertEquals(0, $report['failed']);

        $reports = $d->getReports();

        /** @var TaskReport $report */
        $report = $reports[1];
        $this->assertEquals(".\n..\n.ssh\n", $report->getOutput());

        for ($i = 7; $i < 9; ++$i) {
            /** @var TaskReport $report */
            $report = $reports[$i];
            $this->assertEquals("abba\n", $report->getOutput());
        }

        for ($i = 4; $i < 7; ++$i) {
            /** @var TaskReport $report */
            $report = $reports[$i];
            $this->assertEquals("/root\n", $report->getOutput());
        }
    }

    public function testLimitedConcurrency(): void
    {
        $d = new Dplr(self::USER, self::GOSSHA_PATH, self::SSH_KEY, 16, 1);
        $d
            ->addServer('remote_1', ['app', 'all'])
            ->addServer('remote_2', ['app', 'all'])
            ->addServer('remote_3', ['job', 'all'])
        ;
        $d->setDefaultTimeout(60);

        $d->command('ls -a', 'all');
        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertTrue($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals("CMD ls -a ...\n", $output);

        $report = $d->getReport();
        $this->assertEquals(3, $report['total']);
        $this->assertEquals(3, $report['successful']);
        $this->assertEquals(0, $report['failed']);

        $this->assertCount(0, $d->getFailed());
        $this->assertCount(3, $d->getReports());

        foreach ($d->getReports() as $report) {
            $this->assertTrue($report->isSuccessful());
        }
    }

    public function testShellErrorReporting(): void
    {
        $d = self::getDplr();
        $d->upload(self::getFixturesPath() . '/files/1.txt', '/foo/1.txt', 'job');

        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertFalse($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals(
            'CPY ' . self::getFixturesPath() . "/files/1.txt -> /foo/1.txt E\n",
            $output
        );

        $report = $d->getReport();
        $this->assertEquals(1, $report['total']);
        $this->assertEquals(0, $report['successful']);
        $this->assertEquals(1, $report['failed']);

        $report = $d->getReports()[0];
        $this->assertEquals(
            $report->getErrorOutput(),
            "ash: can't create /foo/1.txt: nonexistent directory\n"
        );
    }

    public function testGosshaErrorReporting(): void
    {
        $d = new Dplr(self::USER, self::GOSSHA_PATH, self::SSH_KEY);
        $d
            ->addServer('remote_4', ['job', 'all'])
        ;
        $d->upload(self::getFixturesPath() . '/files/1.txt', '/foo/1.txt', 'job');

        $this->assertTrue($d->hasTasks());

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertFalse($d->isSuccessful());
        $this->assertFalse($d->hasTasks());
        $this->assertEquals(
            'CPY ' . self::getFixturesPath() . "/files/1.txt -> /foo/1.txt E\n",
            $output
        );

        $report = $d->getReport();
        $this->assertEquals(1, $report['total']);
        $this->assertEquals(0, $report['successful']);
        $this->assertEquals(1, $report['failed']);

        $report = $d->getReports()[0];
        $this->assertEquals(
            $report->getErrorOutput(),
            'dial tcp: lookup remote_4: no such host'
        );
    }

    private static function getFixturesPath(): string
    {
        return realpath(__DIR__ . '/../Fixtures');
    }

    private static function getDplr(): Dplr
    {
        $d = new Dplr(self::USER, self::GOSSHA_PATH, self::SSH_KEY);
        $d
            ->addServer('remote_1', ['app', 'all'])
            ->addServer('remote_2', ['app', 'all'])
            ->addServer('remote_3', ['job', 'all'])
        ;

        return $d;
    }
}
