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
     * @throws \Zend_Search_Lucene_Exception
     */
    public function searchAction(Request $request)
    {
        $terms = $this->luceneHelper->wildcardFindTerms($this->query, $this->frontendIndex);

        // try to find fuzzy related terms if not wildcard terms has been found
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

            $this->addAdditionalSubQueries($query);

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