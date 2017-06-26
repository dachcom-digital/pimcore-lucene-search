<?php

namespace LuceneSearchBundle\EventListener;

use LuceneSearchBundle\Logger\Logger;
use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Organizer\Dispatcher\HandlerDispatcher;

use LuceneSearchBundle\Task\TaskManager;
use Pimcore\Event\System\MaintenanceEvent;

class CrawlListener
{
    /**
     * @var HandlerDispatcher
     */
    protected $handlerDispatcher;

    /**
     * @var TaskManager
     */
    protected $taskManager;

    /**
     * Worker constructor.
     *
     * @param HandlerDispatcher $handlerDispatcher
     * @param TaskManager $taskManager
     */
    public function __construct(HandlerDispatcher $handlerDispatcher, TaskManager $taskManager)
    {
        $this->handlerDispatcher = $handlerDispatcher;
        $this->taskManager = $taskManager;
    }

    /**
     * @param MaintenanceEvent $ev
     *
     * @return void
     */
    public function run(MaintenanceEvent $ev)
    {
        if ($this->handlerDispatcher->getStateHandler()->isCrawlerEnabled() === FALSE) {
            return;
        }


        $currentHour = date('H', time());

        $running = $this->handlerDispatcher->getStateHandler()->getCrawlerState() === StateHandler::CRAWLER_STATE_ACTIVE;

        $lastStarted = $this->handlerDispatcher->getStateHandler()->getCrawlerLastStarted();
        $lastFinished = $this->handlerDispatcher->getStateHandler()->getCrawlerLastFinished();
        $forceStart = $this->handlerDispatcher->getStateHandler()->isCrawlerInForceStart();
        $aDayAgo = time() - (24 * 60 * 60);

        /**
         * + If Crawler is not running
         * + If last start of Crawler is initial or a day ago
         * + If it's between 1 + 3 o clock in the night
         * + OR if its force
         * => RUN
         */
        if ($running === FALSE &&
            (((is_bool($lastStarted) || $lastStarted <= $aDayAgo) && $currentHour > 1 && $currentHour < 3) || $forceStart)
        ) {
            \Pimcore\Logger::debug('LuceneSearch: crawling started from maintenance listener.');

            $consoleLogger = new Logger();
            $this->taskManager->setLogger($consoleLogger);
            $this->taskManager->processTaskChain(['force' => FALSE]);

            /**
             * + If Crawler is Running
             * + If last stop of crawler is before last start
             * + If last start is older than one day
             * => We have some errors: EXIT CRAWLING!
             */
        } else if ($running === TRUE && $lastFinished < $lastStarted && $lastStarted <= $aDayAgo) {
            \Pimcore\Logger::error('LuceneSearch: There seems to be a problem with the search crawler! Trying to stop it.');
            $this->handlerDispatcher->getStateHandler()->stopCrawler(TRUE);
        }
    }
}