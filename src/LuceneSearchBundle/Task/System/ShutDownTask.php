<?php

namespace LuceneSearchBundle\Task\System;

use LuceneSearchBundle\Task\AbstractTask;

class ShutDownTask extends AbstractTask
{
    /**
     * @var string
     */
    protected $prefix = 'task.shutdown';

    /**
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * @param mixed $crawlData
     *
     * @return bool|mixed
     */
    public function process($crawlData)
    {
        $this->logger->setPrefix($this->prefix);

        if ($this->isLastCycle() === false) {
            return false;
        }

        $this->logger->log('Stopping crawling...', 'debug', false, false);

        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetUriFilterPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->riseGenesisToStable();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();
        $this->handlerDispatcher->getStoreHandler()->clearQueuedDocumentModifiers();

        $this->handlerDispatcher->getStateHandler()->stopCrawler();

        return true;
    }
}