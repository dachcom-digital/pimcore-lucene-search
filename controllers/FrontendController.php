<?php

use Pimcore\Controller\Action;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Plugin;
use LuceneSearch\Model\Searcher;

class LuceneSearch_FrontendController extends Action
{
    /**
     * @var
     */
    protected $frontendIndex;

    /**
     * @var
     */
    protected $categories = [];

    /**
     * query, incoming argument
     * @var String
     */
    protected $query = '';

    /**
     * query, incoming argument, unmodified
     * @var String
     */
    protected $untouchedQuery = '';

    /**
     * category, to restrict query, incoming argument
     * @var array
     */
    protected $searchCategories = [];

    /**
     * @var
     */
    protected $searchLanguage = NULL;

    /**
     * @var
     */
    protected $searchCountry = NULL;

    /**
     * @var
     */
    protected $searchRestriction = FALSE;

    /**
     * @var bool
     */
    protected $ownHostOnly = FALSE;

    /**
     * @var bool
     */
    protected $fuzzySearch = FALSE;

    /**
     * @var int
     */
    protected $maxSuggestions = 10;

    /**
     * @var int
     */
    protected $perPage = 10;

    /**
     * @var int
     */
    protected $currentPage = 1;

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();

        if (!Configuration::get('frontend.enabled')) {
            return FALSE;
        }

        try {
            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());

            $this->frontendIndex = \Zend_Search_Lucene::open(Plugin::getFrontendSearchIndex());

            //set search term query
            $searchQuery = $this->cleanRequestString($this->getParam('q'));

            if (!empty($searchQuery)) {
                $this->query = Plugin::cleanTerm($searchQuery);
                $this->untouchedQuery = $this->query;
            }

            //set Language
            if (Configuration::get('frontend.ignoreLanguage') !== TRUE) {
                $this->searchLanguage = $this->getParam('language');

                if (empty($this->searchLanguage)) {
                    try {
                        $this->searchLanguage = \Zend_Registry::get('Zend_Locale');
                    } catch (Exception $e) {
                        $this->searchLanguage = 'en';
                    }
                }
            } else {
                $this->searchLanguage = NULL;
            }

            //Set Category
            $this->categories = Configuration::get('frontend.categories');
            $searchCategories = $this->cleanRequestString($this->getParam('categories'));

            if (!empty($searchCategories)) {
                if (is_string($searchCategories)) {
                    $searchCategories = explode(',', $searchCategories);
                }
                $this->searchCategories = $searchCategories;
            }

            //Set Country
            if (Configuration::get('frontend.ignoreCountry') !== TRUE) {
                $this->searchCountry = $this->getParam('country');

                if ($this->searchCountry == 'global') {
                    $this->searchCountry = 'international';
                } else if (empty($this->searchCountry)) {
                    $this->searchCountry = 'international';
                }
            } else {
                $this->searchCountry = NULL;
            }

            //Set Restrictions (Auth)
            if (Configuration::get('frontend.ignoreRestriction') === FALSE) {
                $this->searchRestriction = TRUE;
            }

            //Set Fuzzy Search (Auth)
            $fuzzySearchRequest = $this->getParam('fuzzy');
            if (Configuration::get('frontend.fuzzySearch') == TRUE || (!empty($fuzzySearchRequest)) && $fuzzySearchRequest !== 'false') {
                $this->fuzzySearch = TRUE;
            }

            //Set own Host Only
            if (Configuration::get('frontend.ownHostOnly') == TRUE) {
                $this->ownHostOnly = TRUE;
            }

            //Set Entries per Page
            $this->perPage = Configuration::get('frontend.view.maxPerPage');
            $perPage = $this->getParam('perPage');
            if (!empty($perPage)) {
                $this->perPage = (int)$perPage;
            }

            //Set max Suggestions
            $this->maxSuggestions = Configuration::get('frontend.view.maxSuggestions');

