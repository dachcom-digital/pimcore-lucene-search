<?php

namespace LuceneSearch\Crawler\Filter;

use LuceneSearch\Model\Persistor;
use VDB\Spider\Event\SpiderEvents;
use Symfony\Component\EventDispatcher\GenericEvent;

trait LogDispatcher {

    public $filtered = [];

    /***
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $dispatcher;

    /**
     * @var Persistor
     */
    private $perisitor;

    /**
     * @param $dispatcher
     */
    function setDispatcher($dispatcher)
    {
        $this->perisitor = new Persistor('lucene-filter');
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param $uri
     * @param $filterType
     */
    function notifyDispatcher($uri, $filterType) {

        $stringUri = $uri->toString();
        $saveUri = md5($stringUri);

        if ($this->perisitor->get($saveUri) === FALSE) {
            $this->filtered[] = $saveUri;
            $this->perisitor->set($saveUri, time());
            $event = new GenericEvent($this, ['uri' => $uri, 'filterType' => $filterType]);
            $this->dispatcher->dispatch(SpiderEvents::SPIDER_CRAWL_FILTER_PREFETCH, $event);
        }
    }
}