<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Helper\HighlighterHelper;
use Symfony\Component\HttpFoundation\Response;

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
                    $searchResult['summary'] = $this->highlighterHelper->getSummaryForUrl($content->value, $this->untouchedQuery);

                    //H1, description and imageTags are not available in pdf files.
                    try {
                        if ($doc->getField('h1')) {
                            $searchResult['h1'] = $doc->getField('h1')->value;
                        }

                        if ($doc->getField('description')) {
                            $searchResult['description'] = $this->highlighterHelper->getSummaryForUrl($doc->getField('description')->value, $this->untouchedQuery);
                        }

                        if ($doc->getField('imageTags')) {
                            $searchResult['imageTags'] = $doc->getField('imageTags')->value;
                        }
                    } catch (\Zend_Search_Lucene_Exception $e) {
                    }

                    foreach ($this->categories as $category) {
                        try {
                            $searchResult['categories'][] = $hit->getDocument()->getField('cat')->value;
                        } catch (\Zend_Search_Lucene_Exception $e) {
                        }
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

            $viewParams = [
                'searchCurrentPage' => $this->currentPage,
                'searchAllPages'    => $pages,

                'searchCategory'            => $this->category,
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
            ];
        } catch (\Exception $e) {

            \Pimcore\Logger::debug('An Exception occurred during search: ' . $e->getMessage());

            $viewParams = [
                'searchResults'    => [],
                'searchHasResults' => FALSE
            ];
        }

        $content = $this->templating->render('@LuceneSearch/List/result.html.twig', $viewParams);

        $response = new Response($content);

        return $response;
    }

    private function getFuzzySuggestions($searchResults = [])
    {
        $suggestions = [];

        //look for similar search terms
        if (!empty($this->query) && (empty($searchResults) || count($searchResults) < 1)) {
            $terms = $this->luceneHelper->fuzzyFindTerms($this->query, $this->frontendIndex, 3);

            if (empty($terms) || count($terms) < 1) {
                $terms = $this->luceneHelper->fuzzyFindTerms($this->query, $this->frontendIndex, 0);
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
}