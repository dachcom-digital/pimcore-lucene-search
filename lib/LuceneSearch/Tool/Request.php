<?php

namespace LuceneSearch\Tool;

class Request {

    /**
     * Check if current request is a LuceneSearch crawler.
     *
     * @fixme Because $front->getResponse()->getHeaders() does only return true SERVER vars,
     * custom injected elements like "lucene-search" won't appear. So maybe we do have some issues with servers running with NGINX or IIS
     *
     * @see https://github.com/zendframework/zend-xmlrpc/blob/master/src/Request/Http.php#L81
     *
     */
    public static function isLuceneSearchCrawler()
    {
        $isLuceneSearch = FALSE;

        if ( !function_exists('getallheaders') )
        {
            return $isLuceneSearch;
        }

        $headers = getallheaders();

        if( empty( $headers ) )
        {
            return $isLuceneSearch;
        }

        foreach ($headers as $name => $value)
        {
            if ($name === 'Lucene-Search')
            {
                $pluginVersion = $value;
                $isLuceneSearch = TRUE;
                break;

            }
        }

        return $isLuceneSearch;

    }
}