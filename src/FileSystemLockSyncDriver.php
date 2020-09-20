<?php

namespace PhpSync\Drivers\FileSystem;

use PhpSync\Core\Exceptions\SyncOperationException;
use PhpSync\Generic\LockSyncDriverInterface;

class FileSystemLockSyncDriver implements LockSyncDriverInterface
{
    use WithFileManagerTrait;

    const CATEGORY = 'locks';

    protected $fp;

    public function __construct(FileManagerInterface $manager)
    {
        $this->setManager($manager);
    }

    public function lock(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            $this->getManager()->ensureDirectoriesExist($fileName);
        }

        $this->fp = fopen($fileName, 'w');
        flock($this->fp, LOCK_EX);
    }

    public function unlock(string $key): bool
    {
        if ($this->fp) {
            fclose($this->fp);
            return true;
        }

        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            return false;
        }

        $fp = fopen($fileName, 'r');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            throw new SyncOperationException("Could not unlock existing lock");
        } else {
            fclose($fp);
            return false;
        }
    }

    public function wait(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            return;
        }

        $fp = fopen($fileName, 'r');
        flock($fp, LOCK_EX);
        fclose($fp);
    }

    public function exists(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            return false;
        }

        $fp = fopen($fileName, 'r');
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        } else {
            fclose($fp);
            return true;
        }
    }

    /**
     * @return FileManagerInterface
     */
    public function getManager(): FileManagerInterface
    {
        return $this->manager;
    }

    /**
     * @param FileManagerInterface $manager
     * @return FileSystemLockSyncDriver
     */
    public function setManager(FileManagerInterface $manager): FileSystemLockSyncDriver
    {
        $this->manager = $manager;
        return $this;
    }
}