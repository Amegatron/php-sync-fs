<?php

use PHPUnit\Framework\TestCase;

class SyncFsTestCase extends TestCase
{
    protected function getRandomKey()
    {
        return "key_" . mt_rand(1, 1000000);
    }

    protected function getRandomCategory()
    {
        return "category_" . mt_rand(1, 1000000);
    }

    protected function rmdirRecursive($dir)
    {
        $entries = glob($dir . '/*');
        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                $this->rmdirRecursive($entry);
            } else {
                unlink($entry);
            }
        }
        rmdir($dir);
    }
}