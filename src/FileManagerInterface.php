<?php

namespace PhpSync\Drivers\FileSystem;

interface FileManagerInterface
{
    public function getFileName(string $key, string $category);
    public function ensureDirectoriesExist(string $fileName);
    public function getRootPath(): string;
    public function setRootPath(string $path);
}