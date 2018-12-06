<?php

namespace LuceneSearchBundle\Task\Parser;

use LuceneSearchBundle\Event\AssetResourceRestrictionEvent;
use LuceneSearchBundle\Event\HtmlParserEvent;
use LuceneSearchBundle\Event\PdfParserEvent;
use LuceneSearchBundle\LuceneSearchEvents;
use LuceneSearchBundle\Task\AbstractTask;
use LuceneSearchBundle\Configuration\Configuration;
use Pimcore\Document\Adapter\Ghostscript;
use Pimcore\Model\Asset;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use VDB\Spider\Resource;

class ParserTask extends AbstractTask
{
    /**
     * @var string
     */
    protected $prefix = 'task.parser';

    /**
     * @var \Zend_Search_Lucene
     */
    protected $index;

    /**
     * @var string
     */
    protected $assetTmpDir;

    /**
     * @var int
     */
    protected $documentBoost = 1;

    /**
     * @var int
     */
    protected $assetBoost = 1;

    /**
     * indicates where the content relevant for search starts
     *
     * @var string
     */
    protected $searchStartIndicator;

    /**
     * indicates where the content relevant for search ends
     *
     * @var string
     */
    protected $searchEndIndicator;

    /**
     * indicates where the content irrelevant for search starts
     *
     * @var string
     */
    protected $searchExcludeStartIndicator;

    /**
     * indicates where the content irrelevant for search ends
     *
     * @var string
     */
    protected $searchExcludeEndIndicator;

    /**
     * search for restriction tags and assets
     *
     * @var bool
     */
    protected $checkRestrictions = false;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventListener(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $crawlerConfig = $this->configuration->getConfig('crawler');
        $boostConfig = $this->configuration->getConfig('boost');
        $restrictionConfig = $this->configuration->getConfig('restriction');

        $this->documentBoost = $boostConfig['documents'];
        $this->assetBoost = $boostConfig['assets'];

        $this->searchStartIndicator = $crawlerConfig['content_start_indicator'];
        $this->searchEndIndicator = $crawlerConfig['content_end_indicator'];
        $this->searchExcludeStartIndicator = $crawlerConfig['content_exclude_start_indicator'];
        $this->searchExcludeEndIndicator = $crawlerConfig['content_exclude_end_indicator'];

        $this->checkRestrictions = $restrictionConfig['enabled'];
        $this->assetTmpDir = Configuration::CRAWLER_TMP_ASSET_DIR_PATH;

        return true;
    }

    /**
     * @param mixed $crawlData
     *
     * @return bool
     */
    public function process($crawlData)
    {
        $this->logger->setPrefix($this->prefix);

        $this->addSignalListener();
        $this->checkAndPrepareIndex();

        foreach ($crawlData as $resource) {
            if ($resource instanceof Resource) {
                $this->parseResponse($resource);
            } else {
                $this->log(sprintf('crawler resource not a instance of \VDB\Spider\Resource. Given type: %s', gettype($resource)), 'notice');
            }
        }

        $this->optimizeIndex();

        return true;
    }

    /**
     * @param Resource $resource
     */
    public function parseResponse($resource)
    {
        $host = $resource->getUri()->getHost();
        $uri = $resource->getUri()->toString();

        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');
        $statusCode = $resource->getResponse()->getStatusCode();

        if ($statusCode !== 200) {
            $this->log(sprintf('skip indexing [ %s ] because of wrong status code [ %s ]', $uri, $statusCode), 'debug');
            return;
        }

        if (!empty($contentTypeInfo)) {
            $parts = explode(';', $contentTypeInfo);
            $mimeType = trim($parts[0]);

            if ($mimeType === 'text/html') {
                $this->parseHtml($resource, $host);
            } elseif ($mimeType === 'application/pdf') {
                $this->parsePdf($resource, $host);
            } else {
                $this->log(sprintf('cannot parse mime type [ %s ] provided by uri [ %s ]', $mimeType, $uri), 'debug');
            }
        } else {
            $this->log(sprintf('could not determine content type of [ %s ]', $uri), 'debug');
        }
    }

