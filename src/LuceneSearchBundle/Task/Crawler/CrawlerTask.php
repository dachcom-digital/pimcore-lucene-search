<?php

namespace LuceneSearchBundle\Task\Crawler;

use GuzzleHttp\Client;
use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Event\CrawlerRequestHeaderEvent;
use LuceneSearchBundle\Event\Events;
use LuceneSearchBundle\Task\AbstractTask;
use LuceneSearchBundle\Task\Crawler\Listener;
use LuceneSearchBundle\Task\Crawler\Filter\Discovery;
use LuceneSearchBundle\Task\Crawler\Filter\PostFetch;
use LuceneSearchBundle\Task\Crawler\Event\Logger;
use LuceneSearchBundle\Task\Crawler\Event\Statistics;
use LuceneSearchBundle\Task\Crawler\PersistenceHandler;
use Psr\Http\Message\RequestInterface;
use VDB\Spider\Spider;
use VDB\Spider\QueueManager;
use VDB\Spider\Filter;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use GuzzleHttp\Middleware;

class CrawlerTask extends AbstractTask
{
    /**
     * @var string
     */
    protected $prefix = 'task.crawler';

    /**
     * @var string
     */
    protected $seed;

    /**
     * @var array
     */
    protected $validLinks;

    /**
     * @var bool
     */
    protected $allowQueryInUrl = false;

    /**
     * @var bool
     */
    protected $allowHashInUrl = false;

    /**
     * @var array
     */
    protected $invalidLinks;

    /**
     * Set max crawl content size (MB)
     * 0 means no limit
     *
     * @var integer
     */
    protected $contentMaxSize = 0;

    /**
     * @deprecated
     * @var integer
     */
    protected $maxRedirects = 10;

    /**
     * @deprecated
     * @var integer
     */
    protected $timeout = 30;

    /**
     * @var int
     */
    protected $downloadLimit = 0;

    /**
     * @var array
     */
    protected $allowedSchemes = [];

    /**
     * @var array
     */
    protected $validMimeTypes = [];

    /**
     * @var boolean
     */
    protected $readyToCrawl = false;

    /**
     * @var bool
     */
    protected $ownHostOnly = true;

    /**
     * @var int
     */
    protected $maxLinkDepth = 0;

    /**
     * @var array
     */
    protected $clientOptions = [];

    /**
     * @return bool
     */
    public function isValid()
    {
        $filterLinks = $this->configuration->getConfig('filter');
        $crawlerConfig = $this->configuration->getConfig('crawler');

        $maxLinkDepth = $crawlerConfig['max_link_depth'];
        $this->maxLinkDepth = !is_numeric($maxLinkDepth) ? 1 : $maxLinkDepth;

        $this->allowHashInUrl = $filterLinks['allow_hash_in_url'];
        $this->allowQueryInUrl = $filterLinks['allow_query_in_url'];
        $this->validLinks = $filterLinks['valid_links'];
        $this->invalidLinks = $this->getInvalidLinks();
        $this->contentMaxSize = $crawlerConfig['content_max_size'];

        $this->ownHostOnly = $this->configuration->getConfig('own_host_only');
        $this->validMimeTypes = $this->configuration->getConfig('allowed_mime_types');
        $this->allowedSchemes = $this->configuration->getConfig('allowed_schemes');
        $this->downloadLimit = $crawlerConfig['max_download_limit'];

        $this->clientOptions = ['allow_redirects' => false];

        $this->seed = $this->options['iterator'];

        return true;
    }

    /**
     * @return array
     */
    private function getInvalidLinks()
    {
        $filterLinks = $this->configuration->getConfig('filter');

        $userInvalidLinks = $filterLinks['user_invalid_links'];
        $coreInvalidLinks = $filterLinks['core_invalid_links'];

        if (!empty($userInvalidLinks) && !empty($coreInvalidLinks)) {
            $invalidLinkRegex = array_merge($userInvalidLinks, [$coreInvalidLinks]);
        } elseif (!empty($userInvalidLinks)) {
            $invalidLinkRegex = $userInvalidLinks;
        } elseif (!empty($coreInvalidLinks)) {
            $invalidLinkRegex = [$coreInvalidLinks];
        } else {
            $invalidLinkRegex = [];
        }

        return $invalidLinkRegex;
    }

