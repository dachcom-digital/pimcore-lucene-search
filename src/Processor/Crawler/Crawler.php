<?php

namespace LuceneSearchBundle\Processor\Crawler;

use LuceneSearchBundle\Processor\Crawler\Listener;
use LuceneSearchBundle\Processor\Crawler\Filter\Discovery;
use LuceneSearchBundle\Processor\Crawler\Filter\PostFetch;
use LuceneSearchBundle\Processor\Crawler\Event\Logger;
use LuceneSearchBundle\Processor\Crawler\Event\Statistics;
use LuceneSearchBundle\Processor\Crawler\PersistenceHandler;

use VDB\Spider\Spider;
use VDB\Spider\QueueManager;
use VDB\Spider\Filter;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use GuzzleHttp\Middleware;

class Crawler
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
     * @var \LuceneSearchBundle\Logger\Engine
     */
    protected $logEngine;

    /**
     * Set max crawl content size (MB)
     * 0 means no limit
     * @var integer
     */
    protected $contentMaxSize = 0;

    /**
     * @var integer
     */
    protected $maxRedirects = 10;

    /**
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

    /**
     * @param $logEngine
     */
    public function setLogger($logEngine)
    {
        $this->logEngine = $logEngine;
    }

    /**
     * @param int $depth
     *
     * @return $this
     */
    public function setDepth($depth = 0)
    {
        if (!is_numeric($depth)) {
            $depth = 1;
        }

        $this->maxLinkDepth = $depth;

        return $this;
    }

    /**
     * @param bool $allowSubdomain
     *
     * @return $this
     */
    public function setAllowSubdomain($allowSubdomain = FALSE)
    {
        $this->allowSubDomains = $allowSubdomain;

        return $this;
    }

    /**
     * @param int $downloadLimit
     *
     * @return $this
     */
    public function setDownloadLimit($downloadLimit = 0)
    {
        $this->downloadLimit = $downloadLimit;

        return $this;
    }

    /**
     * @param array $allowedSchemes
     *
     * @return $this
     */
    public function setAllowedSchemes($allowedSchemes = [])
    {
        $this->allowedSchemes = $allowedSchemes;

        return $this;
    }

    /**
     * @param $validLinkRegexes
     *
     * @return $this
     */
    public function setValidLinks($validLinkRegexes)
    {
        $this->validLinks = $validLinkRegexes;

        return $this;
    }

    /**
     * @param $invalidLinkRegexes
     *
     * @return $this
     */
    public function setInvalidLinks($invalidLinkRegexes)
    {
        $this->invalidLinks = $invalidLinkRegexes;

        return $this;
    }

    /**
     * @param $contentMaxSize
     *
     * @return $this
     */
    public function setContentMaxSize($contentMaxSize = 0)
    {
        $this->contentMaxSize = $contentMaxSize;

        return $this;
    }

    /**
     * @param $mimeTypes
     *
     * @return $this
     */
    public function setValidMimeTypes($mimeTypes = [])
    {
        if (!is_array($mimeTypes)) {
            return $this;
        }

        $this->validMimeTypes = $mimeTypes;

        return $this;
    }

    /**
     * @param $searchStartIndicator
     *
     * @return $this
     */
    public function setSearchStartIndicator($searchStartIndicator)
    {
        $this->searchStartIndicator = $searchStartIndicator;

        return $this;
    }

    /**
     * @param $searchEndIndicator
     *
     * @return $this
     */
    public function setSearchEndIndicator($searchEndIndicator)
    {
        $this->searchEndIndicator = $searchEndIndicator;

        return $this;
    }

    /**
     * @param $searchExcludeStartIndicator
     *
     * @return $this
     */
    public function setSearchExcludeStartIndicator($searchExcludeStartIndicator)
    {
        $this->searchExcludeStartIndicator = $searchExcludeStartIndicator;

        return $this;
    }

    /**
     * @param $searchExcludeEndIndicator
     *
     * @return $this
     */
    public function setSearchExcludeEndIndicator($searchExcludeEndIndicator)
    {
        $this->searchExcludeEndIndicator = $searchExcludeEndIndicator;

        return $this;
    }

    /**
     * @param string $maxRedirects
     *
     * @return $this
     */
    public function setMaxRedirects($maxRedirects = '')
    {
        $this->maxRedirects = $maxRedirects;

        return $this;
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

    /**
     * @param string $timeout
     *
     * @return $this
     */
    public function setTimeOut($timeout = '')
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $seed
     *
     * @return $this
     */
    public function setSeed($seed = '')
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * @return bool|\VDB\Spider\PersistenceHandler\PersistenceHandlerInterface
     * @throws \Exception
     */
    public function fetchCrawlerResources()
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
        $logHandler = new Logger(\Pimcore::inDebugMode(), $this->logEngine);
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

            $handler->push(Middleware::mapRequest(function (\Psr\Http\Message\RequestInterface $request) use ($un, $pw) {
                return $request->withHeader('Authorization', 'Basic ' . base64_encode("$un:$pw"));
            }), 'lucene-search-auth');
        }

        // add LuceneSearch to Headers! @todo: get version of plugin!
        //$pluginInfo = \Pimcore\ExtensionManager::getPluginConfig('LuceneSearch');
        $pluginVersion = '2.0.0';

        $handler->push(Middleware::mapRequest(function (\Psr\Http\Message\RequestInterface $request) use ($pluginVersion) {
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


    /**
     * @param string $message
     * @param string $level
     * @param bool   $addToBackendLog
     *
     * @return bool
     */
    protected function log($message = '', $level = 'debug', $addToBackendLog = TRUE)
    {
        return $this->logEngine->log($message, $level, $addToBackendLog);
    }
}