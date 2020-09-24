<?php

require_once __DIR__ . "/SyncFsTestCase.php";

use PhpSync\Core\Exceptions\SyncOperationException;
use PhpSync\Drivers\FileSystem\FileManager;
use PhpSync\Drivers\FileSystem\FileManagerInterface;
use PhpSync\Drivers\FileSystem\FileSystemLockSyncDriver;
use PhpSync\Generic\Lock;
use PhpSync\Generic\LockSyncDriverInterface;
use PhpSync\Generic\SingletonManager;
use PhpSync\Generic\SingletonManagerInterface;

class FileSystemLockSyncDriverTest extends SyncFsTestCase
{
    /** @var FileManagerInterface */
    private $fileManager;

    /** @var SingletonManagerInterface */
    private $singletonManager;

    /** @var LockSyncDriverInterface */
    private $driver;

    /** @var string */
    private $rootPath;

    public function setUp(): void
    {
        $this->rootPath = __DIR__ . "/data";
        mkdir($this->rootPath);
    }

    public function tearDown(): void
    {
        $this->rmdirRecursive($this->rootPath);
    }

    public function testSequentialLocks()
    {
        $fileName = __DIR__ . "/output.txt";
        $stream = $this->getOutputStream($fileName, $descriptors);
        $key = $this->getRandomKey();

        //
        // Timeouts (-t) are arbitrary here so that processes have enough time to initialize
        // More time - more robust, but longer test.
        //

        $p1cmd = "php " . __DIR__ . "/lock_agent.php -i 1 -k \"{$key}\" -o lock -t300000";
        $p1 = proc_open($p1cmd, $descriptors, $pipes);
        usleep(50000); // small pause before next instance

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

        //
        // Timeouts (-t) are arbitrary here so that processes have enough time to initialize
        // More time - more robust, but longer test.
        //

        $p1cmd = "php " . __DIR__ . "/lock_agent.php -i 1 -k \"{$key}\" -o lock -t200000";
        $p1 = proc_open($p1cmd, $descriptors, $pipes);
        usleep(50000); // small pause before next instance

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

    public function testLockExists()
    {
        $this->setUpDriver();
        $key = $this->getRandomKey();

        $lock1 = Lock::getInstance($key, $this->singletonManager, $this->driver);
        $lock1->lock();
        $this->assertTrue($lock1->exists());

        // Making an independent instance which knows nothing initially
        $lock2 = Lock::getInstance($key, new SingletonManager(), $this->getNewDriver());

        $this->assertNotSame($lock1, $lock2);
        $this->assertTrue($lock2->exists());
    }

    public function testUnlock()
    {
        $this->setUpDriver();
        $key = $this->getRandomKey();

        $lock1 = Lock::getInstance($key, $this->singletonManager, $this->driver);
        $lock1->lock();

        // Making an independent instance which knows nothing initially
        $lock2 = Lock::getInstance($key, new SingletonManager(), $this->getNewDriver());

        $this->assertNotSame($lock1, $lock2);

        $this->assertTrue($lock2->exists());
        $this->assertTrue($lock2->unlock());

        $this->assertFalse($lock2->exists());
        $this->assertFalse($lock2->unlock());
    }

    public function testParallelUnlock()
    {
        $this->setUpDriver();
        $key = $this->getRandomKey();
        $lock = Lock::getInstance($key, $this->singletonManager, $this->driver);

        $p1cmd = "php " . __DIR__ . "/lock_agent.php -i 1 -k \"{$key}\" -o lock -t500000";
        $p1 = proc_open($p1cmd, [1 => ["pipe", "w"]], $pipes);
        usleep(100000); // small pause before next instance

        $this->assertTrue($lock->exists());
        $this->assertTrue($lock->unlock());
        $this->assertFalse($lock->exists());

        $this->waitForProcesses([$p1]);
        proc_close($p1);
    }

    protected function setUpDriver()
    {
        $this->fileManager = new FileManager($this->rootPath);
        $this->singletonManager = new SingletonManager();
        $this->driver = new FileSystemLockSyncDriver($this->fileManager);
    }

    protected function getNewDriver()
    {
        return new FileSystemLockSyncDriver(new FileManager($this->rootPath));
    }

}
