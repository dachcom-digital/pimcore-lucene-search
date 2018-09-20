<?php

namespace LuceneSearchBundle\Organizer\Handler;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Modifier\DocumentModifier;

class StoreHandler extends AbstractHandler
{
    /**
     * @var DocumentModifier
     */
    protected $documentModifier;

    /**
     * @param DocumentModifier $documentModifier
     */
    public function setDocumentModifier(DocumentModifier $documentModifier)
    {
        $this->documentModifier = $documentModifier;
    }

    public function resetGenesisIndex()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Genesis Index');

        if ($this->fileSystem->exists(Configuration::INDEX_DIR_PATH_GENESIS)) {
            $this->removeFolder(Configuration::INDEX_DIR_PATH_GENESIS);
            $this->fileSystem->mkdir(Configuration::INDEX_DIR_PATH_GENESIS, 0755);
        }
    }

    public function riseGenesisToStable()
    {
        //first delete current stable
        if ($this->fileSystem->exists(Configuration::INDEX_DIR_PATH_GENESIS)) {

            if ($this->fileSystem->exists(Configuration::INDEX_DIR_PATH_STABLE)) {
                $this->removeFolder(Configuration::INDEX_DIR_PATH_STABLE);
            }

            //copy genesis to stable
            $this->copyFolder(Configuration::INDEX_DIR_PATH_GENESIS, Configuration::INDEX_DIR_PATH_STABLE);
        }
    }

    /**
     * Reset Resource Persistence Store
     */
    public function resetPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Persistence Store');

        if ($this->fileSystem->exists(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->removeFolder(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH);
        }

        $this->fileSystem->mkdir(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH, 0755);

    }

    /**
     * Reset Resource Persistence Store
     */
    public function resetAssetTmp()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Asset Tmp');

        if ($this->fileSystem->exists(Configuration::CRAWLER_TMP_ASSET_DIR_PATH)) {
            $this->removeFolder(Configuration::CRAWLER_TMP_ASSET_DIR_PATH);
        }

        $this->fileSystem->mkdir(Configuration::CRAWLER_TMP_ASSET_DIR_PATH, 0755);

    }

    /**
     * Remove existing modifier documents
     */
    public function clearQueuedDocumentModifiers()
    {
        \Pimcore\Logger::debug('LuceneSearch: Remove Queued Document Modifiers');

        $this->documentModifier->clearActiveJobs();
    }

    /**
     * Rest Uri Filter Store
     */
    public function resetUriFilterPersistenceStore()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Uri Filter Persistence Store');

        if ($this->fileSystem->exists(Configuration::CRAWLER_URI_FILTER_FILE_PATH)) {
            $this->fileSystem->remove(Configuration::CRAWLER_URI_FILTER_FILE_PATH);
        }
    }

    /**
     * Reset Logs
     */
    public function resetLogs()
    {
        \Pimcore\Logger::debug('LuceneSearch: Reset Logs');
        $this->fileSystem->dumpFile(Configuration::CRAWLER_LOG_FILE_PATH, '');
    }

    /**
     * @param $from
     * @param $to
     */
    private function copyFolder($from, $to)
    {
        if (!$this->fileSystem->exists($to)) {
            $this->fileSystem->mkdir($to);
        }

        $this->fileSystem->mirror($from, $to, null, ['override' => true, 'delete' => true]);
    }

    /**
     * @param        $path
     * @param string $pattern
     */
    private function removeFolder($path, $pattern = '*')
    {
        $files = glob($path . "/$pattern");

        foreach ($files as $file) {
            if (is_dir($file) and !in_array($file, ['..', '.'])) {
                $this->removeFolder($file, $pattern);
                rmdir($file);
            } elseif (is_file($file) and ($file != __FILE__)) {
                unlink($file);
            }
        }
    }
}