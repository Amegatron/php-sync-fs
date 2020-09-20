<?php

namespace PhpSync\Drivers\FileSystem;

trait WithFileManagerTrait
{
    /** @var FileManagerInterface */
    protected $manager;

    /**
     * @return FileManagerInterface
     */
    public function getManager(): FileManagerInterface
    {
        return $this->manager;
    }

    /**
     * @param FileManagerInterface $manager
     * @return WithFileManagerTrait
     */
    public function setManager(FileManagerInterface $manager)
    {
        $this->manager = $manager;
        return $this;
    }
}