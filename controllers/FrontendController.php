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
    protected $searchLanguage;

    /**
     * @var
     */
    protected $searchCountry;

    /**
     * @var
     */
    protected $searchRestriction = false;

    /**
     * @var bool
     */
    protected $ownHostOnly = false;

    /**
     * @var array
     */
    protected $categories = array();

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();

        if (Configuration::get('frontend.enabled'))
        {
            try
            {
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());

                $this->frontendIndex = \Zend_Search_Lucene::open(Plugin::getFrontendSearchIndex());
                $this->categories = Configuration::get('frontend.categories');

                if (Configuration::get('frontend.ignoreLanguage') !== TRUE)
                {
                    $this->searchLanguage = $this->getParam('language');

                    if (empty($this->searchLanguage))
                    {
                        try
                        {
                            $this->searchLanguage = \Zend_Registry::get('Zend_Locale');
                        }
                        catch (Exception $e)
                        {
                            $this->searchLanguage = 'en';
                        }
                    }
                }
                else
                {
                    $this->searchLanguage = null;
                }

                if (Configuration::get('frontend.ignoreCountry') !== TRUE)
                {
                    $this->searchCountry = $this->getParam('country');

                    if( $this->searchCountry == 'global')
                    {
                        $this->searchCountry = 'international';
                    }
                    else if (empty($this->searchCountry))
                    {
                        $this->searchCountry = 'international';
                    }
                }
                else
                {
                    $this->searchCountry = null;
                }

                if (Configuration::get('frontend.ignoreRestriction') === FALSE)
                {
                    $this->searchRestriction = TRUE;
                }

                $this->fuzzySearch = false;

                if (Configuration::get('frontend.fuzzySearch') == TRUE)
                {
                    $this->fuzzySearch = true;
                }

                if (Configuration::get('frontend.ownHostOnly') == TRUE)
                {
                    $this->ownHostOnly = true;
                }
            }
            catch (Exception $e)
            {
                throw new Exception('could not open index');
            }

        }

    }

    public function sitemapAction()
    {
        $this->removeViewRenderer();

        if( Configuration::get('frontend.sitemap.render') === FALSE )
        {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        $sitemapFile = $this->_getParam('sitemap');

        if (strpos($sitemapFile, '/') !== FALSE)
        {
            // not allowed since site map file name is generated from domain name
            throw new Exception(get_class($this) . ': Attempted access to invalid sitemap [ $sitemapFile ]');
        }

        header('Content-type: application/xml');
        $requestedSitemap = PIMCORE_WEBSITE_PATH . '/var/search/sitemap/' . $sitemapFile;
        $indexSitemap = PIMCORE_WEBSITE_PATH . '/var/search/sitemap/sitemap.xml';

        if ($this->_getParam('sitemap') and is_file($requestedSitemap))
        {
            $content = file_get_contents($requestedSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header('Content-Length: '.strlen($content));
            echo $content;
            exit;

        }
        else if (is_file($indexSitemap))
        {
            $content = file_get_contents($indexSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header('Content-Length: '.strlen($content));
            echo $content;
            exit;

        }
        else
        {
            \Logger::debug('LuceneSearch: sitemap request - but no sitemap available to deliver');
            exit;

        }

    }

    public function autocompleteAction()
    {
        $queryFromRequest = $this->cleanRequestString($this->_getParam('q'));
        $categoryFromRequest = $this->cleanRequestString($this->_getParam('cat'));

        $terms = Plugin::wildcardFindTerms(strtolower($queryFromRequest), $this->frontendIndex);

        if (empty($terms))
        {
            $terms = Plugin::fuzzyFindTerms(strtolower($queryFromRequest), $this->frontendIndex);
        }

        $suggestions = array();
        $counter = 1;

        foreach ($terms as $term)
        {
            $t = $term->text;

            //check if term can be found for current language
            $hits = null;

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
            $query->addSubquery($userQuery, true);

            $this->addLanguageQuery( $query );

            $this->addCategoryQuery( $query, $categoryFromRequest );

            $this->addCountryQuery( $query );

            $this->addRestrictionQuery( $query );

            $validHits = $this->getValidHits( $this->frontendIndex->find($query) );

            if (count($validHits) > 0 and !in_array($t, $suggestions))
            {
                $suggestions[] = $t;
                if ($counter >= 10) break;
                $counter++;

            }
        }

        $this->removeViewRenderer();

        $data = [];

        foreach ($suggestions as $suggestion)
        {
            $data[] = $suggestion;
        }

        $this->_helper->json($data);
    }

    public function findAction()
    {
        $queryFromRequest = $this->cleanRequestString($_REQUEST['query']);
        $categoryFromRequest = $this->cleanRequestString($_REQUEST['cat']);

        $searcher = new Searcher();

        $this->view->groupByCategory = $this->getParam('groupByCategory');
        $this->view->omitSearchForm = $this->getParam('omitSearchForm');
        $this->view->categoryOrder = $this->getParam('categoryOrder');
        $this->view->omitJsIncludes = $this->getParam('omitJsIncludes');

        $perPage = $this->getParam('perPage');

        if (empty($perPage))
        {
            $perPage = 10;
        }

        $page = $this->getParam('page');

        if (empty($page))
        {
            $page = 1;
        }

        $queryStr = strtolower($queryFromRequest);
        $this->view->category = $categoryFromRequest;
        $this->view->language = $this->searchLanguage;
        $this->view->country = $this->searchCountry;

        $categories = Configuration::get('frontend.categories');

        if (!empty($categories))
        {
            $this->view->availableCategories = $categories;
        }

        $doFuzzy = $this->getParam('fuzzy');

        try
        {
            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

            $field = $this->getParam('field');

            if (!empty($field))
            {
                \Zend_Search_Lucene::setDefaultSearchField($field);
            }

            $searchResults = array();
            $validHits = array();

            if (!empty($queryStr))
            {
                if ($doFuzzy)
                {
                    $queryStr = str_replace(' ', '~ ', $queryStr);
                    $queryStr .= '~';
                    \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength(3);
                }

                $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($queryStr, 'utf-8');
                $query->addSubquery($userQuery, true);

                $this->addLanguageQuery( $query );

                $this->addCountryQuery( $query );

                $this->addCategoryQuery( $query, $this->view->category );

                $this->addRestrictionQuery( $query );

                $validHits = $this->getValidHits( $this->frontendIndex->find($query) );

                $start = $perPage * ($page - 1);
                $end = $start + ($perPage - 1);

                if ($end > count($validHits) - 1)
                {
                    $end = count($validHits) - 1;
                }

                for ($i = $start; $i <= $end; $i++)
                {
                    $hit = $validHits[$i];

                    $url = $hit->getDocument()->getField('url');
                    $title = $hit->getDocument()->getField('title');
                    $content = $hit->getDocument()->getField('content');
                    //$imageTags = $hit->getDocument()->getField('imageTags');

                    $searchResult['boost'] = $hit->getDocument()->boost;
                    $searchResult['title'] = $title->value;
                    //$searchResult['imageTags'] = $imageTags->value;

                    $searchResult['url'] = $url->value;
                    $searchResult['summary'] = $searcher->getSummaryForUrl($content->value, $queryStr);

                    try
                    {
                        if ($hit->getDocument()->getField('h1'))
                        {
                            $searchResult['h1'] = $hit->getDocument()->getField('h1')->value;
                        }

                    }
                    catch (\Zend_Search_Lucene_Exception $e)
                    {
                    }

                    foreach ($this->categories as $category)
                    {
                        try
                        {
                            $searchResult['categories'][] = $hit->getDocument()->getField('cat')->value;
                        }
                        catch (\Zend_Search_Lucene_Exception $e)
                        {
                        }
                    }

                    $searchResults[] = $searchResult;
                    unset($searchResult);

                }

            }

            if (count($validHits) < 1)
            {
                $this->view->pages = 0;
            } else
            {
                $this->view->pages = ceil(count($validHits) / $perPage);
            }

            $this->view->perPage = $perPage;
            $this->view->page = $page;
            $this->view->total = count($validHits);
            $this->view->query = $queryStr;

            $this->view->searchResults = $searchResults;

            if ($this->fuzzySearch)
            {
                //look for similar search terms
                if (!empty($queryStr) and (empty($searchResults) or count($searchResults) < 1))
                {
                    $terms = Plugin::fuzzyFindTerms($queryStr, $this->frontendIndex, 3);
                    if (empty($terms) or count($terms) < 1)
                    {
                        $terms = Plugin::fuzzyFindTerms($queryStr, $this->frontendIndex, 0);
                    }

                    $suggestions = array();

                    if (is_array($terms))
                    {
                        $counter = 0;
                        foreach ($terms as $term)
                        {
                            $t = $term->text;

                            $hits = null;

                            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

                            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
                            $query->addSubquery($userQuery, true);

                            $this->addLanguageQuery( $query );

                            $this->addCategoryQuery( $query, $categoryFromRequest );

                            $this->addCountryQuery( $query );

                            $this->addRestrictionQuery( $query );

                            $validHits = $this->getValidHits( $this->frontendIndex->find($query) );

                            if (count($validHits) > 0 and!in_array($t, $suggestions))
                            {
                                $suggestions[] = $t;
                                if ($counter >= 20) break;
                                $counter++;
                            }

                        }
                    }

                    $this->view->suggestions = $suggestions;

                }
            }

        }
        catch (Exception $e)
        {
            \Logger::log('An Exception occured during search:', \Zend_Log::ERR);
            \Logger::log($e, \Zend_Log::ERR);
            $this->view->searchResults = array();
        }

        if ($this->getParam('viewscript'))
        {
            $this->renderScript($this->_getParam('viewscript'));
        }

    }

    private function getValidHits( $queryHits )
    {
        $validHits = array();

        if ($this->ownHostOnly and $queryHits != null)
        {
            //get rid of hits from other hosts
            $currenthost = $_SERVER['HTTP_HOST'];

            if (count($queryHits) == 1)
            {
                $url = $queryHits[0]->getDocument()->getField('url');
                if (strpos($url->value, 'http://' . $currenthost) !== FALSE || strpos($url->value, 'https://' . $currenthost) !== FALSE)
                {
                    $validHits[] = $queryHits[0];
                }
            }

            for ($i = 0; $i < (count($queryHits)); $i++)
            {
                $url = $queryHits[$i]->getDocument()->getField('url');
                if (strpos($url->value, 'http://' . $currenthost) !== FALSE)
                {
                    $validHits[] = $queryHits[$i];
                }
            }
        }
        else
        {
            $validHits = $queryHits;
        }

        return $validHits;

    }

    private function addCountryQuery( $query )
    {
        if (!empty($this->searchCountry))
        {
            $country = str_replace(array('_', '-'), '', $this->searchCountry);
            $countryTerm = new \Zend_Search_Lucene_Index_Term($country, 'country');
            $countryQuery = new \Zend_Search_Lucene_Search_Query_Term($countryTerm);
            $query->addSubquery($countryQuery, true);
        }

        return $query;
    }

    private function addCategoryQuery( $query, $category = NULL )
    {
        if ( !empty( $category ) )
        {
            $categoryTerm = new \Zend_Search_Lucene_Index_Term($category, 'cat');
            $categoryQuery = new \Zend_Search_Lucene_Search_Query_Term($categoryTerm);
            $query->addSubquery($categoryQuery, true);
        }

        return $query;
    }

    private function addLanguageQuery( $query )
    {
        if (!empty($this->searchLanguage))
        {
            if (is_object($this->searchLanguage))
            {
                $lang = $this->searchLanguage->toString();
            }
            else
            {
                $lang = $this->searchLanguage;
            }

            $lang = str_replace(array('_', '-'), '', $lang);
            $languageTerm = new \Zend_Search_Lucene_Index_Term($lang, 'lang');
            $languageQuery = new \Zend_Search_Lucene_Search_Query_Term($languageTerm);
            $query->addSubquery($languageQuery, true);
        }

        return $query;
    }

    private function addRestrictionQuery( $query )
    {
        if( $this->searchRestriction )
        {
            $restrictionTerms = array(
                new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_default')
            );

            $signs = array( null );

            $class = Configuration::get('frontend.restriction.class');
            $method = Configuration::get('frontend.restriction.method');

            $call = array($class, $method);

            if( is_callable($call, false) )
            {
                $allowedGroups = call_user_func( $call );

                if( is_array($allowedGroups) )
                {
                    foreach( $allowedGroups as $group)
                    {
                        $restrictionTerms[] = new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_' . $group);
                        $signs[] = null;
                    }
                }

                $restrictionQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($restrictionTerms, $signs);
                $query->addSubquery($restrictionQuery, true);
            }
            else
            {
                throw new \Exception('Method "' . $method . '" in "' . $class . '" not callable');
            }

        }

        return $query;

    }

    /**
     * remove evil stuff from request string
     * @param  string $requestString
     * @return string
     */
    private function cleanRequestString($requestString)
    {
        $queryFromRequest = strip_tags(urldecode($requestString));
        $queryFromRequest = str_replace(array('<', '>', '"', "'", '&'), "", $queryFromRequest);

        return $queryFromRequest;

    }
}