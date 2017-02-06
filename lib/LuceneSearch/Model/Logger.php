<?php

namespace LuceneSearch\Model;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Uri\UriInterface;
use VDB\Spider\Event\SpiderEvents;

class Logger implements EventSubscriberInterface
{
    private $debug = FALSE;

    /**
     * Logger constructor.
     *
     * @param bool $debug
     */
    public function __construct($debug = FALSE)
    {
        $this->debug = $debug;
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
            SpiderEvents::SPIDER_CRAWL_ERROR_REQUEST      => 'logFailed'
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
        $this->logEvent('filtered', $event);
    }

    /**
     * @param GenericEvent $event
     */
    public function logFailed(GenericEvent $event)
    {
        $this->logEvent('failed', $event);
    }

    /**
     * @param              $name
     * @param GenericEvent $event
     */
    protected function logEvent($name, GenericEvent $event)
    {
        if ($this->debug === TRUE) {
            \Pimcore\Logger::debug('LuceneSearch [' . $name . ']: ' . $event->getArgument('uri')->toString());
        }
    }
}