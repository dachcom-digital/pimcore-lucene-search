<?php

namespace LuceneSearchBundle\Task\System;

use LuceneSearchBundle\Task\AbstractTask;

class ShutDownTask extends AbstractTask
{
    public function isValid()
    {
        return TRUE;
    }

    public function process($crawlData)
    {
        $this->logger->setPrefix('task.shutdown');

        if($this->isLastCycle() === FALSE) {
            return FALSE;
        }

        $this->logger->log('Stopping Crawling...', 'debug', FALSE, FALSE);

        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetUriFilterPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->riseGenesisToStable();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();

        $this->handlerDispatcher->getStateHandler()->stopCrawler();

        return TRUE;
    }
}