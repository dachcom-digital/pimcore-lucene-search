<?php

namespace LuceneSearchBundle\Processor\Organizer\Handler;

use Symfony\Component\Filesystem\Filesystem;
use LuceneSearchBundle\Config\ConfigManager;

class StoreHandler extends AbstractHandler
{
    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * Watcher constructor.
     */
    public function __construct()
    {
        $this->fileSystem = new Filesystem();
    }

    public function resetGenesisIndex()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Genesis Index');

        if($this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_GENESIS)) {
            $this->fileSystem->remove(ConfigManager::INDEX_DIR_PATH_GENESIS);
            $this->fileSystem->mkdir(ConfigManager::INDEX_DIR_PATH_GENESIS, 0755);
        }
    }

    public function riseGenesisToStable()
    {
        //first delete current stable
        if($this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_GENESIS)) {
            $this->fileSystem->remove(ConfigManager::INDEX_DIR_PATH_GENESIS);
            //copy genesis to stable
            $this->fileSystem->copy(ConfigManager::INDEX_DIR_PATH_GENESIS, ConfigManager::INDEX_DIR_PATH_STABLE);
        }
    }

    public function resetPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Persistence Store');

        if($this->fileSystem->exists(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->fileSystem->remove(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH);
            $this->fileSystem->mkdir(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH, 0755);
        }

    }

    public function resetUriFilterPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Uri Filter Persistence Store');

        if($this->fileSystem->exists(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->fileSystem->remove(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH);
        }
    }

    public function resetLogs()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Logs');

        $this->fileSystem->dumpFile(ConfigManager::CRAWLER_LOG_FILE_PATH, '');
    }
}