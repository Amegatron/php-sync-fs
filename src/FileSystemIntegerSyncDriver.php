<?php

namespace PhpSync\Drivers\FileSystem;

use PhpSync\Core\Exceptions\SyncOperationException;
use PhpSync\Generic\IntegerDoesNotExistException;
use PhpSync\Generic\IntegerSyncDriverInterface;

/**
 * Class FileSystemIntegerSyncDriver
 *
 * Implements IntegerSyncDriverInterface using file system
 *
 * @package PhpSync\Drivers\FileSystem
 */
class FileSystemIntegerSyncDriver implements IntegerSyncDriverInterface
{
    use WithFileManagerTrait;

    const CATEGORY = 'integers';

    public function __construct(FileManagerInterface $manager)
    {
        $this->setManager($manager);
    }

    /**
     * @inheritDoc
     */
    public function setValue(string $key, int $value): int
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            $this->getManager()->ensureDirectoriesExist($fileName);
        }

        $fp = $this->openFile($fileName, 'w');
        flock($fp, LOCK_EX);
        fwrite($fp, $value);
        fclose($fp);
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getValue(string $key): int
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (!file_exists($fileName)) {
            throw new IntegerDoesNotExistException("Requested Integer does not exist");
        }

        return intval(file_get_contents($fileName));
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $by)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        $currentValue = 0;
        if (file_exists($fileName)) {
            $fp = $this->openFile($fileName, 'r+');
            flock($fp, LOCK_EX);
            $buf = "";
            while (!feof($fp)) {
                $buf .= fread($fp, 10);
            }
            fseek($fp, 0);
            ftruncate($fp, 0);
            $currentValue = intval($buf);
        } else {
            $this->getManager()->ensureDirectoriesExist($fileName);
            $fp = $this->openFile($fileName, 'w');
            flock($fp, LOCK_EX);
        }

        $currentValue += $by;
        fwrite($fp, $currentValue);
        fclose($fp);

        return $currentValue;
    }

    /**
     * @param $fileName
     * @param $mode
     * @return resource
     * @throws SyncOperationException
     */
    protected function openFile($fileName, $mode)
    {
        $fp = fopen($fileName, $mode);
        if (!$fp) {
            throw new SyncOperationException("Could open target file for writing");
        }
        return $fp;
    }

    /**
     * @inheritDoc
     */
    public function hasValue(string $key): bool
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        return file_exists($fileName);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key)
    {
        $fileName = $this->getManager()->getFileName($key, self::CATEGORY);
        if (file_exists($fileName)) {
            if (!unlink($fileName)) {
                throw new SyncOperationException("Could delete integer");
            }
        }
    }
}