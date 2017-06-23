<?php

namespace LuceneSearchBundle\Processor\Organizer\Handler;

use LuceneSearchBundle\Config\ConfigManager;

class StoreHandler extends AbstractHandler
{
    public function resetGenesisIndex()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Genesis Index');

        if ($this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_GENESIS)) {
            $this->removeFolder(ConfigManager::INDEX_DIR_PATH_GENESIS);
            $this->fileSystem->mkdir(ConfigManager::INDEX_DIR_PATH_GENESIS, 0755);
        }
    }

    public function riseGenesisToStable()
    {
        //first delete current stable
        if ($this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_GENESIS)) {

            if ($this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_STABLE)) {
                var_dump("delete stable");
                $this->removeFolder(ConfigManager::INDEX_DIR_PATH_STABLE);
            }

            //copy genesis to stable
            $this->copyFolder(ConfigManager::INDEX_DIR_PATH_GENESIS, ConfigManager::INDEX_DIR_PATH_STABLE);
        }
    }

    public function resetPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Persistence Store');

        if ($this->fileSystem->exists(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->removeFolder(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH);
        }

        $this->fileSystem->mkdir(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH, 0755);

    }

    public function resetUriFilterPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Uri Filter Persistence Store');

        if ($this->fileSystem->exists(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->removeFolder(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH);
        }
    }

    public function resetLogs()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Logs');
        $this->fileSystem->dumpFile(ConfigManager::CRAWLER_LOG_FILE_PATH, '');
    }

    private function copyFolder($from, $to)
    {
        if (!$this->fileSystem->exists($to)) {
            $this->fileSystem->mkdir($to);
        }

        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            if ($item->isDir()) {
                mkdir($to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    private function removeFolder($path, $pattern = '*')
    {
        $files = glob($path . "/$pattern");

        foreach ($files as $file) {
            if (is_dir($file) and !in_array($file, ['..', '.'])) {
                var_dump("opening directory $file ");
                $this->removeFolder($file, $pattern);
                var_dump("deleting directory $file");
                rmdir($file);
            } else if (is_file($file) and ($file != __FILE__)) {
                // make sure you don't delete the current script
                var_dump("deleting file $file ");
                unlink($file);
            }
        }
    }
}