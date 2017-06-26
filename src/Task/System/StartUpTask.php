<?php

namespace LuceneSearchBundle\Task\System;

use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Task\AbstractTask;

class StartUpTask extends AbstractTask
{
    public function isValid()
    {
        if ($this->handlerDispatcher->getStateHandler()->getCrawlerState() == StateHandler::CRAWLER_STATE_ACTIVE) {
            if (isset($this->options['force']) && $this->options['force'] === TRUE) {
                $this->handlerDispatcher->getStateHandler()->stopCrawler(TRUE);
            } else {
                return FALSE;
            }
        }


        return TRUE;
    }

    /**
     * @param mixed $crawlData
     *
     * @return bool
     */
    public function process($crawlData)
    {
        $this->logger->log('LuceneSearch: Start Crawling...', 'debug', FALSE, FALSE);

        $this->handlerDispatcher->getStoreHandler()->resetGenesisIndex();
        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();
        $this->handlerDispatcher->getStoreHandler()->resetLogs();

        $this->handlerDispatcher->getStateHandler()->startCrawler();

        return TRUE;
    }
}