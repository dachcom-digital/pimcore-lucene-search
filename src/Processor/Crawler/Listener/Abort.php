<?php

namespace LuceneSearchBundle\Processor\Crawler\Listener;

use LuceneSearchBundle\Config\ConfigManager;
use Symfony\Component\EventDispatcher\Event;
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
        if (!file_exists(ConfigManager::CRAWLER_PROCESS_FILE_PATH)) {
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