    /**
     * @param Resource $resource
     * @param string   $host
     *
     * @return bool
     */
    private function parseHtml($resource, $host)
    {
        /** @var \Symfony\Component\DomCrawler\Crawler $crawler */
        $crawler = $resource->getCrawler();
        $uri = $resource->getUri()->toString();
        $stream = $resource->getResponse()->getBody();
        $stream->rewind();
        $html = $stream->getContents();
        $originalHtml = $html;

        $contentTypeInfo = $resource->getResponse()->getHeaderLine('Content-Type');
        $contentLanguage = $resource->getResponse()->getHeaderLine('Content-Language');

        $language = strtolower($this->getLanguageFromResponse($contentLanguage, $html));
        $encoding = strtolower($this->getEncodingFromResponse($contentTypeInfo, $html));

        $language = strtolower(str_replace('_', '-', $language));

        //page has canonical link: do not track if this is not the canonical document
        $hasCanonicalLink = $crawler->filterXpath('//link[@rel="canonical"]')->count() > 0;

        if ($hasCanonicalLink === true) {
            if ($uri != $crawler->filterXpath('//link[@rel="canonical"]')->attr('href')) {
                $this->log(sprintf(
                        'skip indexing [ %s ] because it has canonical link %s',
                        $uri,
                        $crawler->filterXpath('//link[@rel="canonical"]')->attr('href')
                    )
                );
                return false;
            }
        }

        //page has no follow: do not track!
        $hasNoIndex = $crawler->filterXpath('//meta[contains(@content, "noindex")]')->count() > 0;

        if ($hasNoIndex === true) {
            $this->log(sprintf('skip indexing [ %s ] because it has a noindex tag', $uri));
            return false;
        }

        \Zend_Search_Lucene_Document_Html::setExcludeNoFollowLinks(true);

        $hasCountryMeta = $crawler->filterXpath('//meta[@name="country"]')->count() > 0;
        $hasTitle = $crawler->filterXpath('//title')->count() > 0;
        $hasDescription = $crawler->filterXpath('//meta[@name="description"]')->count() > 0;
        $hasRestriction = $this->checkRestrictions === true && $crawler->filterXpath('//meta[@name="m:groups"]')->count() > 0;
        $hasCategories = $crawler->filterXpath('//meta[@name="lucene-search:categories"]')->count() > 0;
        $hasCustomMeta = $crawler->filterXpath('//meta[@name="lucene-search:meta"]')->count() > 0;
        $hasCustomBoostMeta = $crawler->filterXpath('//meta[@name="lucene-search:boost"]')->count() > 0;
        $hasDocumentMeta = $crawler->filterXpath('//meta[@name="lucene-search:documentId"]')->count() > 0;
        $hasObjectMeta = $crawler->filterXpath('//meta[@name="lucene-search:objectId"]')->count() > 0;

        $title = null;
        $description = null;
        $customMeta = null;
        $restrictions = false;
        $categories = null;
        $country = null;
        $customBoost = 1;
        $documentId = null;
        $objectId = null;

        if ($hasTitle === true) {
            $title = $crawler->filterXpath('//title')->text();
        }

        if ($hasDescription === true) {
            $description = $crawler->filterXpath('//meta[@name="description"]')->attr('content');
        }

        if ($hasCountryMeta === true) {
            $country = $crawler->filterXpath('//meta[@name="country"]')->attr('content');
        }

        if ($hasRestriction === true) {
            $restrictions = $crawler->filterXpath('//meta[@name="m:groups"]')->attr('content');
        }

        if ($hasCustomMeta === true) {
            $customMeta = $crawler->filterXpath('//meta[@name="lucene-search:meta"]')->attr('content');
        }

        if ($hasCategories === true) {
            $categories = $crawler->filterXpath('//meta[@name="lucene-search:categories"]')->attr('content');
        }

        if ($hasCustomBoostMeta === true) {
            $customBoost = (int)$crawler->filterXpath('//meta[@name="lucene-search:boost"]')->attr('content');
        }

        if ($hasDocumentMeta === true) {
            $documentId = (int)$crawler->filterXpath('//meta[@name="lucene-search:documentId"]')->attr('content');
        }

        if ($hasObjectMeta === true) {
            $objectId = (int)$crawler->filterXpath('//meta[@name="lucene-search:objectId"]')->attr('content');
        }

        $documentHasDelimiter = false;
        $documentHasExcludeDelimiter = false;

        //now limit to search content area if indicators are set and found in this document
        if (!empty($this->searchStartIndicator)) {
            $documentHasDelimiter = strpos($html, $this->searchStartIndicator) !== false;
        }

        //remove content between exclude indicators
        if (!empty($this->searchExcludeStartIndicator)) {
            $documentHasExcludeDelimiter = strpos($html, $this->searchExcludeStartIndicator) !== false;
        }

        if ($documentHasDelimiter
            && !empty($this->searchStartIndicator)
            && !empty($this->searchEndIndicator)) {
            preg_match_all(
                '%' . $this->searchStartIndicator . '(.*?)' . $this->searchEndIndicator . '%si',
                $html,
                $htmlSnippets
            );

            $html = '';

            if (is_array($htmlSnippets[1])) {
                foreach ($htmlSnippets[1] as $snippet) {
                    if ($documentHasExcludeDelimiter
                        && !empty($this->searchExcludeStartIndicator)
                        && !empty($this->searchExcludeEndIndicator)) {
                        $snippet = preg_replace(
                            '#(' . preg_quote($this->searchExcludeStartIndicator) . ')(.*?)(' . preg_quote($this->searchExcludeEndIndicator) . ')#si',
                            ' ',
                            $snippet
                        );
                    }

                    $html .= ' ' . $snippet;
                }
            }
        }

        $params = [
            'title'        => $title,
            'description'  => $description,
            'uri'          => $uri,
            'language'     => $language,
            'country'      => $country,
            'restrictions' => $restrictions,
            'categories'   => $categories,
            'custom_meta'  => $customMeta,
            'encoding'     => $encoding,
            'host'         => $host,
            'custom_boost' => $customBoost,
            'document_id'  => $documentId,
            'object_id'    => $objectId
        ];

        $this->addHtmlToIndex($html, $originalHtml, $params);

        $this->log(sprintf('added html to indexer stack: %s', $uri));

        return true;
    }

