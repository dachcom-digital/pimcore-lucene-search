<?php

namespace LuceneSearch\Model;

use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use VDB\Spider\Filter\Prefetch\AllowedHostsFilter;
use VDB\Spider\Filter\Prefetch\AllowedSchemeFilter;
use VDB\Spider\Filter\Prefetch\UriFilter;
use VDB\Spider\Filter\Prefetch\UriWithHashFragmentFilter;
use VDB\Spider\Filter\Prefetch\UriWithQueryStringFilter;
use VDB\Spider\QueueManager\InMemoryQueueManager;
use VDB\Spider\Spider;
use VDB\Spider\StatsHandler;

use LuceneSearch\Plugin;
use LuceneSearch\Model\Logger;
use LuceneSearch\Crawler\Filter\NegativeUriFilter;

class Parser {

    /**
     * @var \Zend_Search_Lucene
     */
    protected $index = null;

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
     * @var boolean
     */
    protected $readyToCrawl = FALSE;

    /**
     * @var bool
     */
    protected $allowSubDomains = FALSE;

    protected $maxLinkDepth;

    public function __construct()
    {
        $this->checkAndPrepareIndex();
    }

    public function setDepth( $depth = 0 )
    {
        if( !is_numeric( $depth ) )
        {
            $depth = 1;
        }

        $this->maxLinkDepth = $depth;
        return $this;
    }

    public function setAllowSubdomain( $allowSubdomain = FALSE )
    {
        $this->allowSubDomains = $allowSubdomain;
        return $this;
    }

    public function setDownloadLimit( $downloadLimit = 0 )
    {
        $this->downloadLimit = $downloadLimit;
        return $this;
    }

    public function setValidLinkRegexes( $validLinkRegexes )
    {
        $this->validLinkRegexes = $validLinkRegexes;
        return $this;
    }

    public function setInvalidLinkRegexes( $invalidLinkRegexes )
    {
        $this->invalidLinkRegexes = $invalidLinkRegexes;
        return $this;
    }

    public function setSearchStartIndicator( $searchStartIndicator )
    {
        $this->searchStartIndicator = $searchStartIndicator;
        return $this;
    }

    public function setSearchEndIndicator( $searchEndIndicator )
    {
        $this->searchEndIndicator = $searchEndIndicator;
        return $this;
    }

    public function setSeed( $seed = '' )
    {
        $this->seed = $seed;
        return $this;
    }

