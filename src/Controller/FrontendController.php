<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Event\RestrictionContextEvent;
use LuceneSearchBundle\Helper\LuceneHelper;
use LuceneSearchBundle\Helper\StringHelper;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class FrontendController
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @var LuceneHelper
     */
    protected $luceneHelper;

    /**
     * @var StringHelper
     */
    private $stringHelper;

    /**
     * @var string
     */
    protected $frontendIndex;

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * category, to restrict query, incoming argument
     * @var array
     */
    protected $queryCategories = [];

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
     * @var string
     */
    protected $searchLanguage = NULL;

    /**
     * @var string
     */
    protected $searchCountry = NULL;

    /**
     * @var bool
     */
    protected $checkRestriction = FALSE;

    /**
     * @var bool
     */
    protected $ownHostOnly = FALSE;

    /**
     * @var bool
     */
    protected $fuzzySearchResults = FALSE;

    /**
     * @var bool
     */
    protected $searchSuggestion = FALSE;

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
     * FrontendController constructor.
     *
     * @param RequestStack    $requestStack
     * @param EngineInterface $templating
     * @param Configuration   $configuration
     * @param LuceneHelper    $luceneHelper
     * @param StringHelper    $stringHelper
     *
     * @throws \Exception
     */
    public function __construct(
        RequestStack $requestStack,
        EngineInterface $templating,
        Configuration $configuration,
        LuceneHelper $luceneHelper,
        StringHelper $stringHelper
    ) {
        $this->requestStack = $requestStack;
        $this->templating = $templating;
        $this->configuration = $configuration;
        $this->luceneHelper = $luceneHelper;
        $this->stringHelper = $stringHelper;

        $requestQuery = $this->requestStack->getMasterRequest()->query;

        if (!$this->configuration->getConfig('enabled')) {
            return FALSE;
        }

        try {

            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(
                new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive()
            );

            $this->frontendIndex = \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);

            //set search term query
            $searchQuery = $this->stringHelper->cleanRequestString($requestQuery->get('q'));

            if (!empty($searchQuery)) {
                $this->query = $this->luceneHelper->cleanTerm($searchQuery);
                $this->untouchedQuery = $this->query;
            }

            $localeConfig = $this->configuration->getConfig('locale');
            $restrictionConfig = $this->configuration->getConfig('restriction');
            $viewConfig = $this->configuration->getConfig('view');

            //set Language
            if ($localeConfig['ignore_language'] === FALSE) {
                $requestLang = $requestQuery->get('language');

                //no language provided, try to get from requestStack.
                if (empty($requestLang)) {
                    $masterRequest = $this->requestStack->getMasterRequest();
                    if ($masterRequest) {
                        $this->searchLanguage = $this->requestStack->getMasterRequest()->getLocale();
                    } else {
                        $this->searchLanguage = \Pimcore\Tool::getDefaultLanguage();
                    }
                } else {
                    $this->searchLanguage = $requestLang;
                }
            }

            //Set Categories
            $this->categories = $this->configuration->getCategories();

            $queryCategories = $requestQuery->get('categories');

            if (!empty($queryCategories)) {
                if(!is_array($queryCategories)) {
                    $queryCategories = [$queryCategories];
                }
                $this->queryCategories = array_map('intval', $queryCategories);
            }

            //Set Country
            if ($localeConfig['ignore_country'] === FALSE) {
                $this->searchCountry = $requestQuery->get('country');

                if ($this->searchCountry == 'global') {
                    $this->searchCountry = 'international';
                } else if (empty($this->searchCountry)) {
                    $this->searchCountry = 'international';
                }
            } else {
                $this->searchCountry = NULL;
            }

            //Set Restrictions (Auth)
            $this->checkRestriction = $restrictionConfig['enabled'] === TRUE;

            //Set Fuzzy Search
            if ($this->configuration->getConfig('fuzzy_search_results') === TRUE) {
                $this->fuzzySearchResults = TRUE;
            }

            //Set Search Suggestions
            if ($this->configuration->getConfig('search_suggestion') === TRUE) {
                $this->searchSuggestion = TRUE;
            }

            //Set own Host Only
            if ($this->configuration->getConfig('own_host_only') === TRUE) {
                $this->ownHostOnly = TRUE;
            }

            //Set Entries per Page
            $this->perPage = $viewConfig['max_per_page'];
            if (!empty($requestQuery->get('perPage'))) {
                $this->perPage = (int)$requestQuery->get('perPage');
            }

            //Set max Suggestions
            $this->maxSuggestions = $viewConfig['max_suggestions'];

            //Set Current Page
            $currentPage = $requestQuery->get('page');
            if (!empty($currentPage)) {
                $this->currentPage = (int)$currentPage;
            }
        } catch (\Exception $e) {
            throw new \Exception('Error while parsing lucene search params (message was: ' . $e->getMessage() . ').');
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

    /**
     * @param $query
     *
     * @return mixed
     */
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

    /**
     * @param $query
     *
     * @return mixed
     */
    protected function addCategoryQuery($query)
    {
        if (!empty($this->queryCategories) && is_array($this->categories)) {

            $categoryTerms = [];
            $signs = [];

            foreach ($this->queryCategories as $categoryId) {
                $key = array_search($categoryId, array_column($this->categories, 'id'));
                if($key !== FALSE) {
                    $categoryTerms[] = new \Zend_Search_Lucene_Index_Term(TRUE, 'category_' . $categoryId);
                    $signs[] = NULL;
                }
            }

            $categoryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($categoryTerms, $signs);
            $query->addSubquery($categoryQuery, TRUE);
        }

        return $query;
    }

    /**
     * @param $query
     *
     * @return mixed
     */
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

            $lang = strtolower(str_replace('_', '-', $lang));
            $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term($lang, 'lang'));

            $query->addSubquery($languageQuery, TRUE);
        }

        return $query;
    }

    /**
     * @param $query
     *
     * @return mixed
     * @throws \Exception
     */
    protected function addRestrictionQuery($query)
    {
        if (!$this->checkRestriction) {
            return $query;
        }

        $event = new RestrictionContextEvent();
        \Pimcore::getEventDispatcher()->dispatch(
            'lucene_search.frontend.restriction_context',
            $event
        );

        $signs = [NULL];
        $restrictionTerms = [
            new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_default')
        ];

        $allowedGroups = $event->getAllowedRestrictionGroups();
        if (is_array($allowedGroups)) {
            foreach ($allowedGroups as $groupId) {
                $restrictionTerms[] = new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_' . $groupId);
                $signs[] = NULL;
            }
        }

        $restrictionQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($restrictionTerms, $signs);
        $query->addSubquery($restrictionQuery, TRUE);

        return $query;
    }

}