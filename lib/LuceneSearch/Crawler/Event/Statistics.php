<?php

namespace LuceneSearch\Crawler\Event;

use VDB\Spider\Event\SpiderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Override the default class. just count links.
 * Class Statistics
 * @package LuceneSearch\Crawler\Event
 */
class Statistics implements EventSubscriberInterface
{
    /**
     * @var int
     */
    protected $persisted = 0;

    /**
     * @var int
     */
    protected $queued = 0;

    /**
     * @var int
     */
    protected $filtered = 0;

    /**
     * @var int
     */
    protected $failed = 0;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            SpiderEvents::SPIDER_CRAWL_FILTER_POSTFETCH   => 'addToFiltered',
            SpiderEvents::SPIDER_CRAWL_FILTER_PREFETCH    => 'addToFiltered',
            SpiderEvents::SPIDER_CRAWL_POST_ENQUEUE       => 'addToQueued',
            SpiderEvents::SPIDER_CRAWL_RESOURCE_PERSISTED => 'addToPersisted',
            SpiderEvents::SPIDER_CRAWL_ERROR_REQUEST      => 'addToFailed'
        ];
    }

    /**
     * @param GenericEvent $event
     */
    public function addToQueued(GenericEvent $event)
    {
        $this->queued++;
    }

    /**
     * @param GenericEvent $event
     */
    public function addToPersisted(GenericEvent $event)
    {
        $this->persisted++;
    }

    /**
     * @param GenericEvent $event
     */
    public function addToFiltered(GenericEvent $event)
    {
        $this->filtered++;
    }

    /**
     * @param GenericEvent $event
     */
    public function addToFailed(GenericEvent $event)
    {
        $this->failed++;
    }

    /**
     * @return int
     */
    public function getQueued()
    {
        return $this->queued;
    }

    /**
     * @return int
     */
    public function getPersisted()
    {
        return $this->persisted;
    }

    /**
     * @return int
     */
    public function getFiltered()
    {
        return $this->filtered;
    }

    /**
     * @return int
     */
    public function getFailed()
    {
        return $this->failed;
    }

}
