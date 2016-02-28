<?php

namespace LuceneSearch\Model;

use LuceneSearch\Plugin;

class Crawler
{

    /**
     * @var integer
     *
     */
    protected $maxThreads;

    /**
     * @var string[]
     */
    protected $links;

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
    protected $maxRedirects;

    /**
     * @var integer
     */
    protected $timeout;

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
    protected $readyToCrawl;

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $db;

    protected $maxLinkDepth;

    /**
     * @param      $validLinkRegexes
     * @param      $invalidLinkRegexes
     * @param int  $maxRedirects
     * @param int  $timeout
     * @param null $searchStartIndicator
     * @param null $searchEndIndicator
     * @param int  $maxThreads
     * @param int  $maxLinkDepth
     */
    public function __construct($validLinkRegexes, $invalidLinkRegexes, $maxRedirects = 10, $timeout = 30, $searchStartIndicator = null, $searchEndIndicator = null, $maxThreads = 20, $maxLinkDepth = 15)
    {
        $this->validLinkRegexes = $validLinkRegexes;
        $this->invalidLinkRegexes = $invalidLinkRegexes;
        $this->maxRedirects = $maxRedirects;
        $this->timeout = $timeout;
        $this->searchEndIndicator = $searchEndIndicator;
        $this->searchStartIndicator = $searchStartIndicator;
        $this->maxThreads = $maxThreads;
        $this->maxLinkDepth = $maxLinkDepth;

        $this->db = \Pimcore\Db::get();

        $this->db->query( \LuceneSearch\Tool\Tool::getCrawlerQuery() );

        $result = null;

        try
        {
            $result = $this->db->describeTable('plugin_lucenesearch_contents_temp');
            $this->readyToCrawl = !empty($result);
        }
        catch (\Zend_Db_Statement_Exception $e)
        {
            \Logger::alert(get_class($this) . ': could not set up table for crawler contents.', \Zend_Log::ERR);
            $this->readyToCrawl = false;
        }
    }


    /**
     * @return string[]
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * @param  string[] $urls
     * @return void
     */
    public function findLinks($urls)
    {
        //inital for all urls
        $cookieJar = new \Zend_Http_CookieJar();

        foreach ($urls as $url)
        {
            try
            {
                $uri = \Zend_Uri_Http::fromString($url);
                $url = str_ireplace($uri->getHost(), strtolower($uri->getHost()), $url);
            }
            catch (\Zend_Uri_Exception $e)
            {
            }

            $url = $this->addEvictOutputFilterParameter($url);

            $client = \Pimcore\Tool::getHttpClient();
            $client->setUri($url);
            $client->setConfig(
                array(
                    'maxredirects' => $this->maxRedirects,
                    'timeout' => $this->timeout
                )
            );

            $client->setCookieJar($cookieJar);
            $client->setHeaders('If-Modified-Since', null);

            $response = NULL;

            try
            {
                $response = $client->request();
            }
            catch (\Zend_Http_Client_Adapter_Exception $e)
            {
                \Logger::log(get_class($this) . ': Could not get response for Link [ ' . $url .' ] ', \Zend_Log::ERR);
            }

            if ($response instanceof \Zend_Http_Response && ($response->isSuccessful() || $response->isRedirect())) {

                //we don't use port - crawler ist limited to standard port 80
                $client->getUri()->setPort(null);
                //update url - maybe we were redirected
                $url = $client->getUri(true);
                $url = $this->removeOutputFilterParameters($url);

                try
                {
                    $success = $this->parse($url, $response, $client->getUri()->getHost(), $client->getCookieJar(), 0);
                    \Logger::log(get_class($this) . ': parsed entry point  [ ' . $url .' ] ', \Zend_Log::INFO);

                }
                catch (\Exception $e)
                {
                    \Logger::log($e);
                }

            }
            else
            {
                \Logger::log(get_class($this) . ': Invalid Respose for URL  [ ' . $url .' ] ', \Zend_Log::DEBUG);
            }

        }

        $manager = \Pimcore\Model\Schedule\Manager\Factory::getManager('lucenesearchcrawlermanager.pid');

        for ($i = 1; $i <= $this->maxThreads; $i++)
        {
            $manager->registerJob(new \Pimcore\Model\Schedule\Maintenance\Job('crawler-' . $i, $this, 'continueWithFoundLinks', array()));
        }

        $manager->registerJob(new \Pimcore\Model\Schedule\Maintenance\Job('crawler-indexer', $this, 'doIndex', array()));
        $manager->run();

        //make sure that there are no more links left in DB
        $this->continueWithFoundLinks();

        //final indexer run
        $this->doIndex(true);

    }

