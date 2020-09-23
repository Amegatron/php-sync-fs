<?php

namespace PhpSync\Drivers\FileSystem;

use PhpSync\Core\Exceptions\SyncOperationException;
use PhpSync\Generic\LockSyncDriverInterface;

class FileSystemLockSyncDriver implements LockSyncDriverInterface
{
    use WithFileManagerTrait;

    const CATEGORY = 'locks';

    /**
     * Registry of locked files during this thread.
     * This is necessary for at least two reasons:
     *      1) we need to store the file-resource itself somewhere, cause otherwise
     *         PHP will close the file as soon as resource-variable becomes unavailable (GC)
     *      2) we need to keep track of "locally" locked files so that further attempts to lock
     *         or wait will not be blocking due to the nature of single-threaded PHP (it does not
     *         block further attempts to lock a file, cause in a truly single-threaded scenario it
     *         100% a dead-block. For us it is important to workaround this, cause locks can be released
     *         from parallel scripts.
     * @var array
     */
    private static $filesRegistry = [];

    public function __construct(FileManagerInterface $manager)
    {
        $this->setManager($manager);
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

    /**
     * @inheritdoc
     */
    public function lock(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            $this->getManager()->ensureDirectoriesExist($fileName);
        }

        /*
         * Locking the same file sequentially in the same thread is non-blocking
         * due to the nature of PHP's file-locking mechanism and PHP itself.
         *
         * Most probably, attempt to acquire the same lock inside the same thread while it is already
         * acquired is a sign or poor design, cause in "normal" cause it would be a dead-lock (second attempt
         * will wait forever until it is released first, which will never happen).
         *
         * It is possible, though, that a Lock is going to be released by a parallel process.
         */

        $fp = fopen($fileName, 'w');
        flock($fp, LOCK_EX);
        self::$filesRegistry[$fileName] = $fp;
    }

    /**
     * @return FileManagerInterface
     */
    public function getManager(): FileManagerInterface
    {
        return $this->manager;
    }

    /**
     * @inheritdoc
     */
    public function unlock(string $key): bool
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            unset(self::$filesRegistry[$fileName]);
            return false;
        }

        $result = @unlink($fileName);
        if ($result) {
            unset(self::$filesRegistry[$fileName]);
            return true;
        } else {
            throw new SyncOperationException();
        }
    }

    /**
     * @inheritdoc
     */
    public function wait(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            return;
        }

        /*
         * Trying to get NB lock in the same thread will succeed, meaning, the method will not actually wait
         * and will return no matter if file is actually blocked.
         */

        if (isset(self::$filesRegistry[$fileName]) && self::$filesRegistry[$fileName]) {
            while (file_exists($fileName)) {
                usleep(10000);
            }
        } else {
            $fp = fopen($fileName, 'r');
            flock($fp, LOCK_EX);
            fclose($fp);
        }
    }

    /**
     * @inheritdoc
     */
    public function exists(string $key): bool
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);

        /*
         * Attempt to acquire a NB lock inside the same thread where it was already
         * acquired has no sense, so just checking for file existence.
         *
         * Maybe it is worth remembering about acquired locks inside the Driver itself and
         * check for actual locks if there is no such info here, but it will not solve the issue
         * when there are multiple same drivers inside a single thread.
         */


        return file_exists($fileName);
    }
}