    /**
     * @param mixed $previousData
     *
     * @return bool|\VDB\Spider\PersistenceHandler\PersistenceHandlerInterface
     * @throws \Exception
     */
    public function process($previousData)
    {
        $this->logger->setPrefix($this->prefix);

        $start = microtime(true);

        try {
            $spider = $this->initializeSpider();
        } catch (\Exception $e) {
            $this->log('error: ' . $e->getMessage(), 'error');
            return false;
        }

        if ($this->downloadLimit > 0) {
            $spider->getDownloader()->setDownloadLimit($this->downloadLimit);
        }

        $statsHandler = new Statistics();
        $logHandler = new Logger(\Pimcore::inDebugMode(), $this->logger);
        $queueManager = new QueueManager\InMemoryQueueManager();

        $queueManager->getDispatcher()->addSubscriber($statsHandler);
        $queueManager->getDispatcher()->addSubscriber($logHandler);

        $spider->getDiscovererSet()->maxDepth = $this->maxLinkDepth;

        $queueManager->setTraversalAlgorithm(QueueManager\InMemoryQueueManager::ALGORITHM_DEPTH_FIRST);
        $spider->setQueueManager($queueManager);

        $spider->getDiscovererSet()->set(new XPathExpressionDiscoverer("//link[@hreflang]|//a[not(@rel='nofollow')]"));

        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\AllowedSchemeFilter($this->allowedSchemes));

        if ($this->ownHostOnly === true) {
            $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\AllowedHostsFilter([$this->seed], true));
        }

        if ($this->allowHashInUrl === false) {
            $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithHashFragmentFilter());
        }

        if ($this->allowQueryInUrl === false) {
            $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithQueryStringFilter());
        }

        $spider->getDiscovererSet()->addFilter(new Discovery\UriFilter($this->invalidLinks, $spider->getDispatcher()));
        $spider->getDiscovererSet()->addFilter(new Discovery\NegativeUriFilter($this->validLinks, $spider->getDispatcher()));

        if ($this->contentMaxSize !== 0) {
            $spider->getDownloader()->addPostFetchFilter(new PostFetch\MaxContentSizeFilter($this->contentMaxSize));
        }

        $spider->getDownloader()->addPostFetchFilter(new PostFetch\MimeTypeFilter($this->validMimeTypes));

        $spider->getDownloader()->setPersistenceHandler(new PersistenceHandler\FileSerializedResourcePersistenceHandler(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH));

        $politenessPolicyEventListener = new PolitenessPolicyListener(20);
        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            [$politenessPolicyEventListener, 'onCrawlPreRequest']
        );

        $abortListener = new Listener\Abort($spider);
        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            [$abortListener, 'checkCrawlerState']
        );

        $spider->getDownloader()->getDispatcher()->addSubscriber($logHandler);

        $spider->getDispatcher()->addSubscriber($statsHandler);
        $spider->getDispatcher()->addSubscriber($logHandler);

        $spider->getDispatcher()->addListener(
            Events::LUCENE_SEARCH_CRAWLER_INTERRUPTED,
            [$abortListener, 'stopCrawler']
        );

        $spider->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
            [$abortListener, 'stopCrawler']
        );

        $guzzleClient = $spider->getDownloader()->getRequestHandler()->getClient();
        $this->addHeadersToRequest($guzzleClient->getConfig('handler'));

        // Execute the crawl
        try {
            $spider->crawl();
        } catch (\Exception $e) {
            $this->log($e->getMessage(), 'error');
        }

        $this->log('enqueued links: ' . $statsHandler->getQueued(), 'debug');
        $this->log('skipped links: ' . $statsHandler->getFiltered(), 'debug');
        $this->log('failed links: ' . $statsHandler->getFailed(), 'debug');
        $this->log('persisted links: ' . $statsHandler->getPersisted(), 'debug');

        $peakMem = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $totalDelay = round($politenessPolicyEventListener->totalDelay / 1000 / 1000, 2);

        $totalTime = microtime(true) - $start;
        $totalTime = number_format((float)$totalTime, 3, '.', '');
        $minutes = str_pad(floor($totalTime / 60), 2, '0', STR_PAD_LEFT);
        $seconds = str_pad($totalTime % 60, 2, '0', STR_PAD_LEFT);

        $this->log('memory peak usage: ' . $peakMem . 'MB', 'debug');
        $this->log('total time: ' . $minutes . ':' . $seconds, 'debug');
        $this->log('politeness wait time: ' . $totalDelay . ' seconds', 'debug');

        return $spider->getDownloader()->getPersistenceHandler();
    }

    /**
     * @param $handler
     */
    private function addHeadersToRequest($handler)
    {
        $defaultHeaderElements = [
            [
                'name'       => 'Lucene-Search',
                'value'      => $this->configuration->getSystemConfig('version'),
                'identifier' => 'lucene-search-bundle'
            ]
        ];

        $event = new CrawlerRequestHeaderEvent();
        \Pimcore::getEventDispatcher()->dispatch(
            'lucene_search.task.crawler.request_header',
            $event
        );

        $headerElements = array_merge($defaultHeaderElements, $event->getHeaders());

        foreach ($headerElements as $headerElement) {
            $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($headerElement) {
                return $request->withHeader($headerElement['name'], $headerElement['value']);
            }), $headerElement['identifier']);
        }
    }

    /**
     * @return Spider
     */
    private function initializeSpider()
    {
        $spider = new Spider($this->seed);
        $guzzleClient = new Client($this->clientOptions);
        $spider->getDownloader()->getRequestHandler()->setClient($guzzleClient);

        return $spider;
    }
}