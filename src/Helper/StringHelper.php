<?php

namespace LuceneSearchBundle\Helper;

class StringHelper
{
    /**
     * remove evil stuff from request string
     *
     * @param  string $requestString
     *
     * @return string
     */
    public function cleanRequestString($requestString)
    {
        $queryFromRequest = strip_tags(urldecode($requestString));
        $queryFromRequest = str_replace(['<', '>', '"', "'", '&'], '', $queryFromRequest);

        return $queryFromRequest;
    }
}