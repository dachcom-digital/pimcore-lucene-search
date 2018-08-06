<?php

namespace LuceneSearchBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AutoCompleteController extends FrontendController
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws \Exception
     * @throws \Zend_Search_Lucene_Exception
     * @throws \Zend_Search_Lucene_Search_QueryParserException
     */
    public function searchAction(Request $request)
    {
        $terms = $this->luceneHelper->wildcardFindTerms($this->query, $this->frontendIndex);

        if (empty($terms)) {
            $terms = $this->luceneHelper->fuzzyFindTerms($this->query, $this->frontendIndex);
        }

        $suggestions = [];
        $counter = 1;

        foreach ($terms as $term) {
            $t = $term->text;

            //check if term can be found for current language
            $hits = null;

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();
            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
            $query->addSubquery($userQuery, true);

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

        return new JsonResponse($data);
    }

}