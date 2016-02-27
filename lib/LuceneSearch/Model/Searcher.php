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
     * @param  $url
     * @param  $queryStr
     * @return string
     */
    public function getSumaryForUrl($url,$queryStr)
    {

        $query = "SELECT content from plugin_lucenesearch_contents where id = ? ";
        $params = array(md5($url));
        $data = $this->db->fetchRow($query,$params);
        $summary = null;

        if($data)
        {
            $summary =  $this->getHighlightedSumary($data['content'],array($queryStr));

            if(empty($summary))
            {
                $tokens = explode(" ",$queryStr);
                if(count($tokens)>1)
                {
                    foreach($tokens as $token)
                    {
                        $summary = $this->getHighlightedSumary($data['content'],$tokens);
                        if(!empty($summary))
                        {
                            break;
                        }
                    }
                }
            }
        }

        return $summary;

    }

    /**
     * finds the query strings postion in the text
     * @param  string $text
     * @param  string $queryStr
     * @return int
     */
    protected function findPosInSumary($text,$queryStr)
    {
        $pos = stripos($text, " " . $queryStr . " ");
        if ($pos === FALSE)
        {
            $pos = stripos($text, '"' . $queryStr . '"');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, "'" . $queryStr . "'");
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, " " . $queryStr . '-');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, '-' . $queryStr . ' ');
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, $queryStr . " ");
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, " " . $queryStr);
        }
        if ($pos === FALSE)
        {
            $pos = stripos($text, $queryStr);
        }

        return $pos;
    }

    /*
     * extracts sumary with highlighted search word from source text
     * @param string $text
     * @param string[] $queryStr
     * @return string
    */
    protected function getHighlightedSumary($text,$queryTokens)
    {
        //remove additional whitespaces
        $text = preg_replace("/[\s]+/", " ", $text);

        $pos = FALSE;
        $tokenInUse = $queryTokens[0];

        foreach($queryTokens as $queryStr)
        {
            $tokenInUse = $queryStr;
                $pos= $this->findPosInSumary($text,$queryStr);
            if($pos !== FALSE){
                break;
            }
        }

        if ($pos !== FALSE)
        {
            $start = $pos - 100;

            if ($start < 0)
            {
                $start = 0;
            }

            $sumary = substr($text, $start, 200 + strlen($tokenInUse));
            $sumary = trim($sumary);

            $tokens = explode(" ",$sumary);

            if (strtolower($tokens[0]) != strtolower($tokenInUse))
            {
                $tokens = array_slice($tokens, 1, - 1);
            }
            else
            {
                $tokens = array_slice($tokens,0, - 1);
            }

            $trimmedSumary = implode(" ", $tokens);

            foreach($queryTokens as $queryStr)
            {
                $trimmedSumary = preg_replace('@([ \'")(-:.,;])('.$queryStr.')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2\\3</span>", $trimmedSumary);
                $trimmedSumary = preg_replace('@^('.$queryStr.')([ \'")(-:.,;])@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSumary);
                $trimmedSumary = preg_replace('@([ \'")(-:.,;])('.$queryStr.')$@si', " <span class=\"highlight\">\\1\\2</span>", $trimmedSumary);
            }
            
            return $trimmedSumary;
        }
    }
}
