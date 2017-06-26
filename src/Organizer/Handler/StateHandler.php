<?php

namespace LuceneSearchBundle\Organizer\Handler;

use LuceneSearchBundle\Configuration\Configuration;

class StateHandler extends AbstractHandler
{
    const CRAWLER_STATE_IDLE = 'idle';

    const CRAWLER_STATE_ACTIVE = 'active';

    /**
     * @return bool
     */
    public function isCrawlerEnabled()
    {
        return $this->configuration->getConfig('enabled') === TRUE;
    }

    /**
     * @return string
     */
    public function getCrawlerState()
    {
        if ($this->fileSystem->exists(Configuration::CRAWLER_PROCESS_FILE_PATH)) {
            return self::CRAWLER_STATE_ACTIVE;
        }

        return self::CRAWLER_STATE_IDLE;
    }

    public function getCrawlerLastStarted()
    {
        return $this->configuration->getStateConfig('started');
    }

    public function getCrawlerLastFinished()
    {
        return $this->configuration->getStateConfig('finished');
    }

    public function isCrawlerInForceStart()
    {
        return $this->configuration->getStateConfig('forceStart');
    }

    public function isCrawlerInForceStop()
    {
        return $this->configuration->getStateConfig('forceStop');
    }

    /**
     * @return array|bool
     */
    public function getCrawlerStateDescription()
    {
        $messages = [];

        if (!$this->isCrawlerEnabled() === FALSE) {
            return FALSE;
        }

        if ($this->configuration->getStateConfig('running')) {
            $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_running');
        } else {
            $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_not_running');
        }

        $started = 'never';
        $finished = 'never';

        if (!is_bool($this->configuration->getStateConfig('started'))) {
            $started = date('d.m.Y H:i', (double)$this->configuration->getStateConfig('started'));
        }

        if (!is_bool($this->configuration->getStateConfig('finished'))) {
            $finished = date('d.m.Y H:i', (double)$this->configuration->getStateConfig('finished'));
        }

        $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_last_started') . ': ' . $started . '. ';
        $messages[] = $this->getTranslation('lucenesearch_frontend_crawler_last_finished') . ': ' . $finished . '. ';

        if ($this->getConfigCompletionState() === 'incomplete') {
            $messages[] = 'ERROR: ' . $this->getTranslation('lucenesearch_frontend_config_incomplete');
        } else {
            if ($this->configuration->getStateConfig('forceStart')) {
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
        $this->fileSystem->touch(Configuration::CRAWLER_PROCESS_FILE_PATH);

        $this->configuration->setStateConfig('started', time());
        $this->configuration->setStateConfig('forceStart', $forceStart);
        $this->configuration->setStateConfig('forceStop', FALSE);
        $this->configuration->setStateConfig('running', TRUE);
        $this->configuration->setStateConfig('finished', NULL);

        \Pimcore\Logger::debug('LuceneSearch: Starting crawl');

        return TRUE;
    }

    /**
     * @return bool
     */
    public function forceCrawlerStartOnNextMaintenance()
    {
        $this->configuration->setStateConfig('forceStart', TRUE);

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
        $this->fileSystem->remove(Configuration::CRAWLER_PROCESS_FILE_PATH);

        $this->configuration->setStateConfig('finished', time());
        $this->configuration->setStateConfig('forceStart', FALSE);
        $this->configuration->setStateConfig('running', FALSE);
        $this->configuration->setStateConfig('forceStop', $forcedStop);

        \Pimcore\Logger::debug('LuceneSearch: Stopping crawl');

        return TRUE;
    }

    /**
     * @return string
     */
    public function getConfigCompletionState()
    {
        $frontEndUrls = $this->configuration->getConfig('seeds');
        $validLinks = $this->configuration->getConfig('filter:valid_links');

        if (empty($frontEndUrls) || empty($validLinks)) {
            return 'incomplete';
        } else {
            return 'complete';
        }
    }

}