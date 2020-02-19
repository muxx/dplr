<?php

namespace Dplr\Tests;

use Dplr\Dplr;
use Dplr\TaskReport;
use PHPUnit\Framework\TestCase;

class DplrTest extends TestCase
{
    private const GOSSHA_PATH = 'SSH_AUTH_SOCK= /usr/local/bin/GoSSHa';
    private const SSH_KEY = '/root/.ssh/id_rsa';
    private const USER = 'root';

    public function testSimple(): void
    {
        $d = self::getDplr();
        $d
            ->addServer('remote_1', ['app', 'all'])
            ->addServer('remote_2', ['app', 'all'])
            ->addServer('remote_3', ['job', 'all'])
        ;

        $d
            ->command('touch 1.txt', 'job')
            ->command('ls -a', 'all')
            ->command('cat 2.txt', 'job')
            ->command('rm 1.txt', 'job')
        ;

        $output = '';
        $d->run(function (string $s) use (&$output) {
            $output .= $s;
        });

        $this->assertEquals("CMD touch 1.txt .\nCMD ls -a ...\nCMD cat 2.txt E\nCMD rm 1.txt .\n", $output);

        $report = $d->getReport();
        $this->assertEquals(6, $report['total']);
        $this->assertEquals(5, $report['successful']);
        $this->assertEquals(1, $report['failed']);

        $reports = $d->getReports();
        for ($i = 1; $i <= 3; $i++) {
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
    }

    private static function getDplr(): Dplr
    {
        return new Dplr(self::USER, self::GOSSHA_PATH, self::SSH_KEY);
    }
}
