<?php

namespace LuceneSearchBundle\Task\Crawler;

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
     * @var array
     */
    protected $seed;

    /**
     * @var array
     */
    protected $validLinks;

    /**
     * @var array
     */
    protected $invalidLinks;

    /**
     * Set max crawl content size (MB)
     * 0 means no limit
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
     * indicates where the content relevant for search starts
     * @var string
     */
    protected $searchStartIndicator;

    /**
     * indicates where the content relevant for search ends
     * @var string
     */
    protected $searchEndIndicator;

    /**
     * indicates where the content irrelevant for search starts
     * @var string
     */
    protected $searchExcludeStartIndicator;

    /**
     * indicates where the content irrelevant for search ends
     * @var string
     */
    protected $searchExcludeEndIndicator;

    /**
     * @var boolean
     */
    protected $readyToCrawl = FALSE;

    /**
     * @var bool
     */
    protected $allowSubDomains = FALSE;

    /**
     * @var int
     */
    protected $maxLinkDepth = 0;

    /**
     * @var bool
     */
    protected $useAuth = FALSE;

    /**
     * @var null
     */
    protected $authUserName = NULL;

    /**
     * @var null
     */
    Protected $authPassword = NULL;

    public function isValid()
    {
        $this->allowSubDomains = FALSE;

        $maxLinkDepth = $this->configuration->getConfig('crawler:max_link_depth');
        $this->maxLinkDepth = !is_numeric($maxLinkDepth) ? 1 : $maxLinkDepth;

        $this->validLinks = $this->configuration->getConfig('filter:valid_links');
        $this->invalidLinks = $this->getInvalidLinks();
        $this->contentMaxSize = $this->configuration->getConfig('crawler:content_max_size');
        $this->searchStartIndicator = $this->configuration->getConfig('crawler:content_start_indicator');
        $this->searchEndIndicator = $this->configuration->getConfig('crawler:content_end_indicator');
        $this->searchExcludeStartIndicator = $this->configuration->getConfig('crawler:content_exclude_start_indicator');
        $this->searchExcludeEndIndicator = $this->configuration->getConfig('crawler:content_exclude_end_indicator');

        $this->validMimeTypes = $this->configuration->getConfig('allowed_mime_types');
        $this->allowedSchemes = $this->configuration->getConfig('allowed_schemes');
        $this->downloadLimit = $this->configuration->getConfig('crawler:max_download_limit');

        $this->seed = $this->options['iterator'];

        if ($this->configuration->getConfig('auth:use_auth') === TRUE) {
            $this->setAuth($this->configuration->getConfig('auth:username'), $this->configuration->getConfig('auth:password'));
        }

        return TRUE;
    }

    /**
     * @param null $username
     * @param null $password
     *
     * @return $this
     */
    public function setAuth($username = NULL, $password = NULL)
    {
        $this->authUserName = $username;
        $this->authPassword = $password;

        if (!empty($this->authUserName) && !empty($this->authPassword)) {
            $this->useAuth = TRUE;
        }

        return $this;
    }

    private function getInvalidLinks()
    {
        $userInvalidLinks = $this->configuration->getConfig('filter:user_invalid_links');
        $coreInvalidLinks = $this->configuration->getConfig('filter:core_invalid_links');

        if (!empty($userInvalidLinks) && !empty($coreInvalidLinks)) {
            $invalidLinkRegex = array_merge($userInvalidLinks, [$coreInvalidLinks]);
        } else if (!empty($userInvalidLinks)) {
            $invalidLinkRegex = $userInvalidLinks;
        } else if (!empty($coreInvalidLinks)) {
            $invalidLinkRegex = [$coreInvalidLinks];
        } else {
            $invalidLinkRegex = [];
        }

        return $invalidLinkRegex;
    }

    /**
     * @return bool|\VDB\Spider\PersistenceHandler\PersistenceHandlerInterface
     * @throws \Exception
     */
    public function process($previousData)
    {
        $start = microtime(TRUE);

        try {
            $spider = new Spider($this->seed);
        } catch (\Exception $e) {

            $this->log('[crawler] error: ' . $e->getMessage(), 'error');
            return FALSE;
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
        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\AllowedHostsFilter([$this->seed], $this->allowSubDomains));

        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithHashFragmentFilter());
        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithQueryStringFilter());

        $spider->getDiscovererSet()->addFilter(new Discovery\UriFilter($this->invalidLinks, $spider->getDispatcher()));
        $spider->getDiscovererSet()->addFilter(new Discovery\NegativeUriFilter($this->validLinks, $spider->getDispatcher()));

        if($this->contentMaxSize !== 0) {
            $spider->getDownloader()->addPostFetchFilter(new PostFetch\MaxContentSizeFilter($this->contentMaxSize));
        }

        $spider->getDownloader()->addPostFetchFilter(new PostFetch\MimeTypeFilter($this->validMimeTypes));

        $persistencePath = PIMCORE_SYSTEM_TEMP_DIRECTORY . '/ls-crawler-tmp';
        $spider->getDownloader()->setPersistenceHandler(new PersistenceHandler\FileSerializedResourcePersistenceHandler($persistencePath));

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

            SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
            [$abortListener, 'stopCrawler']

        );

        $guzzleClient = $spider->getDownloader()->getRequestHandler()->getClient();
        $handler = $guzzleClient->getConfig('handler');

        //add Auth!
        if ($this->useAuth) {

            $un = $this->authUserName;
            $pw = $this->authPassword;

            $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($un, $pw) {
                return $request->withHeader('Authorization', 'Basic ' . base64_encode("$un:$pw"));
            }), 'lucene-search-auth');
        }

        // add LuceneSearch to Headers! @todo: get version of plugin!
        //$pluginInfo = \Pimcore\ExtensionManager::getPluginConfig('LuceneSearch');
        $pluginVersion = '2.0.0';

        $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($pluginVersion) {
            return $request->withHeader('Lucene-Search', $pluginVersion);
        }), 'lucene-search-header');

        // Execute the crawl
        try {
            $spider->crawl();
        } catch (\Exception $e) {
            $this->log('[crawler] ' . $e->getMessage(), 'error');
            throw new \Exception($e->getMessage());
        }

        $this->log('[crawler] Enqueued Links: ' . $statsHandler->getQueued(), 'debug');
        $this->log('[crawler] Skipped Links: ' . $statsHandler->getFiltered(), 'debug');
        $this->log('[crawler] Failed Links: ' . $statsHandler->getFailed(), 'debug');
        $this->log('[crawler] Persisted Links: ' . $statsHandler->getPersisted(), 'debug');

        $peakMem = round(memory_get_peak_usage(TRUE) / 1024 / 1024, 2);
        $totalDelay = round($politenessPolicyEventListener->totalDelay / 1000 / 1000, 2);

        $totalTime = microtime(TRUE) - $start;
        $totalTime = number_format((float)$totalTime, 3, '.', '');
        $minutes = str_pad(floor($totalTime / 60), 2, '0', STR_PAD_LEFT);
        $seconds = str_pad($totalTime % 60, 2, '0', STR_PAD_LEFT);

        $this->log('[crawler] Memory Peak Usage: ' . $peakMem . 'MB', 'debug');
        $this->log('[crawler] Total Time: ' . $minutes . ':' . $seconds, 'debug');
        $this->log('[crawler] Politeness Wait Time: ' . $totalDelay . ' seconds', 'debug');

        return $spider->getDownloader()->getPersistenceHandler();
    }
}