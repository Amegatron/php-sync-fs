<?php

require_once __DIR__ . '/SyncFsTestCase.php';

use PhpSync\Drivers\FileSystem\FileManager;
use PhpSync\Drivers\FileSystem\FileManagerInterface;
use PhpSync\Generic\IntegerDoesNotExistException;
use PhpSync\Generic\IntegerSyncDriverInterface;

class FileSystemIntegerSyncDriverTest extends SyncFsTestCase
{
    /** @var FileManagerInterface */
    private $manager;

    /** @var string */
    private $rootPath;

    /** @var IntegerSyncDriverInterface */
    private $driver;

    public function setUp(): void
    {
        $this->rootPath = __DIR__ . '/data';
        $this->manager = new FileManager($this->rootPath);
        $this->driver = new \PhpSync\Drivers\FileSystem\FileSystemIntegerSyncDriver($this->manager);
        mkdir($this->rootPath);
    }

    public function tearDown(): void
    {
        $this->rmdirRecursive($this->rootPath);
    }

    public function testDriverThrowsExceptionWhenGettingNonExistentInteger()
    {
        $key = $this->getRandomKey();
        $this->expectException(IntegerDoesNotExistException::class);
        $this->driver->getValue($key);
    }

    public function testDriverSetsNewValue()
    {
        $key = $this->getRandomKey();
        $value = mt_rand(1, 1000000);
        $this->driver->setValue($key, $value);
        $this->assertSame($value, $this->driver->getValue($key));
    }

    public function testDriverIncrementsValue()
    {
        $key = $this->getRandomKey();
        $initialValue = mt_rand(1, 1000000);
        $incBy = mt_rand(1, 1000000);
        $this->driver->setValue($key, $initialValue);
        $this->driver->increment($key, $incBy);
        $this->assertSame($initialValue + $incBy, $this->driver->getValue($key));
    }

    public function testDriverCanIncrementByNegativeNumber()
    {
        $key = $this->getRandomKey();
        $incBy = mt_rand(1, 1000000);
        $incBy *= -1;
        $this->driver->setValue($key, 0);
        $this->driver->increment($key, $incBy);
        $this->assertSame($incBy, $this->driver->getValue($key));
    }

    public function testDriverHasNoValue()
    {
        $key = $this->getRandomKey();
        $this->assertFalse($this->driver->hasValue($key));
    }

    public function testDriverDeletesValue()
    {
        $key = $this->getRandomKey();
        $this->driver->setValue($key, mt_rand(1, 1000000));
        $this->driver->delete($key);
        $this->assertFalse($this->driver->hasValue($key));
    }
}
