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


    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    protected $maxLinkDepth;

    public function __construct()
    {
        $this->db = \Pimcore\Db::get();

        $this->db->query( \LuceneSearch\Tool\Tool::getCrawlerQuery() );

        try
        {
            $result = $this->db->describeTable('plugin_lucenesearch_contents_temp');
            $this->readyToCrawl = !empty($result);
        }
        catch (\Zend_Db_Statement_Exception $e)
        {
            \Logger::alert('LuceneSearch: could not set up table for crawler contents.', \Zend_Log::ERR);
            $this->readyToCrawl = FALSE;
        }

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

        // Set some sane defaults for this example. We only visit the first level of www.dmoz.org. We stop at 10 queued resources
        $spider->getDiscovererSet()->maxDepth = $this->maxLinkDepth;

        // This time, we set the traversal algorithm to breadth-first. The default is depth-first
        $queueManager->setTraversalAlgorithm(InMemoryQueueManager::ALGORITHM_BREADTH_FIRST);
        $spider->setQueueManager($queueManager);

        // We add an URI discoverer. Without it, the spider wouldn't get past the seed resource.
        $spider->getDiscovererSet()->set(new XPathExpressionDiscoverer("//link[@hreflang]|//a") );

        // Add some prefetch filters. These are executed before a resource is requested.
        // The more you have of these, the less HTTP requests and work for the processors
        $spider->getDiscovererSet()->addFilter(new AllowedSchemeFilter(array('http')));
        $spider->getDiscovererSet()->addFilter(new AllowedHostsFilter(array($this->seed), $this->allowSubDomains));

        $spider->getDiscovererSet()->addFilter(new UriWithHashFragmentFilter());
        $spider->getDiscovererSet()->addFilter(new UriWithQueryStringFilter());

        $spider->getDiscovererSet()->addFilter(new UriFilter( $this->invalidLinkRegexes ) );
        $spider->getDiscovererSet()->addFilter(new NegativeUriFilter( $this->validLinkRegexes ) );

        // We add an eventlistener to the crawler that implements a politeness policy. We wait 450ms between every request to the same domain
        $politenessPolicyEventListener = new PolitenessPolicyListener( 1 ); //CHANGE TO 100 !!!!

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
            array($politenessPolicyEventListener, 'onCrawlPreRequest')
        );

        $spider->getDispatcher()->addSubscriber($statsHandler);
        $spider->getDispatcher()->addSubscriber($LogHandler);

        // Let's add something to enable us to stop the script
        $spider->getDispatcher()->addListener(

            SpiderEvents::SPIDER_CRAWL_USER_STOPPED,
            function (Event $event) {

                echo "\nCrawl aborted by user.\n";
                \Logger::log('LuceneSearch: Crawl aborted by user.');
                exit;

            }

        );

        // Let's add a CLI progress meter for fun
        echo "\nCrawling\n";

        $spider->getDownloader()->getDispatcher()->addListener(
            SpiderEvents::SPIDER_CRAWL_POST_REQUEST,
            function (Event $event)
            {
                echo  'crawling: ' . $event->getArgument('uri')->toString() . "\n";
            }
        );

        // Execute the crawl
        $result = $spider->crawl();

        // Report
        echo "\n\nSPIDER ID: " . $statsHandler->getSpiderId();
        echo "\n  ENQUEUED:  " . count($statsHandler->getQueued());
        echo "\n  SKIPPED:   " . count($statsHandler->getFiltered());
        echo "\n  FAILED:    " . count($statsHandler->getFailed());
        echo "\n  PERSISTED: " . count($statsHandler->getPersisted());

        // With the information from some of plugins and listeners, we can determine some metrics
        $peakMem = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $totalTime = round(microtime(true) - $start, 2);
        $totalDelay = round($politenessPolicyEventListener->totalDelay / 1000 / 1000, 2);

        echo "\n\nMETRICS:";
        echo "\n  PEAK MEM USAGE:       " . $peakMem . 'MB';
        echo "\n  TOTAL TIME:           " . $totalTime . 's';
        echo "\n  POLITENESS WAIT TIME: " . $totalDelay . 's';

        // Finally we could start some processing on the downloaded resources
        echo "\n\nDOWNLOADED RESOURCES: ";

        $downloaded = $spider->getDownloader()->getPersistenceHandler();

        foreach ($downloaded as $resource) {

            $this->parseResponse( $resource );

        }

    }

    /**
     * @return void writes lucene documents from db to lucene index
     */
    public function doIndex()
    {
        $this->db = \Pimcore\Db::reset();
        $this->checkAndPrepareIndex();

        do
        {
            $idsDone = array();
            $rows = $this->getIndexerRows();

            if ($rows !== FALSE and count($rows) > 0)
            {
                foreach ($rows as $row)
                {
                    $id = $row['id'];
                    $doc = unserialize($row['content']);
                    if ($doc instanceof \Zend_Search_Lucene_Document)
                    {
                        $this->index->addDocument($doc);
                        \Logger::debug(get_class($this) . ': Added to lucene index db entry id [ ' . $id . ' ] ', \Zend_Log::DEBUG);
                    }
                    else
                    {
                        \Logger::error(get_class($this) . ': could not unserialize lucene document from db row [ ' . $id . ' ] ', \Zend_Log::DEBUG);
                        \Logger::error(get_class($this) . ': string length: ' . strlen($row['content']));
                    }

                    $idsDone[] = $id;
                }
                try
                {
                    $this->db->delete('plugin_lucenesearch_indexer_todo', 'id in (' . implode(',', $idsDone) . ')');
                }
                catch (\Exception $e)
                {
                    \Logger::warn(get_class($this), ' Could not delete plugin_lucenesearch_indexer_todo - maybe forcing crawler stop right now?');
                }

            }

        } while ($rows !== FALSE and count($rows) > 0);

        // optimize lucene index for better performance
        $this->index->optimize();

        //clean up
        if (is_object($this->index) and $this->index instanceof \Zend_Search_Lucene_Proxy)
        {
            $this->index->removeReference();
            unset($this->index);
            \Logger::log('LuceneSearch: Closed frontend index references',\Zend_Log::DEBUG);
        }
    }

    /**
     * @param $response \Guzzle\Http\Message\Response
     */
    private function parseResponse( $response )
    {
        $resource = $response->getResponse();

        $title = '';

        $hasTitle = $response->getCrawler()->filterXpath('//title')->count() > 0;

        if( $hasTitle === TRUE )
        {
            $title = $response->getCrawler()->filterXpath('//title')->text();
        }


        $host = $response->getUri()->getHost();
        $link = $response->getUri()->toString();

        $contentLength = (int) $resource->getHeader('Content-Length')->__toString();
        $contentType = $resource->getHeader('Content-Type')->__toString();

        echo "\n - " . str_pad("[" . round($contentLength / 1024), 4, ' ', STR_PAD_LEFT) . "KB] $link - (" . $title. ")";

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

       // $canonicalLink = $this->checkForCanonical($html); // CHECK FOR CANONICAL: REDIRECT IF FOUND? @fixme!

        $hasNoFollow = $crawler->filterXpath('//meta[@content="nofollow"]')->count() > 0;
        $hasCountryMeta = $crawler->filterXpath('//meta[@name="country"]')->count() > 0;

        $country = FALSE;

        if( $hasCountryMeta === TRUE )
        {
            $country = $crawler->filterXpath('//meta[@name="country"]')->attr('content');
        }

        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(true);


        if ( $hasNoFollow === FALSE )
        {
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
        }
        else
        {
            $this->addNoIndexPage($link);
            \Logger::debug('LuceneSearch: not indexing [ ' . $link. ' ] because it has robots noindex');
        }

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
                $this->db->insert( 'plugin_lucenesearch_contents_temp',  array(
                        'id' => md5($url),
                        'uri' => $url,
                        'host' => $host,
                        'content' =>  $fileContent
                    )
                );

                $doc = new \Zend_Search_Lucene_Document();

                $doc->addField(\Zend_Search_Lucene_Field::Text('body',$fileContent,'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('lang', $language));
                $doc->addField(\Zend_Search_Lucene_Field::Keyword('url', $url));
                $serialized = serialize($doc);
                $this->db->insert('plugin_lucenesearch_indexer_todo', array('content' => $serialized));

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

            $this->db->insert('plugin_lucenesearch_contents_temp', array('id' => md5($url), 'uri' => $url, 'host' => $host, 'content' => $content, 'html' => $html));
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

            $serialized = serialize($doc);

            $this->db->insert('plugin_lucenesearch_indexer_todo', array('content' => $serialized));

        }
        catch (\Exception $e)
        {
            \Logger::log('LuceneSearch: ' . $e->getMessage(), \Zend_Log::ERR);
        }
    }

    protected function addNoIndexPage($url)
    {
        try
        {
            $this->db->insert('plugin_lucenesearch_frontend_crawler_noindex', array('id' => md5($url), 'uri' => $url));
            \Logger::log('LuceneSearch: Adding [ ' . $url. ' ] to noindex pages', \Zend_Log::DEBUG);
        }
        catch (\Exception $e)
        {

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

    /**
     * @return array|FALSE
     */
    protected function getIndexerRows()
    {
        try
        {
            $rows = $this->db->fetchAll('SELECT * FROM plugin_lucenesearch_indexer_todo ORDER BY id', array());
            return $rows;
        }
        catch (\Exception $e)
        {
            // probably table was already removed because crawler is finished
            \Logger::log('LuceneSearch: Could not extract next lucene document from table plugin_lucenesearch_frontend_crawler_todo ', \Zend_Log::DEBUG);
            return FALSE;
        }

    }

    public function collectGarbage() {

        $this->db->query('DROP TABLE IF EXISTS `plugin_lucenesearch_contents`;');
        $this->db->query('RENAME TABLE `plugin_lucenesearch_contents_temp` TO `plugin_lucenesearch_contents`;');

        \Logger::debug('LuceneSearch: replacing old index ...');
    }
}