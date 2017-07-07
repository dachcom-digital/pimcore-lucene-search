<?php

namespace LuceneSearch\Tool;

class Request
{
    /**
     * Check if current request is a LuceneSearch crawler.
     * @fixme Because $front->getResponse()->getHeaders() does only return true SERVER vars,
     * custom injected elements like "lucene-search" won't appear. So maybe we do have some issues with servers running with NGINX or IIS
     * @see   https://github.com/zendframework/zend-xmlrpc/blob/master/src/Request/Http.php#L81
     */
    public static function isLuceneSearchCrawler()
    {
        $isLuceneSearch = FALSE;
        $headers = self::getHeaders();

        if (empty($headers)) {
            return $isLuceneSearch;
        }

        foreach ($headers as $name => $value) {
            if ($name === 'Lucene-Search') {
                $pluginVersion = $value;
                $isLuceneSearch = TRUE;
                break;
            }
        }

        return $isLuceneSearch;
    }

    private static function getHeaders()
    {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        } else {
            return getallheaders();
        }
    }
}