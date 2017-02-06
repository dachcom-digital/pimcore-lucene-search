<?php

namespace LuceneSearch\Crawler\Listener;

use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Event\SpiderEvents;

class Abort
{
    var $spider = NULL;

    /**
     * Abort constructor.
     *
     * @param $spider
     */
    public function __construct($spider)
    {
        $this->spider = $spider;
    }

    /**
     * @param Event $event
     */
    public function checkCrawlerState(Event $event)
    {
        if (!file_exists(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp')) {
            $this->spider->getDispatcher()->dispatch(SpiderEvents::SPIDER_CRAWL_USER_STOPPED);
        }
    }

    /**
     * @param Event $event
     */
    public function stopCrawler(Event $event)
    {
        \Pimcore\Logger::debug('LuceneSearch: Crawl aborted by user.');
        exit;
    }
}
