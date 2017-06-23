<?php

namespace LuceneSearchBundle\EventListener;

use Symfony\Component\EventDispatcher\GenericEvent;

use LuceneSearchBundle\Config\ConfigManager;
use LuceneSearchBundle\Processor\Processor;

class CrawlListener
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var Processor
     */
    protected $processor;

    /**
     * Worker constructor.
     *
     * @param ConfigManager $configManager
     * @param Processor $processor
     */
    public function __construct(ConfigManager $configManager = NULL, Processor $processor)
    {
        $this->configManager = $configManager;
        $this->processor = $processor;
    }

    /**
     * @param GenericEvent $e
     *
     * @return void
     */
    public function run(GenericEvent $e)
    {
        \Pimcore\Logger::err('lucene: maintanence running');

        if ($this->processor->isInstalled()) {
            \Pimcore\Logger::debug('LuceneSearch: Plugin is not installed - no maintenance to do for this plugin.');
            return;
        }

        $currentHour = date('H', time());

        //Frontend recrawl
        $running = self::frontendCrawlerRunning();

        $enabled = Configuration::get('frontend.enabled');
        $lastStarted = Configuration::getCoreSetting('started');
        $lastFinished = Configuration::getCoreSetting('finished');
        $forceStart = Configuration::getCoreSetting('forceStart');
        $aDayAgo = time() - (24 * 60 * 60);

        /**
         * + If Crawler is enabled
         * + If Crawler is not running
         * + If last start of Crawler is initial or a day ago
         * + If it's between 1 + 3 o clock in the night
         * + OR if its force
         * => RUN
         */
        if ($enabled && !$running && (((is_bool($lastStarted) || $lastStarted <= $aDayAgo) && $currentHour > 1 && $currentHour < 3) || $forceStart)) {
            \Pimcore\Logger::debug('starting frontend recrawl...');
            $this->frontendCrawl();
            /**
             * + If Crawler is Running
             * + If last stop of crawler is before last start
             * + If last start is older than one day
             * => We have some errors: EXIT CRAWLING!
             */
        } else if ($running && $lastFinished < $lastStarted && $lastStarted <= $aDayAgo) {
            \Pimcore\Logger::error('LuceneSearch: There seems to be a problem with the search crawler! Trying to stop it.');
            $this->stopFrontendCrawler();
        }


    }
}