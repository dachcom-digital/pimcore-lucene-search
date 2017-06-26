<?php

namespace LuceneSearchBundle\Task\Crawler\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Spider\Event\SpiderEvents;

class Logger implements EventSubscriberInterface
{
    private $debug = FALSE;

    /**
     * @var \LuceneSearchBundle\Logger\AbstractLogger
     */
    private $logEngine = FALSE;

    /**
     * Logger constructor.
     *
     * @param bool $debug
     * @param bool \LuceneSearch\Model\Logger\Engine $logEngine
     */
    public function __construct($debug = FALSE, $logEngine)
    {
        $this->debug = $debug;
        $this->logEngine = $logEngine;
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
        $message = preg_replace('/\s\s+/', ' ', $event->getArgument('message'));
        $this->logEvent('failed', $event, 'error', $message);
    }

    public function logStoppedByUser($event)
    {
        $this->logEvent('stopped', $event, 'debugHighlight');
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
            $message = '[crawler.' . $name . '] ';
            if(!empty($additionalMessage)) {
                $message .= ' ' . $additionalMessage . ' ';
            }

            $message .= $event->getArgument('uri')->toString();

            if($event->hasArgument('errorMessage')) {
                $message .= ' | Error Message: ' . $event->getArgument('errorMessage');
            }
            $this->logEngine->log($message, $debugLevel, $logToBackend, $logToSystem);
        }
    }
}