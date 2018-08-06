<?php

namespace LuceneSearchBundle\Tool;

class CrawlerState
{
    /**
     * @todo: add symfony context
     * Check if current request is a LuceneSearch crawler.
     */
    public function isLuceneSearchCrawler()
    {
        $isLuceneSearch = false;
        $headers = $this->getHeaders();

        if (empty($headers)) {
            return $isLuceneSearch;
        }

        foreach ($headers as $name => $value) {
            if ($name === 'Lucene-Search') {
                $pluginVersion = $value;
                $isLuceneSearch = true;
                break;
            }
        }

        return $isLuceneSearch;
    }

    /**
     * @return array|false
     */
    private function getHeaders()
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