<?php

namespace LuceneSearchBundle\Task\Crawler\Filter;

use VDB\Spider\Event\SpiderEvents;
use Symfony\Component\EventDispatcher\GenericEvent;

trait LogDispatcher {

    /**
     * @var array
     */
    public $filtered = [];

    /***
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $dispatcher;

    /**
     * @var FilterPersistor
     */
    private $persistor;

    /**
     * @param $dispatcher
     */
    function setDispatcher($dispatcher)
    {
        $this->persistor = new FilterPersistor();
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param $uri
     * @param $filterType
     */
    function notifyDispatcher($uri, $filterType) {

        $stringUri = $uri->toString();
        $saveUri = md5($stringUri);

        if ($this->persistor->get($saveUri) === FALSE) {
            $this->filtered[] = $saveUri;
            $this->persistor->set($saveUri, time());
            $event = new GenericEvent($this, ['uri' => $uri, 'filterType' => $filterType]);
            $this->dispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_FILTER_PREFETCH, $event);
        }
    }
}