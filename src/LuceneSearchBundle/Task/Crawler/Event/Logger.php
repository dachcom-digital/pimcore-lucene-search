<?php

namespace LuceneSearchBundle\Task\Crawler\Event;

use LuceneSearchBundle\Logger\AbstractLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Spider\Event\SpiderEvents;

class Logger implements EventSubscriberInterface
{
    private $debug = FALSE;

    /**
     * @var AbstractLogger
     */
    private $logger = FALSE;

    /**
     * Logger constructor.
     *
     * @param bool $debug
     * @param AbstractLogger $logger
     */
    public function __construct($debug = FALSE, $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            SpiderEvents::SPIDER_CRAWL_FILTER_POSTFETCH   => 'logFiltered',
            SpiderEvents::SPIDER_CRAWL_FILTER_PREFETCH    => 'logFiltered',
            SpiderEvents::SPIDER_CRAWL_POST_ENQUEUE       => 'logQueued',
            SpiderEvents::SPIDER_CRAWL_RESOURCE_PERSISTED => 'logPersisted',
            SpiderEvents::SPIDER_CRAWL_ERROR_REQUEST      => 'logFailed',
            SpiderEvents::SPIDER_CRAWL_POST_REQUEST       => 'logCrawled',
            SpiderEvents::SPIDER_CRAWL_USER_STOPPED       => 'logStoppedByUser'
        ];
    }

    /**
     * @param GenericEvent $event
     */
    public function logQueued(GenericEvent $event)
    {
        $this->logEvent('queued', $event);
    }

    /**
     * @param GenericEvent $event
     */
    public function logPersisted(GenericEvent $event)
    {
        $this->logEvent('persisted', $event);
    }

    /**
     * @param GenericEvent $event
     */
    public function logFiltered(GenericEvent $event)
    {
        $filterType = $event->hasArgument('filterType') ? $event->getArgument('filterType') . '.' : '';
        $name = $filterType . 'filtered';
        $this->logEvent($name, $event);
    }

    /**
     * @param GenericEvent $event
     */
    public function logFailed(GenericEvent $event)
    {
        $message = preg_replace('/\s+/S', ' ', $event->getArgument('message'));
        $this->logEvent('failed', $event, 'error', $message);
    }

    /**
     * @param GenericEvent$event
     */
    public function logStoppedByUser(GenericEvent $event)
    {
        $this->logEvent('stopped', $event, 'debugHighlight', $event->getArgument('errorMessage'));
    }

    /**
     * @param GenericEvent $event
     */
    public function logCrawled(GenericEvent $event)
    {
        $this->logEvent('uri.crawled', $event, 'debugHighlight');
    }

    /**
     * @param              $name
     * @param GenericEvent $event
     * @param              $debugLevel
     * @param string       $additionalMessage
     */
    protected function logEvent($name, GenericEvent $event, $debugLevel = 'debug', $additionalMessage = '')
    {
        $triggerLog = in_array($name, ['uri.crawled', 'uri.match.invalid.filtered', 'uri.match.forbidden.filtered', 'filtered', 'failed', 'stopped']);
        $logToBackend = in_array($name, ['filtered', 'failed']);
        $logToSystem = $this->debug === TRUE;

        if ($triggerLog) {

            $prefix = '[spider.' . $name . '] ';

            $message = $prefix;

            if(!empty($additionalMessage)) {
                $message .= $additionalMessage . ' ';
            }

            $message .= $event->getArgument('uri')->toString();

            $this->logger->log($message, $debugLevel, $logToBackend, $logToSystem);
        }
    }
}