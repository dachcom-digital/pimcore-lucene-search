<?php

namespace LuceneSearch\Crawler\Listener;

use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Event\SpiderEvents;

class Abort {

    var $spider = null;

    public function __construct( $spider )
    {
        $this->spider = $spider;
    }

    public  function checkCrawlerState(Event $event)
    {
        if( !file_exists( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp' ) )
        {
            $this->spider->getDispatcher()->dispatch(SpiderEvents::SPIDER_CRAWL_USER_STOPPED);
        }
    }

    public  function stopCrawler(Event $event)
    {
        \Logger::log('LuceneSearch: Crawl aborted by user.');
        exit;
    }
}
