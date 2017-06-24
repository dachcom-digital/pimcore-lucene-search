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
        if ($this->fileSystem->exists(ConfigManager::CRAWLER_PROCESS_FILE_PATH)) {
            return self::CRAWLER_STATE_ACTIVE;
        }

        return self::CRAWLER_STATE_IDLE;
    }

    public function getCrawlerStateDescription()
    {
        $messages = [];

        if (!$this->configManager->getConfig('enabled')) {
            return FALSE;
        }

        if ($this->configManager->getStateConfig('running')) {
            $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_running');
        } else {
            $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_not_running');
        }

        $started = 'never';
        $finished = 'never';

        if (!is_bool($this->configManager->getStateConfig('started'))) {
            $started = date('d.m.Y H:i', (double)$this->configManager->getStateConfig('started'));
        }

        if (!is_bool($this->configManager->getStateConfig('finished'))) {
            $finished = date('d.m.Y H:i', (double)$this->configManager->getStateConfig('finished'));
        }

        $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_last_started') . ': ' . $started . '. ';
        $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_last_finished') . ': ' . $finished . '. ';

        if ($this->getConfigCompletionState() === 'incomplete') {
            $messages[] = 'ERROR: ' . $this->getTranslation('lucenesearch_frontend_config_incomplete');
        } else {
            if ($this->configManager->getStateConfig('forceStart')) {
                $messages[] = $this->getTranslation('lucenesearch_frontend_crawler') . ': ';
                $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_start_on_next_maintenance');
            }
        }

        return $messages;
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

    public function forceCrawlerStartOnNextMaintenance()
    {
        $this->configManager->setStateConfig('forceStart', TRUE);

        \Pimcore\Logger::debug('LuceneSearch: forced to starting crawl');

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

    /**
     * @return string
     */
    public function getConfigCompletionState()
    {
        $frontEndUrls = $this->configManager->getConfig('seeds');
        $validLinks = $this->configManager->getConfig('filter:valid_links');

        if (empty($frontEndUrls) || empty($validLinks)) {
            return 'incomplete';
        } else {
            return 'complete';
        }

    }

}