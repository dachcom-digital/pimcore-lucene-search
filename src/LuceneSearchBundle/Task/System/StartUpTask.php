<?php

namespace LuceneSearchBundle\Task\System;

use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Task\AbstractTask;

class StartUpTask extends AbstractTask
{
    public function isValid()
    {
        //we're in a running cycle, don't interrupt.
        if ($this->isFirstCycle() === false) {
            return true;
        }

        if ($this->handlerDispatcher->getStateHandler()->getCrawlerState() == StateHandler::CRAWLER_STATE_ACTIVE) {
            if (isset($this->options['force']) && $this->options['force'] === true) {
                $this->handlerDispatcher->getStateHandler()->stopCrawler(true);
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $crawlData
     *
     * @return bool
     */
    public function process($crawlData)
    {
        $this->logger->setPrefix('task.startup');

        if ($this->isFirstCycle() === false) {
            return false;
        }

        $this->logger->log('start crawling...', 'debug', false, false);

        $this->handlerDispatcher->getStoreHandler()->resetGenesisIndex();
        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();
        $this->handlerDispatcher->getStoreHandler()->resetLogs();

        $this->handlerDispatcher->getStateHandler()->startCrawler();

        return true;
    }
}