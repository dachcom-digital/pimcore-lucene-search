<?php

namespace LuceneSearch\Model;

use Pimcore\ExtensionManager;
use Pimcore\Model\Asset;
use LuceneSearch\Plugin;
use LuceneSearch\Crawler\Listener;
use LuceneSearch\Crawler\Filter\Discovery;
use LuceneSearch\Crawler\Filter\PostFetch;
use LuceneSearch\Crawler\Event\Logger;
use LuceneSearch\Crawler\Event\Statistics;
use LuceneSearch\Crawler\PersistenceHandler;

use VDB\Spider\Spider;
use VDB\Spider\QueueManager;
use VDB\Spider\Filter;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use GuzzleHttp\Middleware;

class Parser
{
    /**
     * @var \Zend_Search_Lucene
     */
    protected $index = NULL;

    /**
     * @var string[]
     */
    protected $seed;

    /**
     * @var string[]
     */
    protected $validLinkRegexes;

    /**
     * @var string[]
     */
    protected $invalidLinkRegexes;

    /**
     * @var \LuceneSearch\Model\Logger\Engine
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
     * @var int
     */
    protected $documentBoost = 1;

    /**
     * @var int
     */
    protected $assetBoost = 1;

    /**
     * @var null
     */
    Protected $authPassword = NULL;

