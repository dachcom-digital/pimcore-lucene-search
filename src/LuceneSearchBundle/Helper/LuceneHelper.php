<?php

namespace LuceneSearchBundle\Helper;

class LuceneHelper
{
    /**
     *  finds similar terms
     *
     * @param string                        $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     * @param integer                       $prefixLength optionally specify prefix length, default 0
     * @param float                         $similarity   optionally specify similarity, default 0.5
     *
     * @return string[] $similarSearchTerms
     */
    public function fuzzyFindTerms($queryStr, \Zend_Search_Lucene_Interface $index, $prefixLength = 0, $similarity = 0.5)
    {
        \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength($prefixLength);
        $term = new \Zend_Search_Lucene_Index_Term($queryStr);

        try {
            $fuzzyQuery = new \Zend_Search_Lucene_Search_Query_Fuzzy($term, $similarity);
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        try {
            $terms = $fuzzyQuery->rewrite($index)->getQueryTerms();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        return $terms;

    }

    /**
     * find matching terms beginning with query string
     *
     * @param string                        $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     *
     * @return array $hits
     */
    public function wildcardFindTerms($queryStr, \Zend_Search_Lucene_Interface $index)
    {
        $pattern = new \Zend_Search_Lucene_Index_Term($queryStr . '*');
        $userQuery = new \Zend_Search_Lucene_Search_Query_Wildcard($pattern);
        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(2);

        try {
            $terms = $userQuery->rewrite($index)->getQueryTerms();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        return $terms;

    }

    /**
     * @param $term
     *
     * @return string
     */
    public function cleanTerm($term)
    {
        return trim(
            preg_replace('|\s{2,}|', ' ',
                preg_replace('|[^\p{L}\p{N} ]/u|', ' ',
                    strtolower(
                        strip_tags(
                            str_replace(["\n", '<'], [' ', ' <'], $term)
                        )
                    )
                )
            )
        );
    }
}