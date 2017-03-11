<?php

namespace LuceneSearch\Model;

use LuceneSearch\Plugin;
use LuceneSearch\Crawler\Listener;
use LuceneSearch\Crawler\Filter\NegativeUriFilter;
use LuceneSearch\Crawler\Filter\MaxContentSizeFilter;
use LuceneSearch\Crawler\Filter\MimeTypeFilter;

use LuceneSearch\Crawler\PersistenceHandler;

use VDB\Spider\Spider;
use VDB\Spider\StatsHandler;
use VDB\Spider\QueueManager;
use VDB\Spider\Filter;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use GuzzleHttp\Middleware;

use Symfony\Component\EventDispatcher\Event;

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
     * Parser constructor.
     */
    public function __construct()
    {
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
     * Start Parsing Urls!
     */
    public function startParser()
    {
        $start = microtime();

        try {
            $spider = new Spider($this->seed);
        } catch (\Exception $e) {

            $this->log('Error: ' . $e->getMessage(), 'error');

            return;
        }

        if ($this->downloadLimit > 0) {
            $spider->getDownloader()->setDownloadLimit($this->downloadLimit);
        }

        $statsHandler = new StatsHandler();
        $LogHandler = new Logger(\Pimcore::inDebugMode());
        $queueManager = new QueueManager\InMemoryQueueManager();

        $queueManager->getDispatcher()->addSubscriber($statsHandler);
        $queueManager->getDispatcher()->addSubscriber($LogHandler);

        $spider->getDiscovererSet()->maxDepth = $this->maxLinkDepth;

        $queueManager->setTraversalAlgorithm(QueueManager\InMemoryQueueManager::ALGORITHM_DEPTH_FIRST);
        $spider->setQueueManager($queueManager);

        $spider->getDiscovererSet()->set(new XPathExpressionDiscoverer("//link[@hreflang]|//a[not(@rel='nofollow')]"));

        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\AllowedSchemeFilter($this->allowedSchemes));
        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\AllowedHostsFilter([$this->seed], $this->allowSubDomains));

        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithHashFragmentFilter());
        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriWithQueryStringFilter());

        $spider->getDiscovererSet()->addFilter(new Filter\Prefetch\UriFilter($this->invalidLinkRegexes));
        $spider->getDiscovererSet()->addFilter(new NegativeUriFilter($this->validLinkRegexes));

        $spider->getDispatcher()->addSubscriber($statsHandler);
        $spider->getDispatcher()->addSubscriber($LogHandler);

        $spider->getDownloader()->addPostFetchFilter(new MaxContentSizeFilter($this->contentMaxSize));
        $spider->getDownloader()->addPostFetchFilter(new MimeTypeFilter($this->validMimeTypes));

        $persistencePath = PIMCORE_SYSTEM_TEMP_DIRECTORY . '/ls-crawler-tmp';
        $spider->getDownloader()->setPersistenceHandler(new PersistenceHandler\FileSerializedResourcePersistenceHandler($persistencePath));

        $politenessPolicyEventListener = new PolitenessPolicyListener(20); //CHANGE TO 100 !!!!
        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            [$politenessPolicyEventListener, 'onCrawlPreRequest']
        );

        $abortListener = new Listener\Abort($spider);
        $spider->getDownloader()->getDispatcher()->addListener(

            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            [$abortListener, 'checkCrawlerState']

        );

        $spider->getDispatcher()->addListener(

            SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
            [$abortListener, 'stopCrawler']

        );

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_POST_REQUEST,
            function (Event $event) {
                //echo 'crawling: ' . $event->getArgument('uri')->toString() . "\n";
            }
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
        $pluginInfo = \Pimcore\ExtensionManager::getPluginConfig('LuceneSearch');
        $handler->push(Middleware::mapRequest(function (\Psr\Http\Message\RequestInterface $request) use ($pluginInfo) {
            return $request->withHeader('Lucene-Search', $pluginInfo['plugin']['pluginVersion']);
        }), 'lucene-search-header');

        // Execute the crawl
        try {
            $spider->crawl();
        } catch (\Exception $e) {
            $this->log('Crawl Error: ' . $e->getMessage(), 'error');
            throw new \Exception($e->getMessage());
        }

        $this->log('[Crawler] Spider Id: ' . $statsHandler->getSpiderId(), 'debug');
        $this->log('[Crawler] Enqueued Links: ' . count($statsHandler->getQueued()), 'debug');
        $this->log('[Crawler] Skipped Links: ' . count($statsHandler->getFiltered()), 'debug');
        $this->log('[Crawler] Failed Links: ' . count($statsHandler->getFailed()), 'debug');
        $this->log('[Crawler] Persisted Links: ' . count($statsHandler->getPersisted()), 'debug');

        $peakMem = round(memory_get_peak_usage(TRUE) / 1024 / 1024, 2);
        $totalTime = round(microtime(TRUE) - $start, 2);
        $totalDelay = round($politenessPolicyEventListener->totalDelay / 1000 / 1000, 2);

        $this->log('[Crawler] Memory Peak Usage:' . $peakMem . 'Mb', 'debug');
        $this->log('[Crawler] Total Time: ' . ($totalTime / 60) . ' Minutes', 'debug');
        $this->log('[Crawler] Politeness Wait Time: ' . $totalDelay . 'Seconds', 'debug');

        //parse all resources!
        /** @var \VDB\Spider\Resource $resource */
        foreach ($spider->getDownloader()->getPersistenceHandler() as $resource) {
            if ($resource instanceof \VDB\Spider\Resource) {
                $this->log('Parse Response: ' . $resource->getUri()->toString(), 'debug', FALSE);
                $this->parseResponse($resource);
            } else {
                $this->log('Crawler resource not a instance of \VDB\Spider\Resource. Given type: ' . gettype($resource), 'notice');
            }
        }
    }

    /**
     * @param \VDB\Spider\Resource $resource
     */
    private function parseResponse($resource)
    {
        $host = $resource->getUri()->getHost();
        $uri = $resource->getUri()->toString();

        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');

        if (!empty($contentTypeInfo)) {
            $parts = explode(';', $contentTypeInfo);
            $mimeType = trim($parts[0]);

            if ($mimeType == 'text/html') {
                $this->parseHtml($uri, $resource, $host);
            } else if ($mimeType == 'application/pdf') {
                $this->parsePdf($uri, $resource, $host);
            } else {
                $this->log('Cannot parse mime type [ ' . $mimeType . ' ] provided by uri [ ' . $uri . ' ]', 'debug');
            }
        } else {
            $this->log('Could not determine content type of [ ' . $uri . ' ]', 'debug');
        }
    }

    /**
     * @param                      $link
     * @param \VDB\Spider\Resource $resource
     * @param                      $host
     *
     * @return bool
     */
    private function parseHtml($link, $resource, $host)
    {
        /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
        $crawler = $resource->getCrawler();
        $html = $resource->getResponse()->getBody();
        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');
        $contentLanguage = $resource->getResponse()->getHeaderLine('Content-Language');

        $language = strtolower($this->getLanguageFromResponse($contentLanguage, $html));
        $encoding = strtolower($this->getEncodingFromResponse($contentTypeInfo, $html));

        $filter = new \Zend_Filter_Word_UnderscoreToDash();
        $language = strtolower($filter->filter($language));

        //page has canonical link: do not track!
        $hasCanonicalLink = $crawler->filterXpath('//link[@rel="canonical"]')->count() > 0;

        if ($hasCanonicalLink === TRUE) {
            $this->log('Not indexing [ ' . $link . ' ] because it has canonical links');

            return FALSE;
        }

        //page has no follow: do not track!
        $hasNoFollow = $crawler->filterXpath('//meta[@content="nofollow"]')->count() > 0;

        if ($hasNoFollow === TRUE) {
            $this->log('Not indexing [ ' . $link . ' ] because it has robots noindex');

            return FALSE;
        }

        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(TRUE);

        $hasCountryMeta = $crawler->filterXpath('//meta[@name="country"]')->count() > 0;
        $hasTitle = $crawler->filterXpath('//title')->count() > 0;
        $hasDescription = $crawler->filterXpath('//meta[@name="description"]')->count() > 0;
        $hasRestriction = $crawler->filterXpath('//meta[@name="m:groups"]')->count() > 0;
        $hasCustomMeta = $crawler->filterXpath('//meta[@name="lucene-search:meta"]')->count() > 0;
        $hasCustomBoostMeta = $crawler->filterXpath('//meta[@name="lucene-search:boost"]')->count() > 0;

        $title = '';
        $description = '';
        $customMeta = '';
        $customBoost = 1;

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

        $this->addHtmlToIndex($html, $title, $description, $link, $language, $country, $restrictions, $customMeta, $encoding, $host, $customBoost);

        $this->log('Added html to indexer stack: ' . $link);

        return TRUE;
    }

    /**
     * @param                      $link
     * @param \VDB\Spider\Resource $resource
     * @param                      $host
     *
     * @return bool
     */
    private function parsePdf($link, $resource, $host)
    {
        $this->log('Added pdf to indexer stack: ' . $link);

        $metaData = $this->getMetaDataFromAsset($link);
        return $this->addPdfToIndex($resource, $metaData['language'], $metaData['country'], $host);
    }

    /**
     * adds a PDF page to lucene index and mysql table for search result sumaries
     *
     * @param \VDB\Spider\Resource $resource
     * @param string               $language
     * @param string               $country
     * @param string               $host
     * @param integer              $customBoost
     *
     * @return bool
     */
    protected function addPdfToIndex($resource, $language, $country, $host, $customBoost = NULL)
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
            $this->log($e->getMessage());
        }

        $uri = $resource->getUri()->toString();

        if (is_file($tmpFile)) {
            $fileContent = file_get_contents($tmpFile);

            try {
                $doc = new \Zend_Search_Lucene_Document();

                $doc->boost = $customBoost ? $customBoost : $this->assetBoost;

                $text = preg_replace("/\r|\n/", ' ', $fileContent);
                $text = preg_replace('/[^\p{Latin}\d ]/u', '', $text);
                $text = preg_replace('/\n[\s]*/', "\n", $text); // remove all leading blanks

                $doc->addField(\Zend_Search_Lucene_Field::Text('title', basename($uri)), 'utf-8');
                $doc->addField(\Zend_Search_Lucene_Field::Text('content', $text, 'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $language));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('country', $country));

                $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $uri));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('host', $host));

                $doc->addField(\Zend_Search_Lucene_Field::Keyword('restrictionGroup_default', TRUE));

                //no add document to lucene index!
                $this->addDocumentToIndex($doc);
            } catch (\Exception $e) {
                $this->log($e->getMessage());
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
     * @param  string  $url
     * @param  string  $language
     * @param  string  $country
     * @param  string  $restrictions
     * @param  string  $customMeta
     * @param  string  $encoding
     * @param  string  $host
     * @param  integer $customBoost
     *
     * @return void
     */
    protected function addHtmlToIndex($html, $title, $description, $url, $language, $country, $restrictions, $customMeta, $encoding, $host, $customBoost = NULL)
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
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $url));
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

            //no add document to lucene index!
            $this->addDocumentToIndex($doc);
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * @param $doc \Zend_Search_Lucene_Document
     */
    public function addDocumentToIndex($doc)
    {
        if ($doc instanceof \Zend_Search_Lucene_Document) {
            $this->index->addDocument($doc);
            $this->log('Added to lucene index db entry', 'debug', FALSE);
        } else {
            $this->log('Could not parse lucene document', 'error');
        }
    }

    /**
     * Because Assets are not language or country restricted, we need to get them from db
     *
     * @param string $url
     *
     * @return array
     */
    protected function getMetaDataFromAsset($url = '')
    {
        $urlData = parse_url($url);
        $meta = ['language' => 'all', 'country' => 'all'];

        if (empty($urlData['path'])) {
            return $meta;
        }

        $asset = \Pimcore\Model\Asset::getByPath($urlData['path']);

        if ($asset instanceof \Pimcore\Model\Asset) {
            //check for assigned language
            $languageProperty = $asset->getProperty('assigned_language');
            if (!empty($languageProperty)) {
                $meta['language'] = $languageProperty;
            }

            //checked for assigned country
            $countryProperty = $asset->getProperty('assigned_country');
            if (!empty($countryProperty)) {
                $meta['country'] = $countryProperty;
            }
        }

        return $meta;
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

            try {
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                $this->index = \Zend_Search_Lucene::open($indexDir);
            } catch (\Exception $e) {
                $this->log('could not open frontend index, creating new one.', 'debug', FALSE);
                \Zend_Search_Lucene::create($indexDir);
                $this->index = \Zend_Search_Lucene::open($indexDir);
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
        \Pimcore\Logger::log('LuceneSearch: ' . $message, $level);

        if ($addToBackendLog === TRUE) {
            $file = PIMCORE_WEBSITE_VAR . '/search/log.txt';
            $current = '';
            if (file_exists($file)) {
                $current = file_get_contents($file);
            }
            $current .= date('d.m.Y H:i') . '|' . $level . '|' . $message . "\n";
            file_put_contents($file, $current);
        }

        return TRUE;
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
            $this->log('Closed frontend index references', 'debug', FALSE);
        }

        $this->log('Optimize Lucene Index', 'debug', FALSE);
    }
}