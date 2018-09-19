<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Event\RestrictionContextEvent;
use LuceneSearchBundle\Helper\LuceneHelper;
use LuceneSearchBundle\Helper\StringHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Pimcore\Controller\FrontendController as PimcoreFrontEndController;

class FrontendController extends PimcoreFrontEndController
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
     * @var LuceneHelper
     */
    protected $luceneHelper;

    /**
     * @var StringHelper
     */
    private $stringHelper;

    /**
     * @var \Zend_Search_Lucene_Interface
     */
    protected $frontendIndex;

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * category, to restrict query, incoming argument
     *
     * @var array
     */
    protected $queryCategories = [];

    /**
     * query, incoming argument
     *
     * @var String
     */
    protected $query = '';

    /**
     * query, incoming argument, unmodified
     *
     * @var String
     */
    protected $untouchedQuery = '';

    /**
     * @var string
     */
    protected $searchLanguage = null;

    /**
     * @var string
     */
    protected $searchCountry = null;

    /**
     * @var bool
     */
    protected $checkRestriction = false;

    /**
     * @var bool
     */
    protected $ownHostOnly = false;

    /**
     * @var bool
     */
    protected $allowSubDomains = false;

    /**
     * @var bool
     */
    protected $fuzzySearchResults = false;

    /**
     * @var bool
     */
    protected $searchSuggestion = false;

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
     * @param RequestStack  $requestStack
     * @param Configuration $configuration
     * @param LuceneHelper  $luceneHelper
     * @param StringHelper  $stringHelper
     *
     * @throws \Exception
     */
    public function __construct(
        RequestStack $requestStack,
        Configuration $configuration,
        LuceneHelper $luceneHelper,
        StringHelper $stringHelper
    ) {
        $this->requestStack = $requestStack;
        $this->configuration = $configuration;
        $this->luceneHelper = $luceneHelper;
        $this->stringHelper = $stringHelper;

        $requestQuery = $this->requestStack->getMasterRequest()->query;

        if (!$this->configuration->getConfig('enabled')) {
            return false;
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
            if ($localeConfig['ignore_language'] === false) {
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
                if (!is_array($queryCategories)) {
                    $queryCategories = [$queryCategories];
                }
                $this->queryCategories = array_map('intval', $queryCategories);
            }

            //Set Country
            if ($localeConfig['ignore_country'] === false) {
                $this->searchCountry = $requestQuery->get('country');

                if ($this->searchCountry == 'global') {
                    $this->searchCountry = 'international';
                } elseif (empty($this->searchCountry)) {
                    $this->searchCountry = 'international';
                }
            } else {
                $this->searchCountry = null;
            }

            //Set Restrictions (Auth)
            $this->checkRestriction = $restrictionConfig['enabled'] === true;

            //Set Fuzzy Search
            if ($this->configuration->getConfig('fuzzy_search_results') === true) {
                $this->fuzzySearchResults = true;
            }

            //Set Search Suggestions
            if ($this->configuration->getConfig('search_suggestion') === true) {
                $this->searchSuggestion = true;
            }

            //Set own Host Only
            if ($this->configuration->getConfig('own_host_only') === true) {
                $this->ownHostOnly = true;
            }

            //Set subdomain restriction
            if ($this->configuration->getConfig('allow_subdomains') === true) {
                $this->allowSubDomains = true;
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

        if ($this->ownHostOnly === false || $queryHits === null) {
            $validHits = $queryHits;
            return $validHits;
        }

        $currentHost = \Pimcore\Tool::getHostname();

        $allowedHosts = [];

        if ($this->allowSubDomains === true) {
            // only use hostname.tld for comparison
            $allowedHosts[] = join('.', array_slice(explode('.', $currentHost), -2));
        } else {
            // user entire *.hostname.tld for comparison
            $allowedHosts[] = $currentHost;
        }

        /** @var \Zend_Search_Lucene_Search_QueryHit $hit */
        foreach ($queryHits as $hit) {

            try {
                $url = $hit->getDocument()->getField('url');
            } catch (\Zend_Search_Lucene_Exception $e) {
                continue;
            }

            $currentHostname = parse_url($url->value, PHP_URL_HOST);

            if ($this->allowSubDomains) {
                $currentHostname = join('.', array_slice(explode('.', $currentHostname), -2));
            }

            if (in_array($currentHostname, $allowedHosts)) {
                $validHits[] = $hit;
            }
        }

        return $validHits;
    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Boolean $query
     */
    protected function addAvailabilityQuery(\Zend_Search_Lucene_Search_Query_Boolean $query)
    {
        try {
            $availabilityQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return;
        }

        $availabilityQuery->addTerm(new \Zend_Search_Lucene_Index_Term('unavailable', 'internalAvailability'));
        $query->addSubquery($availabilityQuery, false);

    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Boolean $query
     */
    protected function addCountryQuery(\Zend_Search_Lucene_Search_Query_Boolean $query)
    {
        if (empty($this->searchCountry)) {
            return;
        }

        try {
            $countryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return;
        }

        $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'country'));

        $country = str_replace(['_', '-'], '', $this->searchCountry);
        $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term($country, 'country'));

        $query->addSubquery($countryQuery, true);

    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Boolean $query
     */
    protected function addCategoryQuery(\Zend_Search_Lucene_Search_Query_Boolean $query)
    {
        if (empty($this->queryCategories) || !is_array($this->categories)) {
            return;
        }

        try {
            $categoryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return;
        }

        foreach ($this->queryCategories as $categoryId) {
            $key = array_search($categoryId, array_column($this->categories, 'id'));
            if ($key !== false) {
                $categoryQuery->addTerm(new \Zend_Search_Lucene_Index_Term(true, 'category_' . $categoryId));
            }
        }

        $query->addSubquery($categoryQuery, true);

    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Boolean $query
     *
     */
    protected function addLanguageQuery(\Zend_Search_Lucene_Search_Query_Boolean $query)
    {
        if (empty($this->searchLanguage)) {
            return;
        }

        try {
            $languageQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return;
        }

        $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'lang'));

        if (is_object($this->searchLanguage)) {
            $lang = $this->searchLanguage->toString();
        } else {
            $lang = $this->searchLanguage;
        }

        $lang = strtolower(str_replace('_', '-', $lang));
        $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term($lang, 'lang'));

        $query->addSubquery($languageQuery, true);
    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Boolean $query
     */
    protected function addRestrictionQuery(\Zend_Search_Lucene_Search_Query_Boolean $query)
    {
        if (!$this->checkRestriction) {
            return;
        }

        $event = new RestrictionContextEvent();
        \Pimcore::getEventDispatcher()->dispatch(
            'lucene_search.frontend.restriction_context',
            $event
        );

        try {
            $restrictionQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return;
        }

        $restrictionQuery->addTerm(new \Zend_Search_Lucene_Index_Term(true, 'restrictionGroup_default'));

        $allowedGroups = $event->getAllowedRestrictionGroups();
        if (is_array($allowedGroups)) {
            foreach ($allowedGroups as $groupId) {
                $restrictionQuery->addTerm(new \Zend_Search_Lucene_Index_Term(true, 'restrictionGroup_' . $groupId));
            }
        }

        $query->addSubquery($restrictionQuery, true);
    }

}