<?php

namespace LuceneSearch\Model;

class Searcher
{
    /**
     * Integer | Summary Length
     */
    const SUMMARY_LENGTH = 255;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    /**
     * Searcher constructor.
     */
    public function __construct()
    {
        $this->db = \Pimcore\Db::get();
    }

    /**
     * @param $content
     * @param $queryStr
     *
     * @return mixed|string
     */
    public function getSummaryForUrl($content, $queryStr)
    {
        $queryElements = explode(' ', $queryStr);

        //remove additional whitespaces
        $content = preg_replace('/[\s]+/', ' ', $content);

        $summary = $this->getHighlightedSummary($content, $queryElements);

        if ($summary === FALSE) {
            return substr($content, 0, self::SUMMARY_LENGTH);
        }

        return $summary;
    }

    /**
     * finds the query strings position in the text
     *
     * @param  string $text
     * @param  string $queryStr
     *
     * @return int
     */
    protected function findPosInSummary($text, $queryStr)
    {
        $pos = stripos($text, ' ' . $queryStr . ' ');
        if ($pos === FALSE) {
            $pos = stripos($text, '"' . $queryStr . '"');
        }
        if ($pos === FALSE) {
            $pos = stripos($text, '"' . $queryStr . '"');
        }
        if ($pos === FALSE) {
            $pos = stripos($text, ' ' . $queryStr . '-');
        }
        if ($pos === FALSE) {
            $pos = stripos($text, '-' . $queryStr . ' ');
        }
        if ($pos === FALSE) {
            $pos = stripos($text, $queryStr . ' ');
        }
        if ($pos === FALSE) {
            $pos = stripos($text, ' ' . $queryStr);
        }
        if ($pos === FALSE) {
            $pos = stripos($text, $queryStr);
        }

        return $pos;
    }

    /**
     * extracts summary with highlighted search word from source text
     *
     * @param string   $text
     * @param string[] $queryTokens
     *
     * @return string
     */
    protected function getHighlightedSummary($text, $queryTokens)
    {
        $pos = FALSE;
        $tokenInUse = $queryTokens[0];

        foreach ($queryTokens as $queryStr) {
            $tokenInUse = $queryStr;
            $pos = $this->findPosInSummary($text, $queryStr);

            if ($pos !== FALSE) {
                break;
            }
        }

        if ($pos !== FALSE) {
            $start = $pos - 100;

            if ($start < 0) {
                $start = 0;
            }

            $summary = substr($text, $start, self::SUMMARY_LENGTH + strlen($tokenInUse));
            $summary = trim($summary);

            $tokens = explode(' ', $summary);

            if (strtolower($tokens[0]) != strtolower($tokenInUse)) {
                $tokens = array_slice($tokens, 1, -1);
            } else {
                $tokens = array_slice($tokens, 0, -1);
            }

            $trimmedSummary = implode(' ', $tokens);

            foreach ($queryTokens as $queryStr) {
                $trimmedSummary = preg_replace('@([ \'")(-:.,;])(' . $queryStr . ')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2\\3</span>", $trimmedSummary);
                $trimmedSummary = preg_replace('@^(' . $queryStr . ')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSummary);
                $trimmedSummary = preg_replace('@([ \'")(-:.,;])(' . $queryStr . ')$@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSummary);
            }

            return empty($trimmedSummary) ? FALSE : $trimmedSummary;
        }

        return FALSE;
    }
}
