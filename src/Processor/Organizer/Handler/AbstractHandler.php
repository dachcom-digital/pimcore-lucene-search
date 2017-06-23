<?php

namespace LuceneSearchBundle\Processor\Organizer\Handler;

use LuceneSearchBundle\Config\ConfigManager;
use Pimcore\File;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractHandler
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * Worker constructor.
     *
     * @param ConfigManager     $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
        $this->fileSystem = new FileSystem();
    }
}