    public function setMaxRedirects( $maxRedirects = '' )
    {
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    public function setTimeOut( $timeout = '' )
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Start Parsing Urls!
     */
    public function startParser()
    {
        $start = microtime();

        // Create spider
        $spider = new Spider( $this->seed );

        if( $this->downloadLimit > 0 )
        {
            $spider->getDownloader()->setDownloadLimit( $this->downloadLimit );
        }

        $statsHandler = new StatsHandler();
        $LogHandler = new Logger( \Pimcore::inDebugMode() );
        $queueManager = new InMemoryQueueManager();

        $queueManager->getDispatcher()->addSubscriber($statsHandler);
        $queueManager->getDispatcher()->addSubscriber($LogHandler);

        $spider->getDiscovererSet()->maxDepth = $this->maxLinkDepth;

        $queueManager->setTraversalAlgorithm(InMemoryQueueManager::ALGORITHM_BREADTH_FIRST);
        $spider->setQueueManager($queueManager);

        $spider->getDiscovererSet()->set(new XPathExpressionDiscoverer("//link[@hreflang]|//a") );

        $spider->getDiscovererSet()->addFilter(new AllowedSchemeFilter(array('http')));
        $spider->getDiscovererSet()->addFilter(new AllowedHostsFilter(array($this->seed), $this->allowSubDomains));

        $spider->getDiscovererSet()->addFilter(new UriWithHashFragmentFilter());
        $spider->getDiscovererSet()->addFilter(new UriWithQueryStringFilter());

        $spider->getDiscovererSet()->addFilter(new UriFilter( $this->invalidLinkRegexes ) );
        $spider->getDiscovererSet()->addFilter(new NegativeUriFilter( $this->validLinkRegexes ) );

        $politenessPolicyEventListener = new PolitenessPolicyListener( 1 ); //CHANGE TO 100 !!!!

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            array($politenessPolicyEventListener, 'onCrawlPreRequest')
        );

        $spider->getDispatcher()->addSubscriber($statsHandler);
        $spider->getDispatcher()->addSubscriber($LogHandler);

        $spider->getDispatcher()->addListener(

            SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
            function (Event $event) {
                \Logger::log('LuceneSearch: Crawl aborted by user.');
                exit;

            }

        );

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            function (Event $event) use ( $spider )
            {
                if( !file_exists( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp' ) )
                {
                    $spider->getDispatcher()->dispatch(SpiderEvents::SPIDER_CRAWL_USER_STOPPED);
                }

            }
        );

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_POST_REQUEST,
            function (Event $event)
            {
                echo  'crawling: ' . $event->getArgument('uri')->toString() . "\n";
            }
        );

        // Execute the crawl
        $result = $spider->crawl();

        echo "\n\nSPIDER ID: " . $statsHandler->getSpiderId();
        echo "\n  ENQUEUED:  " . count($statsHandler->getQueued());
        echo "\n  SKIPPED:   " . count($statsHandler->getFiltered());
        echo "\n  FAILED:    " . count($statsHandler->getFailed());
        echo "\n  PERSISTED: " . count($statsHandler->getPersisted());
        echo "\n";

        $peakMem = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $totalTime = round(microtime(true) - $start, 2);
        $totalDelay = round($politenessPolicyEventListener->totalDelay / 1000 / 1000, 2);

        echo "\n  METRICS:";
        echo "\n  PEAK MEM USAGE:       " . $peakMem . 'MB';
        echo "\n  TOTAL TIME:           " . $totalTime . 's';
        echo "\n  POLITENESS WAIT TIME: " . $totalDelay . 's';
        echo "\n\n";

        $downloaded = $spider->getDownloader()->getPersistenceHandler();

        foreach ($downloaded as $resource) {

            $this->parseResponse( $resource );

        }

    }


    /**
     * @param $response \Guzzle\Http\Message\Response
     */
    private function parseResponse( $response )
    {
        $resource = $response->getResponse();

        $host = $response->getUri()->getHost();
        $link = $response->getUri()->toString();

        $contentType = $resource->getHeader('Content-Type')->__toString();

        if (!empty($contentType))
        {
            $parts = explode(';', $contentType);
            $mimeType = trim($parts[0]);

            if ($mimeType == 'text/html')
            {
                $this->parseHtml($link, $response, $host);
            }
            else if ($mimeType == 'application/pdf')
            {
                $this->parsePdf($link, $response, $host);
            }
            else
            {
                \Logger::log('LuceneSearch: Cannot parse mime type [ ' . $mimeType. ' ] provided by link [ ' . $link . ' ] ' . \Zend_Log::ERR);
            }
        }
        else
        {
            \Logger::log('LuceneSearch: Could not determine content type of [ ' . $link. ' ] ' . \Zend_Log::ERR);
        }

    }

