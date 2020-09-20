<?php

namespace PhpSync\Drivers\FileSystem;

use PhpSync\Core\Exceptions\SyncOperationException;

class FileManager implements FileManagerInterface
{
    private $rootPath;

    private $cache = [];

    public function __construct($rootPath)
    {
        $this->rootPath = $rootPath;
    }

    /**
     * Gets full fileName for the given key and category
     *
     * TODO: delegate to a separate logic
     *
     * @param string $key
     * @param string $category
     * @return mixed
     */
    public function getFileName(string $key, string $category)
    {
        if (isset($this->cache[$key])) {
            $hash = $this->cache[$key];
        } else {
            $hash = md5($key);
            $this->cache[$key] = $hash;
        }

        $fileName = $this->rootPath . "/" . strtolower($category). "/";
        $fileName .= substr($hash, 0, 2) . "/" . substr($hash, 2, 2) . "/" . $hash;
        return $fileName;
    }

    /**
     * Creates directory structure for a given file, assuming $rootPath already exists
     *
     * TODO: delegate to a separate logic (@see getFileName())
     *
     * @param $fileName
     * @throws SyncOperationException
     */
    public function ensureDirectoriesExist(string $fileName)
    {
        $rootPathReal = realpath($this->rootPath);
        $relativePath = substr($fileName, strlen($rootPathReal));
        $pathParts = explode('/', $relativePath);
        array_pop($pathParts);
        $testPath = $rootPathReal;
        foreach ($pathParts as $part) {
            $testPath .= '/' . $part;
            if (!file_exists($testPath)) {
                if (!mkdir($testPath)) {
                    throw new SyncOperationException("Could create directories for file storage");
                }
            }
        }
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function setRootPath(string $path)
    {
        $this->rootPath = $path;
    }
}