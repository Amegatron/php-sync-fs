<?php

require_once __DIR__ . "/SyncFsTestCase.php";

class FileSystemLockSyncDriverTest extends SyncFsTestCase
{
    public function setUp(): void
    {
        mkdir(__DIR__ . "/data");
    }

    public function tearDown(): void
    {
        $this->rmdirRecursive(__DIR__ . "/data");
    }

    public function testSequentialLocks()
    {
        $fileName = __DIR__ . "/output.txt";
        $stream = $this->getOutputStream($fileName, $descriptors);
        $key = $this->getRandomKey();

        $p1cmd = "php " . __DIR__ . "/lock_agent.php -i 1 -k \"{$key}\" -o lock -t300000";
        $p1 = proc_open($p1cmd, $descriptors, $pipes);
        usleep(100000); // small pause before next instance

        $p2cmd = "php " . __DIR__ . "/lock_agent.php -i 2 -k \"{$key}\" -o lock -t100000";
        $p2 = proc_open($p2cmd, $descriptors, $pipes);

        $this->waitForProcesses([$p1, $p2]);

        proc_close($p1);
        proc_close($p2);

        fseek($stream, 0);
        $output = rtrim(stream_get_contents($stream));
        fclose($stream);
        unlink($fileName);

        $lines = explode("\n", $output);
        $this->assertCount(6, $lines);
        $this->assertEquals("#1: acquiring lock {$key}", $lines[0]);
        $this->assertEquals("#1: lock {$key} acquired", $lines[1]);
        $this->assertEquals("#2: acquiring lock {$key}", $lines[2]);
        $this->assertEquals("#1: lock {$key} released", $lines[3]);
        $this->assertEquals("#2: lock {$key} acquired", $lines[4]);
        $this->assertEquals("#2: lock {$key} released", $lines[5]);
    }

    protected function getOutputStream($fileName, &$descriptors)
    {
        if (file_exists($fileName)) {
            unlink($fileName);
        }
        $stream = fopen($fileName, "a+");
        stream_set_blocking($stream, false);

        $descriptors = [
            1 => $stream,
            2 => $stream,
        ];

        return $stream;
    }

    protected function waitForProcesses(array $processes)
    {
        if (empty($processes)) {
            return;
        }

        while (true) {
            usleep(1000);
            foreach ($processes as $process) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    continue 2;
                }
            }
            break;
        }
    }

    public function testWait()
    {
        $fileName = __DIR__ . "/output.txt";
        $stream = $this->getOutputStream($fileName, $descriptors);
        $key = $this->getRandomKey();

        $p1cmd = "php " . __DIR__ . "/lock_agent.php -i 1 -k \"{$key}\" -o lock -t300000";
        $p1 = proc_open($p1cmd, $descriptors, $pipes);
        usleep(100000); // small pause before next instance

        $p2cmd = "php " . __DIR__ . "/lock_agent.php -i 2 -k \"{$key}\" -o wait";
        $p2 = proc_open($p2cmd, $descriptors, $pipes);

        $this->waitForProcesses([$p1, $p2]);

        proc_close($p1);
        proc_close($p2);

        fseek($stream, 0);
        $output = rtrim(stream_get_contents($stream));
        fclose($stream);
        unlink($fileName);

        $lines = explode("\n", $output);
        $this->assertCount(5, $lines);
        $this->assertEquals("#1: acquiring lock {$key}", $lines[0]);
        $this->assertEquals("#1: lock {$key} acquired", $lines[1]);
        $this->assertEquals("#2: waiting for lock {$key}", $lines[2]);
        $this->assertEquals("#1: lock {$key} released", $lines[3]);
        $this->assertEquals("#2: lock {$key} was released", $lines[4]);
    }
}
