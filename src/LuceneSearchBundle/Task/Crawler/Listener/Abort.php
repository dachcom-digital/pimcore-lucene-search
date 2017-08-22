<?php

namespace LuceneSearchBundle\Task\Crawler\Listener;

use LuceneSearchBundle\Configuration\Configuration;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Spider\Event\SpiderEvents;

class Abort
{
    /**
     * @var null
     */
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
        if (!file_exists(Configuration::CRAWLER_PROCESS_FILE_PATH)) {
            $this->spider->getDispatcher()->dispatch(SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
                new GenericEvent($this, ['uri' => $event->getArgument('uri'), 'errorMessage' => 'crawling aborted by user (tmp file while crawling has suddenly gone.']));
        }
    }

    /**
     * @param Event $event
     */
    public function stopCrawler(Event $event)
    {
        exit;
    }
}