    private function parseHtml( $link, $response, $host )
    {
        $resource = $response->getResponse();
        $crawler = $response->getCrawler();

        $html = $resource->getBody();

        //page has canonical link: do not track!
        $hasCanonicalLink = $crawler->filterXpath('//link[@rel="canonical"]')->count() > 0;

        if( $hasCanonicalLink === TRUE )
        {
            \Logger::debug('LuceneSearch: not indexing [ ' . $link. ' ] because it has canonical links');
            return FALSE;
        }

        //page has no follow: do not track!
        $hasNoFollow = $crawler->filterXpath('//meta[@content="nofollow"]')->count() > 0;

        if( $hasNoFollow === TRUE )
        {
            \Logger::debug('LuceneSearch: not indexing [ ' . $link. ' ] because it has robots noindex');
            return FALSE;
        }

        $hasCountryMeta = $crawler->filterXpath('//meta[@name="country"]')->count() > 0;
        $hasTitle = $response->getCrawler()->filterXpath('//title')->count() > 0;

        $country = FALSE;
        $title = '';

        if( $hasCountryMeta === TRUE )
        {
            $country = $crawler->filterXpath('//meta[@name="country"]')->attr('content');
        }

        if( $hasTitle === TRUE )
        {
            $title = $response->getCrawler()->filterXpath('//title')->text();
        }

        $contentLength = (int) $resource->getHeader('Content-Length')->__toString();
        echo "\n - " . str_pad("[" . round($contentLength / 1024), 4, ' ', STR_PAD_LEFT) . "KB] $link - $title";

        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(true);

        $documentHasDelimiter = FALSE;

        //now limit to search content area if indicators are set and found in this document
        if (!empty($this->searchStartIndicator))
        {
            $documentHasDelimiter = strpos($html, $this->searchStartIndicator) !== FALSE;
        }

        if ($documentHasDelimiter && !empty($this->searchStartIndicator) && !empty($this->searchEndIndicator))
        {
            //get part before html head starts
            $top = explode('<head>', $html);

            //get html head
            $htmlHead = array();
            preg_match_all('@(<head[^>]*?>.*?</head>)@si', $html, $htmlHead);
            $head = $top[0] . '<head></head>';

            if (is_array($htmlHead[0]))
            {
                $head = $top[0] . $htmlHead[0][0];
            }

            //get snippets within allowed content areas
            $htmlSnippets = array();
            $minified = str_replace(array('\r\n', '\r', '\n'), '', $html);
            $minified = preg_replace('@[ \t\n\r\f]+@', ' ', $minified);

            preg_match_all('%' . $this->searchStartIndicator . '(.*?)' . $this->searchEndIndicator . '%si', $minified, $htmlSnippets);

            $html = $head;

            if (is_array($htmlSnippets[0]))
            {
                foreach ($htmlSnippets[0] as $snippet)
                {
                    $html .= ' ' . $snippet;
                }
            }

            //close html tag
            $html .= '</html>';

        }

        $language = $this->getLanguageFromResponse($resource, $html);
        $encoding = $this->getEncodingFromResponse($resource, $html);

        $this->addHtmlToIndex($html, $link, $language, $country, $encoding, $host);

        \Logger::info('LuceneSearch: Added to indexer stack [ ' . $link. ' ]');

        return true;

    }

    private function parsePdf( $link, $response, $host )
    {
        $resource = $response->getResponse();
        $html = $resource->getBody();
        $language = $this->getLanguageFromResponse($resource, $html);

        \Logger::log('LuceneSearch: Added pdf to index [ ' . $link . ' ]', \Zend_Log::INFO);

        return $this->addPdfToIndex($link, $language, $host);

    }


