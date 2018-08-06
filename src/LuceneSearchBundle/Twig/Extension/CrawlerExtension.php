<?php

namespace LuceneSearchBundle\Twig\Extension;

use LuceneSearchBundle\Tool\CrawlerState;

class CrawlerExtension extends \Twig_Extension
{
    /**
     * @var CrawlerState
     */
    protected $crawlerState;

    public function __construct(CrawlerState $crawlerState)
    {
        $this->crawlerState = $crawlerState;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_Function('lucene_search_crawler_active', [$this, 'checkCrawlerState'])
        ];
    }

    public function checkCrawlerState()
    {
        return $this->crawlerState->isLuceneSearchCrawler();
    }
}