            //Set Current Page
            $currentPage = $this->getParam('page');
            if (!empty($currentPage)) {
                $this->currentPage = (int)$currentPage;
            }
        } catch (\Exception $e) {
            throw new \Exception('could not open index');
        }
    }

    public function sitemapAction()
    {
        $this->removeViewRenderer();

        if (Configuration::get('frontend.sitemap.render') === FALSE) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        $sitemapFile = $this->_getParam('sitemap');

        if (strpos($sitemapFile, '/') !== FALSE) {
            // not allowed since site map file name is generated from domain name
            throw new Exception(get_class($this) . ': Attempted access to invalid sitemap [ $sitemapFile ]');
        }

        header('Content-type: application/xml');
        $requestedSitemap = PIMCORE_WEBSITE_PATH . '/var/search/sitemap/' . $sitemapFile;
        $indexSitemap = PIMCORE_WEBSITE_PATH . '/var/search/sitemap/sitemap.xml';

        if ($this->_getParam('sitemap') and is_file($requestedSitemap)) {
            $content = file_get_contents($requestedSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header('Content-Length: '.strlen($content));
            echo $content;
            exit;
        } else if (is_file($indexSitemap)) {
            $content = file_get_contents($indexSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header('Content-Length: '.strlen($content));
            echo $content;
            exit;
        } else {
            \Pimcore\Logger::debug('LuceneSearch: sitemap request - but no sitemap available to deliver');
            exit;
        }
    }

    /**
     * Use this Method the get some results for your ajax autoCompleter.
     * @throws \Exception
     * @throws \Zend_Search_Lucene_Exception
     */
    public function autoCompleteAction()
    {
        $this->removeViewRenderer();

        $terms = Plugin::wildcardFindTerms($this->query, $this->frontendIndex);

        if (empty($terms)) {
            $terms = Plugin::fuzzyFindTerms($this->query, $this->frontendIndex);
        }

        $suggestions = [];
        $counter = 1;

        foreach ($terms as $term) {
            $t = $term->text;

            //check if term can be found for current language
            $hits = NULL;

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();
            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
            $query->addSubquery($userQuery, TRUE);

            $this->addLanguageQuery($query);
            $this->addCategoryQuery($query);
            $this->addCountryQuery($query);
            $this->addRestrictionQuery($query);

            $validHits = $this->getValidHits($this->frontendIndex->find($query));

            if (count($validHits) > 0 and !in_array($t, $suggestions)) {
                $suggestions[] = $t;

                if ($counter >= $this->maxSuggestions) {
                    break;
                }

                $counter++;
            }
        }

        $data = [];

        foreach ($suggestions as $suggestion) {
            $data[] = $suggestion;
        }

        $this->_helper->json($data);
    }

    public function findAction()
    {
        $this->disableViewAutoRender();

        $searcher = new Searcher();

        try {
            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

            $field = $this->getParam('field');

            if (!empty($field)) {
                \Zend_Search_Lucene::setDefaultSearchField($field);
            }

            $searchResults = [];
            $validHits = [];

            if (!empty($this->query)) {
                if ($this->fuzzySearch) {
                    $this->query = str_replace(' ', '~ ', $this->query);
                    $this->query .= '~';
                    \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength(3);
                }

                $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($this->query, 'utf-8');
                $query->addSubquery($userQuery, TRUE);

                $this->addLanguageQuery($query);
                $this->addCountryQuery($query);
                $this->addCategoryQuery($query);
                $this->addRestrictionQuery($query);

                $validHits = $this->getValidHits($this->frontendIndex->find($query));

                $start = $this->perPage * ($this->currentPage - 1);
                $end = $start + ($this->perPage - 1);

                if ($end > count($validHits) - 1) {
                    $end = count($validHits) - 1;
                }

                for ($i = $start; $i <= $end; $i++) {
                    $hit = $validHits[$i];

                    /** @var \Zend_Search_Lucene_Document $doc */
                    $doc = $hit->getDocument();

                    $url = $doc->getField('url');
                    $title = $doc->getField('title');
                    $content = $doc->getField('content');

                    $searchResult['boost'] = $doc->boost;
                    $searchResult['title'] = $title->value;

                    $searchResult['url'] = $url->value;
                    $searchResult['summary'] = $searcher->getSummaryForUrl($content->value, $this->untouchedQuery);

                    //H1, description and imageTags are not available in pdf files.
                    try {
                        if ($doc->getField('h1')) {
                            $searchResult['h1'] = $doc->getField('h1')->value;
                        }

                        if ($doc->getField('description')) {
                            $searchResult['description'] = $searcher->getSummaryForUrl($doc->getField('description')->value, $this->untouchedQuery);
                        }

                        if ($doc->getField('imageTags')) {
                            $searchResult['imageTags'] = $doc->getField('imageTags')->value;
                        }
                    } catch (\Zend_Search_Lucene_Exception $e) {
                    }

                    $searchResult['categories'] = [];
                    try {
                        $categories = $doc->getField('categories')->value;
                        if(!empty($categories)) {
                            $searchResult['categories'] = $this->mapCategories($categories);
                        }
                    } catch (\Zend_Search_Lucene_Exception $e) {
                    }

                    $searchResults[] = $searchResult;
                    unset($searchResult);
                }
            }

            $suggestions = FALSE;
            if ($this->fuzzySearch) {
                $suggestions = $this->getFuzzySuggestions($searchResults);
            }

            $currentPageResultStart = $this->perPage * ($this->currentPage - 1);
            $currentPageResultEnd = $currentPageResultStart + $this->perPage;

            if ($currentPageResultEnd > count($validHits)) {
                $currentPageResultEnd = count($validHits);
            }

            $pages = 0;

            if (count($validHits) > 0) {
                $pages = ceil(count($validHits) / $this->perPage);
            }

            $this->view->assign([

                'searchCurrentPage' => $this->currentPage,
                'searchAllPages'    => $pages,

                'searchCategories'          => $this->searchCategories,
                'searchAvailableCategories' => $this->categories,

                'searchSuggestions'            => $suggestions,
                'searchLanguage'               => $this->searchLanguage,
                'searchCountry'                => $this->searchCountry,
                'searchPerPage'                => $this->perPage,
                'searchTotalHits'              => count($validHits),
                'searchQuery'                  => $this->untouchedQuery,
                'searchHasResults'             => count($searchResults) > 0,
                'searchResults'                => $searchResults,
                'searchCurrentPageResultStart' => $currentPageResultStart + 1,
                'searchCurrentPageResultEnd'   => $currentPageResultEnd

            ]);
        } catch (\Exception $e) {
            \Pimcore\Logger::debug('An Exception occurred during search: ' . $e->getMessage());

            $this->view->assign([
                'searchResults'    => [],
                'searchHasResults' => FALSE
            ]);
        }

        if ($this->getParam('viewScript')) {
            $this->renderScript($this->_getParam('viewScript'));
        } else {
            $this->renderScript('/search/find.php');
        }
    }

    /**
     * @param $queryHits
     *
     * @return array
     */
    protected function getValidHits($queryHits)
    {
        $validHits = [];

        if ($this->ownHostOnly && $queryHits !== NULL) {
            //get rid of hits from other hosts
            $currentHost = \Pimcore\Tool::getHostname();

            foreach ($queryHits as $hit) {
                $url = $hit->getDocument()->getField('url');
                if (strpos($url->value, '://' . $currentHost) !== FALSE) {
                    $validHits[] = $hit;
                }
            }
        } else {
            $validHits = $queryHits;
        }

        return $validHits;
    }

    protected function addCountryQuery($query)
    {
        if (!empty($this->searchCountry)) {

            $countryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
            $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'country'));

            $country = str_replace(['_', '-'], '', $this->searchCountry);
            $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term($country, 'country'));

            $query->addSubquery($countryQuery, TRUE);
        }

        return $query;
    }

    protected function addCategoryQuery($query)
    {
        if (!empty($this->searchCategories) && is_array($this->categories)) {
            $categoryTerms = [];
            $signs = [];
            foreach ($this->searchCategories as $categoryId) {
                if (in_array($categoryId, $this->categories)) {
                    $categoryTerms[] = new \Zend_Search_Lucene_Index_Term('category_' . $categoryId, 'category_' . $categoryId);
                    $signs[] = TRUE;
                }
            }
            $categoryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($categoryTerms, $signs);
            $query->addSubquery($categoryQuery, TRUE);
        }

        return $query;
    }

    protected function addLanguageQuery($query)
    {
        if (!empty($this->searchLanguage)) {
            $languageQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
            $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'lang'));

            if (is_object($this->searchLanguage)) {
                $lang = $this->searchLanguage->toString();
            } else {
                $lang = $this->searchLanguage;
            }

            $filter = new \Zend_Filter_Word_UnderscoreToDash();
            $lang = strtolower($filter->filter($lang));
            $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term($lang, 'lang'));

            $query->addSubquery($languageQuery, TRUE);
        }

        return $query;
    }

    protected function addRestrictionQuery($query)
    {
        if ($this->searchRestriction) {
            $restrictionTerms = [
                new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_default')
            ];

            $signs = [NULL];

            $class = Configuration::get('frontend.restriction.class');
            $method = Configuration::get('frontend.restriction.method');

            $call = [$class, $method];

            if (is_callable($call, FALSE)) {
                $allowedGroups = call_user_func($call);

                if (is_array($allowedGroups)) {
                    foreach ($allowedGroups as $group) {
                        $restrictionTerms[] = new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_' . $group);
                        $signs[] = NULL;
                    }
                }

                $restrictionQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($restrictionTerms, $signs);
                $query->addSubquery($restrictionQuery, TRUE);
            } else {
                throw new \Exception('Method "' . $method . '" in "' . $class . '" not callable');
            }
        }

        return $query;
    }

    protected function getFuzzySuggestions($searchResults = [])
    {
        $suggestions = [];

        //look for similar search terms
        if (!empty($this->query) && (empty($searchResults) || count($searchResults) < 1)) {
            $terms = Plugin::fuzzyFindTerms($this->query, $this->frontendIndex, 3);

            if (empty($terms) || count($terms) < 1) {
                $terms = Plugin::fuzzyFindTerms($this->query, $this->frontendIndex, 0);
            }

            if (is_array($terms)) {
                $counter = 0;

                foreach ($terms as $term) {
                    $t = $term->text;

                    $hits = NULL;

                    $query = new \Zend_Search_Lucene_Search_Query_Boolean();
                    $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
                    $query->addSubquery($userQuery, TRUE);

                    $this->addLanguageQuery($query);
                    $this->addCategoryQuery($query);
                    $this->addCountryQuery($query);
                    $this->addRestrictionQuery($query);

                    $validHits = $this->getValidHits($this->frontendIndex->find($query));

                    if (count($validHits) > 0 && !in_array($t, $suggestions)) {
                        $suggestions[] = $t;

                        if ($counter >= $this->maxSuggestions) {
                            break;
                        }

                        $counter++;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param string $documentCategories
     *
     * @return array
     */
    protected function mapCategories($documentCategories = '')
    {
        $categoryStore = [];
        $validCategories = $this->categories;
        if(empty($validCategories)) {
            return $categoryStore;
        }

        $categories = explode(',', $documentCategories);
        foreach($categories as $categoryId) {
            if(in_array($categoryId, $validCategories)) {
                $categoryStore[] = $categoryId;
            }
        }
        return $categoryStore;
    }

    /**
     * remove evil stuff from request string
     *
     * @param  string $requestString
     *
     * @return string
     */
    protected function cleanRequestString($requestString)
    {
        $queryFromRequest = strip_tags(urldecode($requestString));
        $queryFromRequest = str_replace(['<', '>', '"', "'", '&'], "", $queryFromRequest);

        return $queryFromRequest;
    }
}
