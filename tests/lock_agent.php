<?php

require_once __DIR__ . "/../vendor/autoload.php";

use PhpSync\Drivers\FileSystem\FileManager;
use PhpSync\Drivers\FileSystem\FileSystemLockSyncDriver;
use PhpSync\Generic\Lock;
use PhpSync\Generic\SingletonManager;

$opts = getopt("i:k:o:t::");
$key = $opts["k"];
$instanceNum = intval($opts["i"]);

$singletonManager = new SingletonManager();
$fileManager = new FileManager(__DIR__ . '/data');
$driver = new FileSystemLockSyncDriver($fileManager);
$lock = Lock::getInstance($key, $singletonManager, $driver);

switch ($opts["o"]) {
    case "lock":
        $timeout = $opts["t"];
        output($instanceNum, "acquiring lock {$key}");
        $lock->lock();
        usleep(1000);
        output($instanceNum, "lock {$key} acquired");
        usleep($timeout);
        $lock->unlock();
        output($instanceNum, "lock {$key} released");
        break;
    case "unlock":
        break;
    case "wait":
        output($instanceNum, "waiting for lock {$key}");
        $lock->wait();
        usleep(1000);
        output($instanceNum, "lock {$key} was released");
        break;
}

function output($instanceNum, $text)
{
    echo "#" . $instanceNum . ": " . $text . PHP_EOL;
}