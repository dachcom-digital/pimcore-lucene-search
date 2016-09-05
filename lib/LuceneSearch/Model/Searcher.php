<?php

namespace LuceneSearch\Model;

class Searcher {

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    public function __construct(){
        $this->db = \Pimcore\Db::get();

    }

    /**
     * @param $content
     * @param $queryStr
     * @param $trim
     *
     * @return mixed|string
     */
    public function getSummaryForUrl($content, $queryStr, $trim = TRUE)
    {
        $summary =  $this->getHighlightedSummary($content, array($queryStr), $trim);

        if( empty($summary) )
        {
            $tokens = explode(' ', $queryStr);

            if( count($tokens) > 1 )
            {
                foreach($tokens as $token)
                {
                    $summary = $this->getHighlightedSummary($content, $tokens, $trim);

                    if( !empty($summary) )
                    {
                        break;
                    }
                }
            }
        }

        return $summary;

    }

    /**
     * finds the query strings position in the text
     * @param  string $text
     * @param  string $queryStr
     * @return int
     */
    protected function findPosInSummary($text,$queryStr)
    {
        $pos = stripos($text, ' ' . $queryStr . ' ');
        if ($pos === FALSE)
        {
            $pos = stripos($text, '"' . $queryStr . '"');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, '"' . $queryStr . '"');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, ' ' . $queryStr . '-');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, '-' . $queryStr . ' ');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, $queryStr . ' ');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, ' ' . $queryStr);
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, $queryStr);
        }

        return $pos;
    }

    /**
     * extracts summary with highlighted search word from source text
     * @param string $text
     * @param string[] $queryTokens
     * @param bool $trim
     * @return string
    */
    protected function getHighlightedSummary($text, $queryTokens, $trim = TRUE)
    {
        //remove additional whitespaces
        $text = preg_replace('/[\s]+/', ' ', $text);

        $pos = FALSE;
        $tokenInUse = $queryTokens[0];

        foreach($queryTokens as $queryStr)
        {
            $tokenInUse = $queryStr;
            $pos = $this->findPosInSummary($text,$queryStr);

            if($pos !== FALSE)
            {
                break;
            }
        }

        if ($pos !== FALSE)
        {
            $trimmedSummary = $text;

            if( $trim !== FALSE )
            {
                $start = $pos - 100;

                if ($start < 0)
                {
                    $start = 0;
                }

                $summary = substr($text, $start, 255 + strlen($tokenInUse) );
                $summary = trim($summary);

                $tokens = explode(' ', $summary);

                if (strtolower($tokens[0]) != strtolower($tokenInUse))
                {
                    $tokens = array_slice($tokens, 1, - 1);
                }
                else
                {
                    $tokens = array_slice($tokens,0, - 1);
                }

                $trimmedSummary = implode(' ', $tokens);
            }

            foreach($queryTokens as $queryStr)
            {
                $trimmedSummary = preg_replace('@([ \'")(-:.,;])('.$queryStr.')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2\\3</span>", $trimmedSummary);
                $trimmedSummary = preg_replace('@^('.$queryStr.')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSummary);
                $trimmedSummary = preg_replace('@([ \'")(-:.,;])('.$queryStr.')$@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSummary);
            }

            return $trimmedSummary;
        }
        else
        {
            return $trim === FALSE ? $text : substr($text, 0, 255);
        }
    }
}