    /**
     * adds a PDF page to lucene index and mysql table for search result sumaries
     * @param  string $url
     * @param  string $language
     * @param string $host
     * @return void
     */
    protected function addPdfToIndex($url, $language, $host)
    {
        $pdftotextBin = FALSE;

        try
        {
            $pdftotextBin = \Pimcore\Document\Adapter\Ghostscript::getPdftotextCli();
        }
        catch (\Exception $e)
        {
            $pdftotextBin = FALSE;
        }

        if( $pdftotextBin === FALSE )
        {
            return FALSE;
        }

        $textFileTmp = uniqid('t2p-');
        $tmpFile = PIMCORE_TEMPORARY_DIRECTORY . '/' . $textFileTmp . '.txt';
        $tmpPdfFile = PIMCORE_TEMPORARY_DIRECTORY . '/' . $textFileTmp. '.pdf';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $data = curl_exec($ch);
        $result = file_put_contents($tmpPdfFile, $data);

        curl_close($ch);

        try
        {
            $cmnd = '-layout ' . $tmpPdfFile . ' ' . $tmpFile;
            exec( $pdftotextBin . ' ' . $cmnd);
        }
        catch( \Exception $e )
        {
        }

        if( is_file( $tmpFile ) )
        {
            $fileContent = file_get_contents( $tmpFile );

            try
            {
                $doc = new \Zend_Search_Lucene_Document();

                $doc->addField(\Zend_Search_Lucene_Field::Text('body',$fileContent,'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $language));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $url));

                //no add document to lucene index!
                $this->addDocumentToIndex( $doc );

            }
            catch (\Exception $e)
            {
                \Logger::log($e->getMessage());
            }

            @unlink( $tmpFile );
            @unlink( $tmpPdfFile );
        }

        return TRUE;


    }

    /**
     * adds a HTML page to lucene index and mysql table for search result summaries
     * @param  string $html
     * @param  string $url
     * @param  string $language
     * @param  string $country
     * @param  string $encoding
     * @param  string $host
     * @return void
     */
    protected function addHtmlToIndex($html, $url, $language, $country, $encoding, $host)
    {
        try
        {
            $content = $this->getPlainTextFromHtml($html);

            $doc = \Zend_Search_Lucene_Document_Html::loadHTML($html, false, 'utf-8');

            //add h1 to index
            $headlines = array();
            preg_match_all('@(<h1[^>]*?>[ \t\n\r\f]*(.*?)[ \t\n\r\f]*' . '</h1>)@si', $html, $headlines);

            if (is_array($headlines[2]))
            {
                $h1 = '';
                foreach ($headlines[2] as $headline)
                {
                    $h1 .= $headline . ' ';
                }

                $h1 = strip_tags($h1);
                $field = \Zend_Search_Lucene_Field::Text('h1', $h1, $encoding);
                $field->boost = 10;
                $doc->addField($field);
            }

            $doc->addField(\Zend_Search_Lucene_Field::Keyword('charset', $encoding));
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $language));
            $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $url));

