<?php

namespace LuceneSearchBundle\Task\Crawler\Listener;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Event\Events;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;

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
            $this->spider->getDispatcher()->dispatch(Events::LUCENE_SEARCH_CRAWLER_INTERRUPTED,
                new GenericEvent($this, ['uri' => $event->getArgument('uri'), 'errorMessage' => 'crawling aborted by user (tmp file while crawling has suddenly gone.)']));
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