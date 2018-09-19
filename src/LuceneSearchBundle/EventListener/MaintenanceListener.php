<?php

namespace LuceneSearchBundle\EventListener;

use LuceneSearchBundle\Logger\Logger;
use LuceneSearchBundle\Modifier\QueuedDocumentModifier;
use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Organizer\Dispatcher\HandlerDispatcher;
use LuceneSearchBundle\Task\TaskManager;
use Pimcore\Event\System\MaintenanceEvent;
use Pimcore\Model\Schedule\Maintenance\Job;

class MaintenanceListener
{
    /**
     * @var HandlerDispatcher
     */
    protected $handlerDispatcher;

    /**
     * @var QueuedDocumentModifier
     */
    protected $queuedDocumentModifier;

    /**
     * @var TaskManager
     */
    protected $taskManager;

    /**
     * MaintenanceListener constructor.
     *
     * @param HandlerDispatcher      $handlerDispatcher
     * @param QueuedDocumentModifier $queuedDocumentModifier
     * @param TaskManager            $taskManager
     */
    public function __construct(HandlerDispatcher $handlerDispatcher, QueuedDocumentModifier $queuedDocumentModifier, TaskManager $taskManager)
    {
        $this->handlerDispatcher = $handlerDispatcher;
        $this->queuedDocumentModifier = $queuedDocumentModifier;
        $this->taskManager = $taskManager;
    }

    /**
     * @param MaintenanceEvent $event
     */
    public function runQueuedDocumentModifier(MaintenanceEvent $event)
    {
        $mainCrawlerIsActive = $this->handlerDispatcher->getStateHandler()->getCrawlerState() === StateHandler::CRAWLER_STATE_ACTIVE;

        // new index is on its way. wait for new index arrival.
        if ($mainCrawlerIsActive === true) {
            return;
        }

        $event->getManager()->registerJob(new Job('lucene_search.maintenance.queued_modifier', [$this->queuedDocumentModifier, 'resolveQueue']));
    }

    /**
     * @param MaintenanceEvent $event
     */
    public function runCrawler(MaintenanceEvent $event)
    {
        $event->getManager()->registerJob(new Job('lucene_search.maintenance.crawler', [$this, 'checkCrawlerCycle']));
    }

    /**
     * Run Crawler in given time range
     */
    public function checkCrawlerCycle()
    {
        if ($this->handlerDispatcher->getStateHandler()->isCrawlerEnabled() === false) {
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
        if ($running === false &&
            (((is_bool($lastStarted) || $lastStarted <= $aDayAgo) && $currentHour > 1 && $currentHour < 3) || $forceStart)
        ) {
            \Pimcore\Logger::debug('LuceneSearch: crawling started from maintenance listener.');

            $logger = new Logger();
            $this->taskManager->setLogger($logger);

            try {
                $this->taskManager->processTaskChain(['force' => false]);
            } catch (\Exception $e) {
                \Pimcore\Logger::error('LuceneSearch: error while running crawler in maintenance.', $e->getTrace());
            }

            /**
             * + If Crawler is Running
             * + If last stop of crawler is before last start
             * + If last start is older than one day
             * => We have some errors: EXIT CRAWLING!
             */
        } elseif ($running === true && $lastFinished < $lastStarted && $lastStarted <= $aDayAgo) {
            \Pimcore\Logger::error('LuceneSearch: There seems to be a problem with the search crawler! Trying to stop it.');
            $this->handlerDispatcher->getStateHandler()->stopCrawler(true);
        }
    }
}