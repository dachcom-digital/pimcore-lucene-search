<?php

namespace LuceneSearchBundle\Processor\Organizer\Handler;

use LuceneSearchBundle\Config\ConfigManager;

class StateHandler extends AbstractHandler
{


    const CRAWLER_STATE_IDLE = 'idle';

    const CRAWLER_STATE_ACTIVE = 'active';

    /**
     * @return string
     */
    public function getCrawlerState()
    {
        if ($this->configManager->getStateConfig('running') === TRUE) {
            return self::CRAWLER_STATE_ACTIVE;
        }

        return self::CRAWLER_STATE_IDLE;
    }

    /**
     * @param bool $forceStart
     *
     * @return bool
     */
    public function startCrawler($forceStart = FALSE)
    {
        $this->fileSystem->touch(ConfigManager::CRAWLER_PROCESS_FILE_PATH);

        $this->configManager->setStateConfig('started', time());
        $this->configManager->setStateConfig('forceStart', $forceStart);
        $this->configManager->setStateConfig('forceStop', FALSE);
        $this->configManager->setStateConfig('running', TRUE);
        $this->configManager->setStateConfig('finished', NULL);

        \Pimcore\Logger::debug('LuceneSearch: Starting crawl');

        return TRUE;
    }

    /**
     * @param bool $forcedStop
     *
     * @return bool
     */
    public function stopCrawler($forcedStop = FALSE)
    {
        $this->fileSystem->remove(ConfigManager::CRAWLER_PROCESS_FILE_PATH);

        $this->configManager->setStateConfig('finished', time());
        $this->configManager->setStateConfig('forceStart', FALSE);
        $this->configManager->setStateConfig('running', FALSE);
        $this->configManager->setStateConfig('forceStop', $forcedStop);

        \Pimcore\Logger::debug('LuceneSearch: Stopping crawl');

        return TRUE;
    }

}