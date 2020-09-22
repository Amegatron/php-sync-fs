<?php

require_once __DIR__ . '/SyncFsTestCase.php';

use PhpSync\Drivers\FileSystem\FileManager;
use PhpSync\Drivers\FileSystem\FileManagerInterface;
use PHPUnit\Framework\TestCase;

class FileManagerTest extends SyncFsTestCase
{
    /** @var FileManagerInterface */
    private $manager;

    /** @var string */
    private $rootPath;

    public function setUp(): void
    {
        $this->rootPath = __DIR__ . '/data';
        mkdir($this->rootPath);
        $this->manager = new FileManager($this->rootPath);
    }

    public function tearDown(): void
    {
        $this->rmdirRecursive($this->rootPath);
    }

    public function testManagerChangesRootPathInternally()
    {
        $dir = __DIR__ . '/data' . mt_rand(1, 1000000);
        $this->manager->setRootPath($dir);
        $this->assertEquals($dir, $this->manager->getRootPath());
    }

    public function testManagerGetsSameFileNameForSameKeyAndCategory()
    {
        $key = $this->getRandomKey();
        $category = "category_" . mt_rand(1, 1000000);
        $filename = $this->manager->getFileName($key, $category);
        $this->assertEquals($filename, $this->manager->getFileName($key, $category));
    }

    public function testManagerGetsDifferentFileNameForDifferentCategory()
    {
        $key = $this->getRandomKey();
        $category1 = "category_" . mt_rand(1, 1000000);
        $category2 = "category_" . mt_rand(1000001, 2000001);
        $filename1 = $this->manager->getFileName($key, $category1);
        $filename2 = $this->manager->getFileName($key, $category2);
        $this->assertNotEquals($filename1, $filename2);
    }

    public function testManagerGetsDifferentFileNameForDifferentKey()
    {
        $key1 = $this->getRandomKey();
        $key2 = $this->getRandomKey();
        $category = "category_" . mt_rand(1, 1000000);
        $filename1 = $this->manager->getFileName($key1, $category);
        $filename2 = $this->manager->getFileName($key2, $category);
        $this->assertNotEquals($filename1, $filename2);
    }

    public function testManagerCreatesDirectoriesForFilenames()
    {
        $key = $this->getRandomKey();
        $category = $this->getRandomCategory();
        $filename = $this->manager->getFileName($key, $category);
        $this->manager->ensureDirectoriesExist($filename);
        touch($filename);
        $this->assertTrue(file_exists($filename));
        unlink($filename);
    }

    public function testFileNameStartsFromRootPath()
    {
        $key = $this->getRandomKey();
        $category = $this->getRandomCategory();
        $filename = $this->manager->getFileName($key, $category);
        $this->assertSame(0, strpos($filename, $this->rootPath));
    }
}