    /**
     * @param Resource $resource
     * @param string   $host
     *
     * @return bool
     */
    private function parsePdf($resource, $host)
    {
        $this->log(sprintf('added pdf to indexer stack: %s', $resource->getUri()->toString()));

        $params = [
            'host'         => $host,
            'custom_boost' => false
        ];

        return $this->addPdfToIndex($resource, $params);
    }

    /**
     * adds a PDF page to lucene index and mysql table for search result summaries
     *
     * @param Resource $resource
     * @param array    $params
     *
     * @return bool
     */
    protected function addPdfToIndex($resource, $params)
    {
        $defaults = [
            'host'         => null,
            'custom_boost' => false
        ];

        $params = array_merge($defaults, $params);

        try {
            $pdfToTextBin = Ghostscript::getPdftotextCli();
        } catch (\Exception $e) {
            $pdfToTextBin = false;
        }

        if ($pdfToTextBin === false) {
            return false;
        }

        $textFileTmp = uniqid('t2p-');

        $tmpFile = $this->assetTmpDir . DIRECTORY_SEPARATOR . $textFileTmp . '.txt';
        $tmpPdfFile = $this->assetTmpDir . DIRECTORY_SEPARATOR . $textFileTmp . '.pdf';

        $stream = $resource->getResponse()->getBody();
        $stream->rewind();
        $contents = $stream->getContents();

        file_put_contents($tmpPdfFile, $contents);

        $verboseCommand = \Pimcore::inDebugMode() ? '' : '-q ';

        try {
            $cmd = $verboseCommand . $tmpPdfFile . ' ' . $tmpFile;
            exec($pdfToTextBin . ' ' . $cmd);
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }

        $uri = $resource->getUri()->toString();
        $assetMeta = $this->getAssetMeta($resource);

        if (is_file($tmpFile)) {
            $fileContent = file_get_contents($tmpFile);

            try {

                $doc = new \Zend_Search_Lucene_Document();
                $doc->boost = $params['custom_boost'] ? $params['custom_boost'] : $this->assetBoost;

                $text = preg_replace("/\r|\n/", ' ', $fileContent);
                $text = preg_replace('/[^\p{Latin}\d ]/u', '', $text);
                $text = preg_replace('/\n[\s]*/', "\n", $text); // remove all leading blanks

                $title = $assetMeta['key'] !== false ? $assetMeta['key'] : basename($uri);
                $doc->addField(\Zend_Search_Lucene_Field::text('title', $title, 'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::text('content', $text, 'utf-8'));
                $doc->addField(\Zend_Search_Lucene_Field::keyword('lang', $assetMeta['language']));
                $doc->addField(\Zend_Search_Lucene_Field::keyword('country', $assetMeta['country']));

                $doc->addField(\Zend_Search_Lucene_Field::keyword('url', $uri));
                $doc->addField(\Zend_Search_Lucene_Field::keyword('host', $params['host']));

                if ($assetMeta['restrictions'] === false) {
                    $doc->addField(\Zend_Search_Lucene_Field::keyword('restrictionGroup_default', true));
                } else {
                    if (is_array($assetMeta['restrictions'])) {
                        foreach ($assetMeta['restrictions'] as $restrictionGroup) {
                            $doc->addField(\Zend_Search_Lucene_Field::keyword('restrictionGroup_' . $restrictionGroup, true));
                        }
                    }
                }

                // add internal availability flag
                $doc->addField(\Zend_Search_Lucene_Field::keyword('internalAvailability', 'available'));

                $parserEvent = new PdfParserEvent($doc, $fileContent, $assetMeta, $params);
                $this->eventDispatcher->dispatch(
                    LuceneSearchEvents::LUCENE_SEARCH_PARSER_PDF_DOCUMENT,
                    $parserEvent
                );

                $doc = $parserEvent->getDocument();

                $this->addDocumentToIndex($doc);
            } catch (\Exception $e) {
                $this->log($e->getMessage());
            }

            @unlink($tmpFile);
            @unlink($tmpPdfFile);
        }

        return true;
    }

    /**
     * adds a HTML page to lucene index and mysql table for search result summaries
     *
     * @param  string $html
     * @param  array  $params
     *
     * @return void
     */
    protected function addHtmlToIndex($html, $originalHtml, $params)
    {
        $defaults = [
            'title'        => null,
            'description'  => null,
            'uri'          => null,
            'language'     => null,
            'country'      => null,
            'categories'   => null,
            'custom_meta'  => null,
            'encoding'     => null,
            'host'         => null,
            'restrictions' => false,
            'custom_boost' => false
        ];

        $params = array_merge($defaults, $params);

        try {
            $content = $this->getPlainTextFromHtml($html);

            $doc = new \Zend_Search_Lucene_Document();
            $doc->boost = $params['custom_boost'] ? $params['custom_boost'] : $this->documentBoost;

            //add h1 to index
            $headlines = [];
            preg_match_all('@(<h1[^>]*?>[ \t\n\r\f]*(.*?)[ \t\n\r\f]*' . '</h1>)@si', $html, $headlines);

            if (is_array($headlines[2])) {
                $h1 = '';
                foreach ($headlines[2] as $headline) {
                    $h1 .= $headline . ' ';
                }

                $h1 = strip_tags($h1);
                $field = \Zend_Search_Lucene_Field::text('h1', $h1, $params['encoding']);
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

            $doc->addField(\Zend_Search_Lucene_Field::keyword('charset', $params['encoding']));
            $doc->addField(\Zend_Search_Lucene_Field::keyword('lang', $params['language']));
            $doc->addField(\Zend_Search_Lucene_Field::keyword('url', $params['uri']));
            $doc->addField(\Zend_Search_Lucene_Field::keyword('host', $params['host']));

            $doc->addField(\Zend_Search_Lucene_Field::text('title', $params['title'], $params['encoding']));
            $doc->addField(\Zend_Search_Lucene_Field::text('description', $params['description'], $params['encoding']));
            $doc->addField(\Zend_Search_Lucene_Field::text('customMeta', strip_tags($params['custom_meta']), $params['encoding']));

            $doc->addField(\Zend_Search_Lucene_Field::text('content', $content, $params['encoding']));
            $doc->addField(\Zend_Search_Lucene_Field::text('imageTags', join(',', $tags)));

            if ($params['country'] !== null) {
                $doc->addField(\Zend_Search_Lucene_Field::keyword('country', $params['country']));
            }

            if ($params['restrictions'] === false) {
                $doc->addField(\Zend_Search_Lucene_Field::keyword('restrictionGroup_default', true));
            } else {
                $restrictionGroups = explode(',', $params['restrictions']);
                foreach ($restrictionGroups as $restrictionGroup) {
                    $doc->addField(\Zend_Search_Lucene_Field::keyword('restrictionGroup_' . $restrictionGroup, true));
                }
            }

            if ($params['categories'] !== false) {

                $validCategories = $this->configuration->getCategories();

                if (!empty($validCategories)) {
                    $validIds = [];
                    $categoryIds = array_map('intval', explode(',', $params['categories']));
                    foreach ($categoryIds as $categoryId) {
                        $key = array_search($categoryId, array_column($validCategories, 'id'));
                        if ($key !== false) {
                            $validIds[] = $categoryId;
                            $doc->addField(\Zend_Search_Lucene_Field::keyword('category_' . $categoryId, true));
                        }
                    }

                    if (!empty($validIds)) {
                        $doc->addField(\Zend_Search_Lucene_Field::text('categories', implode(',', $validIds)));
                    }
                }
            }

            // add internal availability flag
            $doc->addField(\Zend_Search_Lucene_Field::keyword('internalAvailability', 'available'));

            $parserEvent = new HtmlParserEvent($doc, $html, $originalHtml, $params);
            $this->eventDispatcher->dispatch(
                LuceneSearchEvents::LUCENE_SEARCH_PARSER_HTML_DOCUMENT,
                $parserEvent
            );

            $doc = $parserEvent->getDocument();

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
        } else {
            $this->log('could not parse lucene document', 'error');
        }
    }

    /**
     * @param Resource $resource
     *
     * @return array
     */
    protected function getAssetMeta($resource)
    {
        $link = $resource->getUri()->toString();

        $assetMetaData = [
            'language'     => 'all',
            'country'      => 'all',
            'key'          => false,
            'restrictions' => false,
            'id'           => false
        ];

        if (empty($link) || !is_string($link)) {
            return $assetMetaData;
        }

        $restrictions = false;
        $pathFragments = parse_url($link);
        $assetPath = $pathFragments['path'];

        if ($this->checkRestrictions === true) {

            $event = new AssetResourceRestrictionEvent($resource);
            $this->eventDispatcher->dispatch(
                LuceneSearchEvents::LUCENE_SEARCH_PARSER_ASSET_RESTRICTION,
                $event
            );

            if ($event->getAsset() instanceof Asset) {
                $asset = $event->getAsset();
                $restrictions = $event->getRestrictions();
            } else {
                $asset = Asset::getByPath($assetPath);
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
        $assetMetaData['id'] = $asset->getId();

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
        $doc = \Zend_Search_Lucene_Document_Html::loadHTML($html, false, 'utf-8');
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
        libxml_use_internal_errors(true);

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

    protected function checkAndPrepareIndex()
    {
        if (!$this->index) {
            $indexDir = Configuration::INDEX_DIR_PATH_GENESIS;

            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());

            try {
                $index = \Zend_Search_Lucene::open($indexDir);
            } catch (\Zend_Search_Lucene_Exception $e) {
                $index = \Zend_Search_Lucene::create($indexDir);
            }

            $this->index = $index;

            if (!$this->index instanceof \Zend_Search_Lucene_Proxy) {
                $this->log(sprintf('could not create/open index at %s', $indexDir), 'error', true);
                $this->handlerDispatcher->getStateHandler()->stopCrawler(true);
                exit;
            }
        }
    }

    protected function optimizeIndex()
    {
        // optimize lucene index for better performance
        $this->index->optimize();

        //clean up
        if (is_object($this->index) && $this->index instanceof \Zend_Search_Lucene_Proxy) {
            $this->index->removeReference();
            unset($this->index);
            $this->log('closed frontend index references', 'debug', false);
        }

        $this->log('optimize lucene index', 'debug', false);
    }
}