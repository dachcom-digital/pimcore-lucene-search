<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Helper\HighlighterHelper;

class ListController extends FrontendController
{
    /**
     * @var HighlighterHelper
     */
    protected $highlighterHelper;

    /**
     * @param HighlighterHelper $highlighterHelper
     */
    public function setHighlighterHelper(HighlighterHelper $highlighterHelper)
    {
        $this->highlighterHelper = $highlighterHelper;
    }

    public function getResultAction()
    {
        $requestQuery = $this->requestStack->getMasterRequest()->query;

        try {
            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

            $field = $requestQuery->get('field');

            if (!empty($field)) {
                \Zend_Search_Lucene::setDefaultSearchField($field);
            }

            $searchResults = [];
            $validHits = [];

            if (!empty($this->query)) {
                //fuzzy search term if enabled
                if ($this->fuzzySearchResults) {
                    $this->query = str_replace(' ', '~ ', $this->query);
                    $this->query .= '~';
                    \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength(3);
                }

                $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($this->query, 'utf-8');
                $query->addSubquery($userQuery, true);

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
                    $searchResult['summary'] = $this->highlighterHelper->getSummaryForUrl($content->value, $this->untouchedQuery);

                    //H1, description and imageTags are not available in pdf files.
                    try {
                        if ($doc->getField('h1')) {
                            $searchResult['h1'] = $doc->getField('h1')->value;
                        }

                        if ($doc->getField('description')) {
                            $searchResult['description'] = $this->highlighterHelper->getSummaryForUrl($doc->getField('description')->value,
                                $this->untouchedQuery);
                        }

                        if ($doc->getField('imageTags')) {
                            $searchResult['imageTags'] = $doc->getField('imageTags')->value;
                        }
                    } catch (\Zend_Search_Lucene_Exception $e) {
                    }

                    $searchResult['categories'] = [];

                    try {
                        $categories = $hit->getDocument()->getField('categories')->value;
                        if (!empty($categories)) {
                            $searchResult['categories'] = $this->mapCategories($categories);
                        }
                    } catch (\Zend_Search_Lucene_Exception $e) {
                    }

                    $searchResults[] = $searchResult;
                    unset($searchResult);
                }
            }

            $suggestions = false;
            if ($this->searchSuggestion && count($searchResults) === 0) {
                $suggestions = $this->getFuzzySuggestions();
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

            $viewParams = [

                'searchCurrentPage'            => $this->currentPage,
                'searchAllPages'               => $pages,
                'searchCategory'               => $this->queryCategories,
                'searchAvailableCategories'    => $this->categories,
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
            ];

            $viewName = 'result';

        } catch (\Exception $e) {

            $viewParams = [
                'error'        => true,
                'errorMessage' => $e->getMessage() . ' (' . $e->getFile() . ' Line: ' . $e->getLine() . ')',
            ];

            $viewName = 'error';
        }

        return $this->renderTemplate('@LuceneSearch/List/' . $viewName . '.html.twig', $viewParams);
    }

    /**
     * look for similar search terms
     *
     * @return array
     */
    protected function getFuzzySuggestions()
    {
        $suggestions = [];

        if (empty($this->untouchedQuery)) {
            return $suggestions;
        }

        $terms = $this->luceneHelper->fuzzyFindTerms($this->untouchedQuery, $this->frontendIndex, 3);

        if (empty($terms) || count($terms) < 1) {
            $terms = $this->luceneHelper->fuzzyFindTerms($this->untouchedQuery, $this->frontendIndex, 0);
        }

        if (is_array($terms)) {
            $counter = 0;

            foreach ($terms as $term) {
                $t = $term->text;

                $hits = null;

                $query = new \Zend_Search_Lucene_Search_Query_Boolean();
                $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
                $query->addSubquery($userQuery, true);

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
        $validCategories = $this->configuration->getCategories();

        if (empty($validCategories)) {
            return $categoryStore;
        }

        $categories = explode(',', $documentCategories);

        foreach ($categories as $categoryId) {
            $key = array_search($categoryId, array_column($validCategories, 'id'));
            if ($key !== false) {
                $categoryStore[] = ['id' => $categoryId, 'label' => $validCategories[$key]['label']];
            }
        }

        return $categoryStore;

    }
}