    /**
     * @param \LuceneSearch\Model\Logger\Engine $logEngine
     * Parser constructor.
     */
    public function __construct($logEngine)
    {
        $this->logEngine = $logEngine;

        $this->checkAndPrepareIndex();
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
    public function setValidLinkRegexes($validLinkRegexes)
    {
        $this->validLinkRegexes = $validLinkRegexes;

        return $this;
    }

    /**
     * @param $invalidLinkRegexes
     *
     * @return $this
     */
    public function setInvalidLinkRegexes($invalidLinkRegexes)
    {
        $this->invalidLinkRegexes = $invalidLinkRegexes;

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
     * @param int $documentBoost
     *
     * @return $this
     */
    public function setDocumentBoost($documentBoost = 1)
    {
        $this->documentBoost = $documentBoost;

        return $this;
    }

    /**
     * @param int $assetBoost
     *
     * @return $this
     */
    public function setAssetBoost($assetBoost = 1)
    {
        $this->assetBoost = $assetBoost;

        return $this;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function startParser()
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

        $spider->getDiscovererSet()->addFilter(new Discovery\UriFilter($this->invalidLinkRegexes, $spider->getDispatcher()));
        $spider->getDiscovererSet()->addFilter(new Discovery\NegativeUriFilter($this->validLinkRegexes, $spider->getDispatcher()));

        $spider->getDownloader()->addPostFetchFilter(new PostFetch\MaxContentSizeFilter($this->contentMaxSize));
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

        // add LuceneSearch to Headers!
        $pluginInfo = ExtensionManager::getPluginConfig('LuceneSearch');
        $handler->push(Middleware::mapRequest(function (\Psr\Http\Message\RequestInterface $request) use ($pluginInfo) {
            return $request->withHeader('Lucene-Search', $pluginInfo['plugin']['pluginVersion']);
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

        $this->log('[crawler] Memory Peak Usage:' . $peakMem . 'Mb', 'debug');
        $this->log('[crawler] Total Time: ' . $minutes . ':' . $seconds, 'debug');
        $this->log('[crawler] Politeness Wait Time: ' . $totalDelay . ' seconds', 'debug');

        //parse all resources!
        /** @var \VDB\Spider\Resource $resource */
        foreach ($spider->getDownloader()->getPersistenceHandler() as $resource) {
            if ($resource instanceof \VDB\Spider\Resource) {
                $this->parseResponse($resource);
            } else {
                $this->log('[crawler] crawler resource not a instance of \VDB\Spider\Resource. Given type: ' . gettype($resource), 'notice');
            }
        }

        return TRUE;
    }

    /**
     * @param \VDB\Spider\Resource $resource
     */
    private function parseResponse($resource)
    {
        $host = $resource->getUri()->getHost();
        $uri = $resource->getUri()->toString();

        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');
        $provider = $resource->getResponse()->getHeaderLine('Provider');

        if (!empty($contentTypeInfo)) {
            $parts = explode(';', $contentTypeInfo);
            $mimeType = trim($parts[0]);

            if ($mimeType === 'text/html') {
                $this->parseHtml($resource, $host);
            } else if ($mimeType === 'application/pdf') {
                $this->parsePdf($resource, $host);
            } else {
                $this->log('[resource] cannot parse mime type [ ' . $mimeType . ' ] provided by uri [ ' . $uri . ' ]', 'debug');
            }
        } else {
            $this->log('[resource] could not determine content type of [ ' . $uri . ' ]', 'debug');
        }
    }

    /**
     * @param \VDB\Spider\Resource $resource
     * @param                      $host
     *
     * @return bool
     */
    private function parseHtml($resource, $host)
    {
        /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
        $crawler = $resource->getCrawler();

        $uri = $resource->getUri()->toString();
        $html = $resource->getResponse()->getBody();
        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');
        $contentLanguage = $resource->getResponse()->getHeaderLine('Content-Language');

        $language = strtolower($this->getLanguageFromResponse($contentLanguage, $html));
        $encoding = strtolower($this->getEncodingFromResponse($contentTypeInfo, $html));

        $filter = new \Zend_Filter_Word_UnderscoreToDash();
        $language = strtolower($filter->filter($language));

        //page has canonical link: do not track if this is not the canonical document
        $hasCanonicalLink = $crawler->filterXpath('//link[@rel="canonical"]')->count() > 0;

        if ($hasCanonicalLink === TRUE) {
            if($uri != $crawler->filterXpath('//link[@rel="canonical"]')->attr('href')){
                $this->log('[parser] skip indexing [ ' . $uri . ' ] because it has canonical link '.$crawler->filterXpath('//link[@rel="canonical"]')->attr('href').'');

                return FALSE;
            }
        }

        //page has no follow: do not track!
        $hasNoFollow = $crawler->filterXpath('//meta[@content="nofollow"]')->count() > 0;

        if ($hasNoFollow === TRUE) {
            $this->log('[parser] skip indexing [ ' . $uri . ' ] because it has a nofollow tag');

            return FALSE;
        }

        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(TRUE);

        $hasCountryMeta = $crawler->filterXpath('//meta[@name="country"]')->count() > 0;
        $hasTitle = $crawler->filterXpath('//title')->count() > 0;
        $hasDescription = $crawler->filterXpath('//meta[@name="description"]')->count() > 0;
        $hasRestriction = $crawler->filterXpath('//meta[@name="m:groups"]')->count() > 0;
        $hasCustomMeta = $crawler->filterXpath('//meta[@name="lucene-search:meta"]')->count() > 0;
        $hasCustomBoostMeta = $crawler->filterXpath('//meta[@name="lucene-search:boost"]')->count() > 0;
        $hasCategories = $crawler->filterXpath('//meta[@name="lucene-search:categories"]')->count() > 0;

        $title = '';
        $description = '';
        $customMeta = '';
        $customBoost = 1;
        $categories = FALSE;

        $restrictions = FALSE;
        $country = FALSE;

        if ($hasTitle === TRUE) {
            $title = $crawler->filterXpath('//title')->text();
        }

        if ($hasDescription === TRUE) {
            $description = $crawler->filterXpath('//meta[@name="description"]')->attr('content');
        }

        if ($hasCountryMeta === TRUE) {
            $country = $crawler->filterXpath('//meta[@name="country"]')->attr('content');
        }

        if ($hasRestriction === TRUE) {
            $restrictions = $crawler->filterXpath('//meta[@name="m:groups"]')->attr('content');
        }

        if ($hasCustomMeta === TRUE) {
            $customMeta = $crawler->filterXpath('//meta[@name="lucene-search:meta"]')->attr('content');
        }

        if ($hasCustomBoostMeta === TRUE) {
            $customBoost = (int)$crawler->filterXpath('//meta[@name="lucene-search:boost"]')->attr('content');
        }

        if ($hasCategories === TRUE) {
            $categories = $crawler->filterXpath('//meta[@name="lucene-search:categories"]')->attr('content');
        }

        $documentHasDelimiter = FALSE;
        $documentHasExcludeDelimiter = FALSE;

        //now limit to search content area if indicators are set and found in this document
        if (!empty($this->searchStartIndicator)) {
            $documentHasDelimiter = strpos($html, $this->searchStartIndicator) !== FALSE;
        }

        //remove content between exclude indicators
        if (!empty($this->searchExcludeStartIndicator)) {
            $documentHasExcludeDelimiter = strpos($html, $this->searchExcludeStartIndicator) !== FALSE;
        }

        if ($documentHasDelimiter && !empty($this->searchStartIndicator) && !empty($this->searchEndIndicator)) {
            preg_match_all('%' . $this->searchStartIndicator . '(.*?)' . $this->searchEndIndicator . '%si', $html, $htmlSnippets);

            $html = '';

            if (is_array($htmlSnippets[1])) {
                foreach ($htmlSnippets[1] as $snippet) {
                    if ($documentHasExcludeDelimiter && !empty($this->searchExcludeStartIndicator) && !empty($this->searchExcludeEndIndicator)) {
                        $snippet = preg_replace('#(' . preg_quote($this->searchExcludeStartIndicator) . ')(.*?)(' . preg_quote($this->searchExcludeEndIndicator) . ')#si', ' ', $snippet);
                    }

                    $html .= ' ' . $snippet;
                }
            }
        }

        $this->addHtmlToIndex($html, $title, $description, $link, $language, $country, $restrictions, $customMeta, $encoding, $host, $customBoost, $categories);

        $this->log('[parser] added html to indexer stack: ' . $uri);

        return TRUE;
    }

    /**
     * @param \VDB\Spider\Resource $resource
     * @param                      $host
     *
     * @return bool
     */
    private function parsePdf($resource, $host)
    {
        $this->log('[parser] added pdf to indexer stack: ' . $resource->getUri()->toString());
        return $this->addPdfToIndex($resource, $host);
    }

    /**
     * adds a PDF page to lucene index and mysql table for search result sumaries
     *
     * @param \VDB\Spider\Resource $resource
     * @param string               $host
     * @param integer              $customBoost
     *
     * @return bool
     */
    protected function addPdfToIndex($resource, $host, $customBoost = NULL)
    {
        try {
            $pdfToTextBin = \Pimcore\Document\Adapter\Ghostscript::getPdftotextCli();
        } catch (\Exception $e) {
            $pdfToTextBin = FALSE;
        }

        if ($pdfToTextBin === FALSE) {
            return FALSE;
        }

        $textFileTmp = uniqid('t2p-');
        $tmpFile = PIMCORE_TEMPORARY_DIRECTORY . '/' . $textFileTmp . '.txt';
        $tmpPdfFile = PIMCORE_TEMPORARY_DIRECTORY . '/' . $textFileTmp . '.pdf';

        file_put_contents($tmpPdfFile, $resource->getResponse()->getBody());

        $verboseCommand = \Pimcore::inDebugMode() ? '' : '-q ';

        try {
            $cmd = $verboseCommand . $tmpPdfFile . ' ' . $tmpFile;
            exec($pdfToTextBin . ' ' . $cmd);
        } catch (\Exception $e) {
            $this->log('[parser] ' . $e->getMessage());
        }

        $uri = $resource->getUri()->toString();
        $assetMeta = $this->getAssetMeta($uri);

        if (is_file($tmpFile)) {
            $fileContent = file_get_contents($tmpFile);

            try {
                $doc = new \Zend_Search_Lucene_Document();

                $doc->boost = $customBoost ? $customBoost : $this->assetBoost;

                $text = preg_replace("/\r|\n/", ' ', $fileContent);
                $text = preg_replace('/[^\p{Latin}\d ]/u', '', $text);
                $text = preg_replace('/\n[\s]*/', "\n", $text); // remove all leading blanks

                $title = $assetMeta['key'] !== FALSE ? $assetMeta['key'] : basename($uri);
                $doc->addField(\Zend_Search_Lucene_Field::Text('title', $title), 'utf-8');
                $doc->addField(\Zend_Search_Lucene_Field::Text('content', $text, 'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $assetMeta['language']));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('country', $assetMeta['country']));

                $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $uri));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('host', $host));

                if (is_array($assetMeta['restrictions'])) {
                    foreach ($assetMeta['restrictions'] as $restrictionGroup) {
                        $doc->addField(\Zend_Search_Lucene_Field::Keyword('restrictionGroup_' . $restrictionGroup, TRUE));
                    }
                } else if ($assetMeta['restrictions'] === NULL) {
                    $doc->addField(\Zend_Search_Lucene_Field::Keyword('restrictionGroup_default', TRUE));
                }

                //no add document to lucene index!
                $this->addDocumentToIndex($doc);
            } catch (\Exception $e) {
                $this->log('[parser] ' . $e->getMessage());
            }

            @unlink($tmpFile);
            @unlink($tmpPdfFile);
        }

        return TRUE;
    }

    /**
     * adds a HTML page to lucene index and mysql table for search result summaries
     *
     * @param  string  $html
     * @param  string  $title
     * @param  string  $description
     * @param  string  $uri
     * @param  string  $language
     * @param  string  $country
     * @param  string  $restrictions
     * @param  string  $customMeta
     * @param  string  $encoding
     * @param  string  $host
     * @param  integer $customBoost
     * @param  string  $categories
     *
     * @return void
     */
    protected function addHtmlToIndex($html, $title, $description, $url, $language, $country, $restrictions, $customMeta, $encoding, $host, $customBoost = NULL, $categories = FALSE)
    {
        try {
            $content = $this->getPlainTextFromHtml($html);

            $doc = new \Zend_Search_Lucene_Document();
            $doc->boost = $customBoost ? $customBoost : $this->documentBoost;

            //add h1 to index
            $headlines = [];
            preg_match_all('@(<h1[^>]*?>[ \t\n\r\f]*(.*?)[ \t\n\r\f]*' . '</h1>)@si', $html, $headlines);

            if (is_array($headlines[2])) {
                $h1 = '';
                foreach ($headlines[2] as $headline) {
                    $h1 .= $headline . ' ';
                }

                $h1 = strip_tags($h1);
                $field = \Zend_Search_Lucene_Field::Text('h1', $h1, $encoding);
                $field->boost = 10;
                $doc->addField($field);
            }

            $imageTags = $this->extractImageAltText($html);

            $tags = [];
            if (!empty($imageTags)) {
                foreach ($imageTags as $imageTag) {
                    $tags[] = $imageTag['alt'];
                }
            }

            //clean meta
            $customMeta = strip_tags($customMeta);

            $doc->addField(\Zend_Search_Lucene_Field::Keyword('charset', $encoding));
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $language));
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $uri));
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('host', $host));

            $doc->addField(\Zend_Search_Lucene_Field::Text('title', $title, $encoding));
            $doc->addField(\Zend_Search_Lucene_Field::Text('description', $description, $encoding));
            $doc->addField(\Zend_Search_Lucene_Field::Text('customMeta', $customMeta, $encoding));

            $doc->addField(\Zend_Search_Lucene_Field::Text('content', $content, $encoding));
            $doc->addField(\Zend_Search_Lucene_Field::Text('imageTags', join(',', $tags)));

            if ($country !== FALSE) {
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('country', $country));
            }