    /**
     * @param int $delay
     * @return array|FALSE
     */
    protected function getIndexerRows($delay = 0)
    {
        if ($delay > 0)
        {
            sleep($delay);
        }
        try
        {
            $rows = $this->db->fetchAll('SELECT * FROM plugin_lucenesearch_indexer_todo ORDER BY id', array());
            return $rows;

        }
        catch (\Exception $e)
        {
            // probably table was already removed because crawler is finished
            \Logger::log(get_class($this) . ': Could not extract next lucene document from table plugin_lucenesearch_frontend_crawler_todo ', \Zend_Log::DEBUG);
            return FALSE;
        }

    }

    /**
     * @param bool $final
     *
     * @return void writes lucene documents from db to lucene index
     */
    public function doIndex($final = false)
    {
        $this->db = \Pimcore\Db::reset();
        $this->checkAndPrepareIndex();

        //start with delay
        sleep(3);

        $counter = 1;

        do
        {
            $idsDone = array();
            $rows = $this->getIndexerRows(0);

            if (!$final and !$rows === FALSE)
            {
                //try again with delay
                if (!is_array($rows) or count($rows) == 0)
                {
                    $rows = $this->getIndexerRows(5);
                }
                if (!is_array($rows) or count($rows) == 0)
                {
                    $rows = $this->getIndexerRows(10);
                }
                if (!is_array($rows) or count($rows) == 0)
                {
                    $rows = $this->getIndexerRows(20);
                }
            }

            if ($rows !== FALSE and count($rows) > 0)
            {
                foreach ($rows as $row)
                {
                    $id = $row['id'];
                    $doc = unserialize($row['content']);
                    if ($doc instanceof \Zend_Search_Lucene_Document)
                    {
                        $this->index->addDocument($doc);
                        \Logger::debug(get_class($this) . ': Added to lucene index db entry id [ $id ] ', \Zend_Log::DEBUG);
                    }
                    else
                    {
                        \Logger::error(get_class($this) . ': could not unserialize lucene document from db row [ $id ] ', \Zend_Log::DEBUG);
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
            \Logger::log(get_class($this) . ': Closed frontend index references',\Zend_Log::DEBUG);
        }
    }

    /**
     * @return void
     */
    public function continueWithFoundLinks()
    {

        //reset DB in case this is executed in a forked child process
        $this->db = \Pimcore\Db::reset();

        try
        {
            $row = $this->db->fetchRow('SELECT * FROM plugin_lucenesearch_frontend_crawler_todo ORDER BY id', array());
            $nextLink = $row['uri'];
            $depth = $row['depth'];
            $cookieJar = unserialize($row['cookiejar']);
        }
        catch (\Exception $e)
        {
            // probably table was already removed because crawler is finished
            \Logger::log(get_class($this) . ': Could not extract next link from table plugin_lucenesearch_frontend_crawler_todo ', \Zend_Log::DEBUG);
            return;
        }

        if (empty($nextLink))
        {
            return;
        }

        $client = \Pimcore\Tool::getHttpClient();
        $client->setUri($nextLink);
        $client->setConfig(array(
            'maxredirects' => $this->maxRedirects,
            'keepalive' => true,
            'timeout' => $this->timeout
            )
        );

        $client->setCookieJar($cookieJar);
        $client->setHeaders('If-Modified-Since', null);

        while ($nextLink)
        {
            try
            {
                $this->db->delete('plugin_lucenesearch_frontend_crawler_todo', 'id = "' . md5($nextLink) . '"');
            }
            catch (\Exception $e)
            {
                \Logger::warn(get_class($this) . ': Could not delete from plugin_lucenesearch_frontend_crawler_todo - maybe forcing crawler stop right now?');
            }

            if ($depth <= $this->maxLinkDepth)
            {
                \Logger::debug(get_class($this) . ': Link depth [ ' . $depth . ' ]');

                try
                {
                    $nextLink = $this->addEvictOutputFilterParameter($nextLink);
                    $client->setUri($nextLink);
                    $client->setCookieJar($cookieJar);
                    $client->setHeaders('If-Modified-Since', null);

                    $response = NULL;

                    try
                    {
                        $response = $client->request();

                    } catch (\Zend_Http_Client_Adapter_Exception $e)
                    {
                        \Logger::log(get_class($this) . ': Could not get response for Link [ ' . $nextLink . ' ] ', \Zend_Log::ERR);
                    }

                    if ($response instanceof \Zend_Http_Response and ($response->isSuccessful() or $response->isRedirect()))
                    {
                        //we don't use port - crawler ist limited to standard port 80
                        $client->getUri()->setPort(null);

                        //update url - maybe we were redirected
                        $nextLink = $client->getUri(true);
                        $nextLink = $this->removeOutputFilterParameters($nextLink);

                        $valid = $this->validateLink($nextLink);

                        if ($valid)
                        {
                            //see if we were redirected to a place we already have in fetch list or done
                            try
                            {
                                $rowTodo = $this->db->fetchRow('SELECT count(*) as count from plugin_lucenesearch_frontend_crawler_todo WHERE id ="' . md5($nextLink) . '"');
                            }
                            catch (\Exception $e)
                            {
                                \Logger::log(get_class($this) . ': could not fetch from plugin_lucenesearch_contents_temp', \Zend_Log::DEBUG);
                            }

                            try
                            {
                                $rowDone = $this->db->fetchRow('SELECT count(*) as count from plugin_lucenesearch_contents_temp WHERE id ="' . md5($nextLink) . '"');
                            }
                            catch (\Exception $e)
                            {
                                \Logger::log(get_class($this) . ': could not fetch from plugin_lucenesearch_contents_temp', \Zend_Log::DEBUG);
                            }

                            try
                            {
                                $rowNoIndex = $this->db->fetchRow('SELECT count(*) as count from plugin_lucenesearch_frontend_crawler_noindex WHERE id ="' . md5($nextLink) . '"');
                            }
                            catch (\Exception $e)
                            {
                                \Logger::log(get_class($this) . ': could not fetch from plugin_lucenesearch_frontend_crawler_noindex', \Zend_Log::DEBUG);
                            }

                            if ($rowTodo['count'] > 0 or $rowDone['count'] > 0 or $rowNoIndex['count'] > 0)
                            {
                                \Logger::log(get_class($this) . ' Redirected to uri [ $nextLink ] - which has already been processed',\ Zend_Log::DEBUG);
                            }
                            else
                            {
                                try
                                {
                                    $success = $this->parse($nextLink, $response, $client->getUri()->getHost(), $client->getCookieJar(), $depth);
                                    \Logger::log(get_class($this) . ': parsed  [ $nextLink ] ', \Zend_Log::DEBUG);
                                }
                                catch (\Exception $e)
                                {
                                    \Logger::log($e, \Zend_Log::ERR);
                                }

                            }
                        } else {
                            \Logger::log('We were redirected to an invalid Link [ $nextLink]', \Zend_Log::DEBUG);
                        }


                    } else
                    {
                        \Logger::log(get_class($this) . ': Error parsing  [ $nextLink ] ', \Zend_Log::ERR);
                    }


                } catch (\Zend_Uri_Exception $e)
                {
                    \Logger::log(get_class($this) . ': Invalid URI  [ $nextLink ] ', \Zend_Log::ERR);

                }

            } else
            {
                \Logger::alert(get_class($this) . ': Stopping with uri [ $nextLink ] because maximum link depth of [ $depth ] has been reached.');
            }

            //get next from DB
            try
            {
                $row = $this->db->fetchRow('SELECT * FROM plugin_lucenesearch_frontend_crawler_todo ORDER BY id', array());
                $nextLink = $row['uri'];
                $depth = $row['depth'];
                $cookieJar = unserialize($row['cookiejar']);
            }
            catch (\Exception $e)
            {
                //wait 2 seconds then try again
                sleep(2);

                try
                {
                    $row = $this->db->fetchRow('SELECT * FROM plugin_lucenesearch_frontend_crawler_todo ORDER BY id', array());
                    $nextLink = $row['uri'];
                    $depth = $row['depth'];
                    $cookieJar = unserialize($row['cookiejar']);
                }
                catch (\Exception $e)
                {
                    // probably table was already removed because crawler is finished
                    \Logger::log(get_class($this) . ': Could not extract next link from table plugin_lucenesearch_frontend_crawler_todo ', \Zend_Log::DEBUG);
                    $nextLink = false;
                }

            }

        }

    }


    /**
     * This function absolutizes and formats the found link
     * @param string $foundLink found link can be relative or absolute
     * @param string $protocol protocol (http or https) extracted from the uri on which the current link was found
     * @param string $host host extracted from the uri on which the current link was found
     * @param string $link previous link on which the current link was found
     * @return string
     */
    protected function cleanAndFormatLink($foundLink, $protocol, $host, $link)
    {
        //make the entire link lower case
        $foundLink = strtolower($foundLink);

        //make sure this is not an endless loop
        $testString = $foundLink . $foundLink . $foundLink;

        if (strpos(strtolower($link), $testString) !== FALSE)
        {
            \Logger::debug(get_class($this) . ': Detected insane link [ $link ], stopping here.');
            return null;
        }

        //remove all %20 from the beginning of the link - some crazy users manage to produce these weird links with wysiwyg editor
        if (strpos($foundLink, '%20') === 0)
        {
            $pattern = '@^([%20]*)(http.*)@';
            $foundLink = preg_replace($pattern, '$2', $foundLink);
        }

        //remove #,?and & from the end of the link if they are not followed by any parameters
        $lastChar = $foundLink[strlen($foundLink) - 1];
        while ($lastChar == '#' or $lastChar == '?' or $lastChar == '&')
        {
            $foundLink = substr($foundLink, 0, -1);
            $lastChar = $foundLink[strlen($foundLink) - 1];
        }

        //absolutize link
        if ($foundLink[0] == '/')
        {
            $foundLink = $protocol . '://' . strtolower($host) . $foundLink;
        }
        else if ($foundLink[0] == '?')
        {
            $paramsStart = stripos($link, '?');
            if ($paramsStart !== FALSE) {
                $linkWithoutParameters = substr($link, 0, stripos($link, '?'));
                $foundLink = $linkWithoutParameters . $foundLink;
            } else {
                $foundLink = $link . $foundLink;
            }
        }
        else if ($foundLink[0] == '&') {

            $foundLink = $link . $foundLink;
        }
        else if ($foundLink[0] == '#') {
            //$foundLink = $link . $foundLink;
            return null;
        }
        else if (strpos($foundLink, 'http://') !== 0
            and strpos($foundLink, 'https://') !== 0
                and strpos($foundLink, 'www.') !== 0
                    and strpos($foundLink, 'mailto:') !== 0
                        and strpos($foundLink, 'javascript:') !== 0
                            and strpos($foundLink, 'file://') !== 0
                                and strpos($foundLink, 'ftp://') !== 0
                                    and strpos($foundLink, 'gopher://') !== 0
                                        and strpos($foundLink, 'telnet://') !== 0
                                            and strpos($foundLink, 'news:') !== 0
        )
        {

            \Logger::debug('relative link:' . $foundLink);
            $foundLink = $link . $foundLink;
        } else if (strpos($foundLink, 'https://') === 0 or strpos($foundLink, 'http://') === 0) {
            //absolute link -> strtolower host
            try {
                $uri = \Zend_Uri_Http::fromString($foundLink);
                $foundLink = str_ireplace($uri->getHost(), strtolower($uri->getHost()), $foundLink);
            } catch (\Zend_Uri_Exception $e) {
            }
        }
        return $foundLink;
    }

    /**
     * @param  string $link
     * @param  \Zend_Http_Response $response
     * @param string $host
     * @param \Zend_Http_CookieJar $cookieJar
     * @param integer $depth
     * @return boolean
     */
    protected function parse($link, $response, $host, $cookieJar, $depth)
    {
        $success = false;
        if (strpos($link, 'https://') !== FALSE)
        {
            $protocol = 'https';
        }
        else if (strpos($link, 'http://') !== FALSE)
        {
            $protocol = 'http';
        }
        else
        {
            \Logger::log(get_class($this) . ' parsing [$link] not possible. Only parsing http and https ', \Zend_Log::DEBUG);
            return;
        }

        $headers = $response->getHeaders();

        if (array_key_exists('Content-Type', $headers))
        {
            $contentType = $response->getHeader('Content-Type');
        }
        else if (array_key_exists('Content-type', $headers))
        {
            $contentType = $response->getHeader('Content-Type');
        }
        else if (array_key_exists('content-type', $headers))
        {
            $contentType = $response->getHeader('Content-Type');
        }
        else if (array_key_exists('content-Type', $headers))
        {
            $contentType = $response->getHeader('Content-Type');
        }

        if (!empty($contentType))
        {
            $parts = explode(';', $contentType);
            $mimeType = trim($parts[0]);

            if ($mimeType == 'text/html')
            {
                $success = $this->parseHtml($link, $response, $host, $protocol, $cookieJar, $depth);
            }
            else if ($mimeType == 'application/pdf')
            {
                $success = $this->parsePdf($link, $response);
            }
            else
            {
                \Logger::log(get_class($this) . ' Cannot parse mime type [ $mimeType ] provided by link [ $link ] ' . \Zend_Log::ERR);
            }
        }
        else
        {
            \Logger::log(get_class($this) . ' Could not determine content type of [ $link ] ' . \Zend_Log::ERR);
        }

        return $success;

    }

    /**
     * @param  string $html
     * @return string
     */
    protected function checkForCanonical($html)
    {

        include_once 'simple_html_dom.php';

        if ($source = str_get_html($html))
        {
            $headElements = $source->find('head link');

            foreach ($headElements as $element)
            {
                if ($element->hasAttribute('rel') and strtolower($element->getAttribute('rel')) == 'canonical')
                {
                    return $element->getAttribute('href');
                }
            }
        }

        return null;

    }

    /**
     * @param  string $link
     * @param  \Zend_Http_Response $response
     * @param string $host
     * @param string $protocol
     * @param Zend_Http_CookieJar
     * @param integer $depth
     * @return boolean
     */
    protected function parseHtml($link, $response, $host, $protocol, $cookieJar, $depth)
    {
        $html = $response->getBody();

        $canonicalLink = $this->checkForCanonical($html);
        if ($canonicalLink and $canonicalLink!=$link)
        {
            $this->processFoundLink($canonicalLink, $protocol, $host, $link, $depth, $cookieJar);
            \Logger::debug(get_class($this) . ': Stopping to parse html at [ $link ], processing canonical link [ $canonicalLink ] instead');
            return true;
        }

        //TODO: robots.txt
        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(true);
        $doc = \Zend_Search_Lucene_Document_Html::loadHTML($html, false, 'utf-8');
        $links = $doc->getLinks();

        $robotsMeta = $this->getRobotsMetaInfo($html);
        if (in_array('nofollow', $robotsMeta))
        {
            //no links to follow
            $links = array();
            \Logger::debug(get_class($this) . ': not following links on [ $link ] because it has robots nofollow');
        }

        if (!in_array('noindex', $robotsMeta))
        {

            //now limit to search content area if indicators are set and found in this document
            if (!empty($this->searchStartIndicator))
            {
                $documentHasDelimiter = strpos($html, $this->searchStartIndicator) !== FALSE;
            }

            if ($documentHasDelimiter and !empty($this->searchStartIndicator) and !empty($this->searchEndIndicator))
            {
                //get part before html head starts
                $top = explode('<head>', $html);

                //get html head
                $htmlHead = array();
                preg_match_all('@(<head[^>]*?>.*?</head>)@si', $html, $htmlHead);
                $head = $top[0] . '<head></head>';
                if (is_array($htmlHead[0])) {
                    $head = $top[0] . $htmlHead[0][0];
                }

                //get snippets within allowed content areas
                $htmlSnippets = array();
                $minified = str_replace(array('\r\n', '\r', '\n'), '', $html);
                $minified = preg_replace('@[ \t\n\r\f]+@', ' ', $minified);

                preg_match_all('%' . $this->searchStartIndicator . '(.*?)' . $this->searchEndIndicator . '%si', $minified, $htmlSnippets);

                $html = $head;
                if (is_array($htmlSnippets[0])) {
                    foreach ($htmlSnippets[0] as $snippet) {
                        $html .= ' ' . $snippet;
                    }
                }
                //close html tag
                $html .= '</html>';
            }

            $this->addHtmlToIndex($html, $link, $this->getLanguageFromResponse($response), $this->getEncodingFromResponse($response), $host);
            \Logger::info(get_class($this) . ': Added to indexer stack [ $link ]');
        }
        else
        {
            $this->addNoIndexPage($link);
            \Logger::debug(get_class($this) . ': not indexing [ $link ] because it has robots noindex');
        }

        if (count($links) > 0)
        {
            foreach ($links as $foundLink) {
                $this->processFoundLink($foundLink, $protocol, $host, $link, $depth, $cookieJar);
            }
        } else {
            \Logger::debug(get_class($this) . ': No links found on page at [ $link ] ');
        }

        //TODO: for now we always return true - as success ... are there any unsuccessful states?
        return true;

    }

    /**
     * @param  stirng $foundLink
     * @param  string $protocol
     * @param  string $host
     * @param  string $link
     * @param  integer $depth
     * @param  \Zend_Http_CookieJar $cookieJar
     * @return void
     */
    protected function processFoundLink($foundLink, $protocol, $host, $link, $depth, $cookieJar)
    {
        $foundLink = $this->cleanAndFormatLink($foundLink, $protocol, $host, $link);

        if ($foundLink)
        {
            $valid = $this->validateLink($foundLink);

            if ($valid and $foundLink != $link and strlen($foundLink) > 0)
            {
                $rowDone = $this->db->fetchRow('SELECT count(*) as count from plugin_lucenesearch_contents_temp WHERE id = "' . md5($foundLink) . '"');
                $rowNoIndex = $this->db->fetchRow('SELECT count(*) as count from plugin_lucenesearch_frontend_crawler_noindex WHERE id ="' . md5($foundLink) . '"');

                if ($rowDone['count'] == 0 and $rowNoIndex['count'] == 0)
                {
                    try
                    {
                        if ($this->db->insert('plugin_lucenesearch_frontend_crawler_todo', array('id' => md5($foundLink), 'uri' => $foundLink, 'depth' => ($depth + 1), 'cookiejar' => serialize($cookieJar))))
                        {
                            \Logger::log(get_class($this) . ': Added link [ $foundLink ] to fetch list', \Zend_Log::DEBUG);
                        }

                    } catch (\Exception $e) {

                    }
                }
            }
        }

    }


    /**
     * @param  string $foundLink
     * @return bool
     */
    protected function validateLink($foundLink)
    {
        $valid = false;

        foreach ($this->validLinkRegexes as $regex)
        {
            if (preg_match($regex, $foundLink))
            {
                //check if not explicity excluded
                if (count($this->invalidLinkRegexes) > 0)
                {
                    $invalid = false;

                    foreach ($this->invalidLinkRegexes as $invalidRegex)
                    {
                        if (preg_match($invalidRegex, $foundLink))
                        {
                            $invalid = true;
                            break;
                        }
                    }
                }

                $valid = $invalid ? false : true;
                break;
            }
        }

        return $valid;

    }


    /**
     * parsing pdf is an endpoint for the crawler, no further links are extracted, it just indices the pdf content
     * @param  string $link
     * @param  \Zend_Http_Response $response
     * @return void
     */
    protected function parsePdf($link, $response)
    {
        $this->addPdfToIndex($link, $this->getLanguageFromResponse($response));
        \Logger::log(get_class($this) . ': Added pdf to index [ $link ]', \Zend_Log::INFO);
    }


    /**
     * extract encoding either from HTTP Header or from HTML Attribute
     * @param  Zend_Http_Response $response
     * @return string
     */
    protected function getEncodingFromResponse($response)
    {
        //try content-type header
        $contentType = $response->getHeader("Content-Type");
        if (!empty($contentType)) {
            $data = array();
            preg_match('@.*?;\s*charset=(.*)\s*@si', $contentType, $data);
            if ($data[1]) {
                $encoding = trim($data[1]);
                //logger::log("encoding " . $contentType);
                //logger::log(get_class($this) . ":found encoding [$encoding] in HTTP header Content-Type", Zend_Log::DEBUG);
            }
        }
        if (empty($encoding)) {
            //try html
            $data = array();
            preg_match('@<meta\shttp-equiv="Content-Type"\scontent=".*?;\s+charset=(.*?)"\s\/>@si', $response->getBody(), $data);
            if ($data[1]) {
                $encoding = trim($data[1]);
                //logger::log("encoding " . $data[0]);
                //logger::log(get_class($this) . ":found encoding [$encoding] in HTML", Zend_Log::DEBUG);
            }
        }
        if (empty($encoding)) {
            //try xhtml
            $data = array();
            preg_match('@<\?xml.*?encoding="(.*?)"\s*\?>@si', $response->getBody(), $data);
            if ($data[1]) {
                $encoding = trim($data[1]);
                //logger::log(get_class($this) . ":found encoding [$encoding] in XHTML",Zend_Log::DEBUG);
            }
        }
        if (empty($encoding)) {
            //try html 5
            $data = array();
            preg_match('@<meta\scharset="(.*?)"\s*>@si', $response->getBody(), $data);
            if ($data[1]) {
                $encoding = trim($data[1]);
                //logger::log(get_class($this) . ":found encoding [$encoding] in HTML5",Zend_Log::DEBUG);
            }
        }
        return $encoding;
    }

    /**
     * @param  string $html
     * @return string[]
     */
    protected function getRobotsMetaInfo($html)
    {
        //use pimcore_searchphp direction first, robots as fallback
        preg_match_all('/<[\s]*meta[\s]*name="pimcore_searchphp"?[\s]*content="?([^>"]*)"?[\s]*[\/]?[\s]*>/si', $html, $tags1);
        preg_match_all('/<[\s]*meta[\s]*name="robots"?[\s]*content="?([^>"]*)"?[\s]*[\/]?[\s]*>/si', $html, $tags2);
        $tags = implode(",", array_merge($tags1[1], $tags2[1]));
        $tokens = array();

        if ($tags)
        {
            $tokens = explode(",", $tags);
            if (is_array($tokens))
            {
                $cleanedTokens = array();
                foreach ($tokens as $token)
                {
                    $t = trim($token);
                    $t = strtolower($t);
                    $cleanedTokens[] = $t;
                }
                $tokens = $cleanedTokens;
            }
            else if (!empty($tokens))
            {
                $tokens = array(trim(strtolower($tokens)));
            }
        }
        return $tokens;
    }

    /**
     * Try to find the document's language by first looking for Content-Language in Http headers than in html
     * attribute and last in content-language meta tag
     * @param  \Zend_Http_Response $response
     * @return string
     */
    protected function getLanguageFromResponse($response)
    {
        $l = $response->getHeader('Content-Language');

        if (empty($l))
        {
            //try html lang attribute
            $languages = array();
            preg_match_all('@<html[\n|\r\n]*.*?[\n|\r\n]*lang="(?P<language>\S+)"[\n|\r\n]*.*?[\n|\r\n]*>@si', $response->getBody(), $languages);
            if ($languages['language'])
            {
                $l = str_replace(array('_', '-'), '', $languages['language'][0]);

            }
        }
        if (empty($l))
        {
            //try meta tag
            $languages = array();
            preg_match_all('@<meta\shttp-equiv="content-language"\scontent="(?P<language>\S+)"\s\/>@si', $response->getBody(), $languages);
            if ($languages['language'])
            {
                //for lucene index remove '_' - this causes tokenization
                $l = str_replace('_', '', $languages['language'][0]);

            }
        }

        return $l;
    }


    protected function addNoIndexPage($url)
    {
        try
        {
            $this->db->insert('plugin_lucenesearch_frontend_crawler_noindex', array('id' => md5($url), 'uri' => $url));
            \Logger::log('Plugin_LuceneSearch: Adding [ $url ] to noindex pages', \Zend_Log::DEBUG);

        }
        catch (\Exception $e)
        {

        }


    }

    /**
     * adds a HTML page to lucene index and mysql table for search result sumaries
     * @param  string $html
     * @param  string $url
     * @param  string $language
     * @return void
     */
    protected function addHtmlToIndex($html, $url, $language, $encoding, $host)
    {
        //$this->checkAndPrepareIndex();

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
            $serialized = serialize($doc);
            $this->db->insert('plugin_lucenesearch_indexer_todo', array('content' => $serialized));

            //$this->index->addDocument($doc);
        }
        catch (\Exception $e)
        {
            \Logger::log($e->getMessage(), \Zend_Log::ERR);
        }


    }

    /**
     * adds a PDF page to lucene index and mysql table for search result sumaries
     * @param  string $url
     * @param  string $language
     * @return void
     */
    protected function addPdfToIndex($url, $language)
    {
        //$this->checkAndPrepareIndex();

        //TODO: PDF2Text does not seem to work

        /*
        $this->checkAndPrepareIndex();

        $pdf2Text = new PDF2Text();
        $pdf2Text->setFilename($url);
        $pdf2Text->setUnicode(true);
        $pdf2Text->decodePDF();
        $text = $pdf2Text->output();
        echo $text;
        try {
            $this->db->insert('plugin_lucenesearch_contents_temp', array(
                'id' => md5($url),
                'uri' => $url,
                'content' => $text  ,
                'host' =>
            ));

            $doc = new Zend_Search_Lucene_Document();

            //TODO use propper encoding!
            $doc->addField(Zend_Search_Lucene_Field::Text('body',$text,'utf-8'));
            $doc->addField(Zend_Search_Lucene_Field::Keyword('lang', $language));
            $doc->addField(Zend_Search_Lucene_Field::Keyword('url', $url));
            $this->index->addDocument($doc);
        } catch (Exception $e) {
            logger::log($e->getMessage());
        }
        */
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
                \Logger::log(get_class($this) . ': could not open frontend index, creating new one.', \Zend_Log::WARN);
                \Zend_Search_Lucene::create($indexDir);
                $this->index = \Zend_Search_Lucene::open($indexDir);
            }
        }
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

    /**
     * @param  $link
     * @return string
     */
    protected function removeOutputFilterParameters($link)
    {
        $link = str_replace('?pimcore_outputfilters_disabled=1&', '?', $link);
        $link = str_replace('?pimcore_outputfilters_disabled=1', '', $link);
        $link = str_replace('&pimcore_outputfilters_disabled=1', '', $link);
        return $link;
    }

    /**
     * @param  string $link
     * @return string
     */
    protected function addEvictOutputFilterParameter($link)
    {
        if (strpos($link, 'pimcore_outputfilters_disabled=1') === FALSE) {
            $paramConcat = '?';

            if (strpos($link, '?') !== FALSE)
            {
                $paramConcat = '&';
            }

            if (strpos($link, '#')!==FALSE)
            {
                //insert before anchor
                $pos = strpos($link, '#');
                $first = substr($link, 0, $pos);
                $second = substr($link, $pos);

                return $first . $paramConcat . 'pimcore_outputfilters_disabled=1' . $second;

            } else
            {
                return $link . $paramConcat . 'pimcore_outputfilters_disabled=1';
            }

        } else
        {
            return $link;
        }
    }

}