            if( $country !== FALSE )
            {
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('country', $country));
            }

            //no add document to lucene index!
            $this->addDocumentToIndex( $doc );

        }
        catch (\Exception $e)
        {
            \Logger::log('LuceneSearch: ' . $e->getMessage(), \Zend_Log::ERR);
        }
    }

    /**
     * @param $doc \Zend_Search_Lucene_Document
     */
    public function addDocumentToIndex( $doc )
    {
        if ($doc instanceof \Zend_Search_Lucene_Document)
        {
            $this->index->addDocument($doc);
            \Logger::debug(get_class($this) . ': Added to lucene index db entry', \Zend_Log::DEBUG);
        }
        else
        {
            \Logger::error(get_class($this) . ': could not parse lucene document ', \Zend_Log::DEBUG);
        }

    }

    /**
     * Try to find the document's language by first looking for Content-Language in Http headers than in html
     * attribute and last in content-language meta tag
     * @return string
     */
    protected function getLanguageFromResponse($resource, $body)
    {
        $l = NULL;

        try
        {
            $cl = $resource->getHeader('Content-Language');

            if( !empty( $cl ) )
            {
                $l = $cl->__toString();
            }
        }
        catch( \Exception $e)
        {

        }

        if (empty($l))
        {
            //try html lang attribute
            $languages = array();
            preg_match_all('@<html[\n|\r\n]*.*?[\n|\r\n]*lang="(?P<language>\S+)"[\n|\r\n]*.*?[\n|\r\n]*>@si', $body, $languages);
            if ($languages['language'])
            {
                $l = str_replace(array('_', '-'), '', $languages['language'][0]);
            }
        }
        if (empty($l))
        {
            //try meta tag
            $languages = array();
            preg_match_all('@<meta\shttp-equiv="content-language"\scontent="(?P<language>\S+)"\s\/>@si', $body, $languages);
            if ($languages['language'])
            {
                //for lucene index remove '_' - this causes tokenization
                $l = str_replace('_', '', $languages['language'][0]);

            }
        }

        return $l;
    }

    /**
     * extract encoding either from HTTP Header or from HTML Attribute
     * @return string
     */
    protected function getEncodingFromResponse($resource, $body)
    {
        //try content-type header
        $contentType = NULL;

        try
        {
            $ct = $resource->getHeader('Content-Type');

            if( !empty( $ct ) )
            {
                $contentType = $ct->__toString();
            }

        }
        catch( \Exception $e)
        {

        }

        if (!empty($contentType))
        {
            $data = array();
            preg_match('@.*?;\s*charset=(.*)\s*@si', $contentType, $data);

            if ($data[1])
            {
                $encoding = trim($data[1]);
            }
        }
        if (empty($encoding))
        {
            //try html
            $data = array();
            preg_match('@<meta\shttp-equiv="Content-Type"\scontent=".*?;\s+charset=(.*?)"\s\/>@si', $body, $data);

            if ($data[1])
            {
                $encoding = trim($data[1]);
            }
        }
        if (empty($encoding))
        {
            //try xhtml
            $data = array();
            preg_match('@<\?xml.*?encoding="(.*?)"\s*\?>@si', $body, $data);

            if ($data[1])
            {
                $encoding = trim($data[1]);
            }
        }
        if (empty($encoding))
        {
            //try html 5
            $data = array();
            preg_match('@<meta\scharset="(.*?)"\s*>@si', $body, $data);

            if ($data[1])
            {
                $encoding = trim($data[1]);
            }
        }

        return $encoding;
    }

    /**
     *
     * removes html, javascript and additional whitespaces from string
     *
     * @param  $html
     * @return mixed|string
     */
    protected function getPlainTextFromHtml($html)
    {
        $doc = \Zend_Search_Lucene_Document_Html::loadHTML($html, false, 'utf-8');
        $html = $doc->getHTML();

        //remove scripts and stuff
        $search = array('@(<script[^>]*?>.*?</script>)@si', // Strip out javascript
            '@<style[^>]*?>.*?</style>@siU', // Strip style tags properly
            '@<![\s\S]*?--[ \t\n\r]*>@' // Strip multi-line comments including CDATA
        );

        $text = preg_replace($search, '', $html);
        //remove html tags
        $text = strip_tags($text);
        //remove additional whitespaces
        $text = preg_replace('@[ \t\n\r\f]+@', ' ', $text);

        return $text;

    }

    protected function checkAndPrepareIndex()
    {
        if (!$this->index)
        {
            $indexDir = Plugin::getFrontendSearchIndex();

            //switch to tmpIndex
            $indexDir = str_replace('/index', '/tmpindex', $indexDir);

            try
            {
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                $this->index = \Zend_Search_Lucene::open($indexDir);
            }
            catch (\Exception $e)
            {
                \Logger::log('LuceneSearch: could not open frontend index, creating new one.', \Zend_Log::WARN);
                \Zend_Search_Lucene::create($indexDir);
                $this->index = \Zend_Search_Lucene::open($indexDir);
            }
        }

    }

    public function optimizeIndex() {

        // optimize lucene index for better performance
        $this->index->optimize();

        //clean up
        if (is_object($this->index) and $this->index instanceof \Zend_Search_Lucene_Proxy)
        {
            $this->index->removeReference();
            unset($this->index);
            \Logger::log('LuceneSearch: Closed frontend index references',\Zend_Log::DEBUG);
        }

        \Logger::debug('LuceneSearch: optimizeIndex.');
    }
}