            if ($restrictions !== FALSE) {
                $restrictionGroups = explode(',', $restrictions);
                foreach ($restrictionGroups as $restrictionGroup) {
                    $doc->addField(\Zend_Search_Lucene_Field::Keyword('restrictionGroup_' . $restrictionGroup, TRUE));
                }
            }

            if ($categories !== FALSE) {
                $validCategories = \LuceneSearch\Model\Configuration::get('frontend.categories');
                if(!empty($validCategories)) {
                    $validIds = [];
                    $categoryIds = explode(',', $categories);
                    foreach ($categoryIds as $categoryId) {
                        if(in_array($categoryId, $validCategories)) {
                            $validIds[] = $categoryId;
                            $doc->addField(\Zend_Search_Lucene_Field::Keyword('category_' . $categoryId, 'category_' . $categoryId));
                        }
                    }
                    if(!empty($validIds)) {
                        $doc->addField(\Zend_Search_Lucene_Field::Text('categories', implode(',', $validIds)));
                    }
                }

            }

            //no add document to lucene index!
            $this->addDocumentToIndex($doc);
        } catch (\Exception $e) {
            $this->log('[lucene] ' . $e->getMessage());
        }
    }

    /**
     * @param $doc \Zend_Search_Lucene_Document
     */
    public function addDocumentToIndex($doc)
    {
        if ($doc instanceof \Zend_Search_Lucene_Document) {
            $this->index->addDocument($doc);
        } else {
            $this->log('[lucene] could not parse lucene document', 'error');
        }
    }

    /**
     * @param string $link
     *
     * @return array
     */
    protected function getAssetMeta($link)
    {
        $assetMetaData = [
            'language'     => 'all',
            'country'      => 'all',
            'key'          => FALSE,
            'restrictions' => FALSE
        ];

        if (empty($link) || !is_string($link)) {
            return $assetMetaData;
        }

        $hasPossibleRestriction = FALSE;

        if (ExtensionManager::isEnabled('plugin', 'Members')) {
            $hasPossibleRestriction = TRUE;
        }

        $asset = FALSE;
        $restrictions = FALSE;

        $pathFragments = parse_url($link);
        $assetPath = $pathFragments['path'];

        //members extension is available and it's a restricted asset
        if ($hasPossibleRestriction && strpos($assetPath, 'members/request-data') !== FALSE) {
            try {
                $method = new \ReflectionMethod('\Members\Tool\UrlServant', 'getAssetUrlInformation');
                if ($method->isStatic()) {
                    $key = end(explode('/', $assetPath));
                    $restrictedAssetInfo = \Members\Tool\UrlServant::getAssetUrlInformation($key);
                    if ($restrictedAssetInfo !== FALSE) {
                        $asset = $restrictedAssetInfo['asset'];
                        $restrictions = $restrictedAssetInfo['restrictionGroups'];
                    }
                }
            } catch(\ReflectionException $e) {
                $this->log('[parser] ' . $e->getMessage(), 'error');
            }

        } else {
            $asset = Asset::getByPath($assetPath);
        }

        if (!$asset instanceof Asset) {
            return $assetMetaData;
        }

        $assetMetaData['restrictions'] = $restrictions;

        //check for assigned language
        $languageProperty = $asset->getProperty('assigned_language');
        if (!empty($languageProperty)) {
            $assetMetaData['language'] = $languageProperty;
        }

        //checked for assigned country
        $countryProperty = $asset->getProperty('assigned_country');
        if (!empty($countryProperty)) {
            $assetMetaData['country'] = $countryProperty;
        }

        $assetMetaData['key'] = $asset->getKey();

        return $assetMetaData;
    }

    /**
     * @param $contentLanguage
     * @param $body
     * Try to find the document's language by first looking for Content-Language in Http headers than in html
     * attribute and last in content-language meta tag
     *
     * @return string
     */
    protected function getLanguageFromResponse($contentLanguage, $body)
    {
        $l = $contentLanguage;

        if (empty($l)) {
            //try html lang attribute
            $languages = [];
            preg_match_all('@<html[\n|\r\n]*.*?[\n|\r\n]*lang="(?P<language>\S+)"[\n|\r\n]*.*?[\n|\r\n]*>@si', $body, $languages);
            if ($languages['language']) {
                $l = str_replace(['_', '-'], '', $languages['language'][0]);
            }
        }

        if (empty($l)) {
            //try meta tag
            $languages = [];
            preg_match_all('@<meta\shttp-equiv="content-language"\scontent="(?P<language>\S+)"\s\/>@si', $body, $languages);
            if ($languages['language']) {
                //for lucene index remove '_' - this causes tokenization
                $l = str_replace('_', '', $languages['language'][0]);
            }
        }

        return $l;
    }

    /**
     * @param $contentType
     * @param $body
     * extract encoding either from HTTP Header or from HTML Attribute
     *
     * @return string
     */
    protected function getEncodingFromResponse($contentType, $body)
    {
        $encoding = '';

        //try content-type header
        if (!empty($contentType)) {
            $data = [];
            preg_match('@.*?;\s*charset=(.*)\s*@si', $contentType, $data);

            if ($data[1]) {
                $encoding = trim($data[1]);
            }
        }

        if (empty($encoding)) {
            //try html
            $data = [];
            preg_match('@<meta\shttp-equiv="Content-Type"\scontent=".*?;\s+charset=(.*?)"\s\/>@si', $body, $data);

            if ($data[1]) {
                $encoding = trim($data[1]);
            }
        }

        if (empty($encoding)) {
            //try xhtml
            $data = [];
            preg_match('@<\?xml.*?encoding="(.*?)"\s*\?>@si', $body, $data);

            if ($data[1]) {
                $encoding = trim($data[1]);
            }
        }

        if (empty($encoding)) {
            //try html 5
            $data = [];
            preg_match('@<meta\scharset="(.*?)"\s*>@si', $body, $data);

            if ($data[1]) {
                $encoding = trim($data[1]);
            }
        }

        return $encoding;
    }

    /**
     * removes html, javascript and additional whitespaces from string
     *
     * @param  $html
     *
     * @return mixed|string
     */
    protected function getPlainTextFromHtml($html)
    {
        $doc = \Zend_Search_Lucene_Document_Html::loadHTML($html, FALSE, 'utf-8');
        $html = $doc->getHTML();

        //remove scripts and stuff
        $search = [
            '@(<script[^>]*?>.*?</script>)@si', // Strip out javascript
            '@<style[^>]*?>.*?</style>@siU', // Strip style tags properly
            '@<![\s\S]*?--[ \t\n\r]*>@' // Strip multi-line comments including CDATA
        ];

        $text = preg_replace($search, '', $html);
        //remove html tags
        $text = strip_tags($text);
        //remove additional whitespaces
        $text = preg_replace('@[ \t\n\r\f]+@', ' ', $text);

        return $text;
    }

    /**
     * @param $html
     *
     * @return array
     */
    protected function extractImageAltText($html)
    {
        libxml_use_internal_errors(TRUE);

        $doc = new \DOMDocument();
        $data = [];
        $imageTags = [];

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        if (empty($html)) {
            return [];
        }

        try {
            $doc->loadHTML($html);
            $imageTags = $doc->getElementsByTagName('img');
        } catch (\Exception $e) {
            //do nothing. just die trying.
        }

        foreach ($imageTags as $tag) {
            $alt = $tag->getAttribute('alt');

            if (in_array($alt, ['', 'Image is not available', 'Image not available'])) {
                continue;
            }

            $data[] = [
                'src'   => $tag->getAttribute('src'),
                'title' => $tag->getAttribute('title'),
                'alt'   => $alt
            ];
        }

        return $data;
    }

    /**
     *
     */
    protected function checkAndPrepareIndex()
    {
        if (!$this->index) {
            $indexDir = Plugin::getFrontendSearchIndex();

            //switch to tmpIndex
            $indexDir = str_replace('/index', '/tmpindex', $indexDir);

            //always create new tmp index.
            try {
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                \Zend_Search_Lucene::create($indexDir);
                $this->index = \Zend_Search_Lucene::open($indexDir);
            } catch (\Exception $e) {
                $this->log('[lucene] ' . $e->getMessage(), 'debug', FALSE);
            }
        }
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

    /**
     *
     */
    public function optimizeIndex()
    {
        // optimize lucene index for better performance
        $this->index->optimize();

        //clean up
        if (is_object($this->index) and $this->index instanceof \Zend_Search_Lucene_Proxy) {
            $this->index->removeReference();
            unset($this->index);
            $this->log('[lucene] ' . 'closed frontend index references', 'debug', FALSE);
        }

        $this->log('[lucene] ' . 'optimize lucene index', 'debug', FALSE);
    }
}