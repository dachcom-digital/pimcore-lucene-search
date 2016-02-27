<?php

namespace LuceneSearch\Tool;

class ConfigParser {

    public static function parseValues($values, $prefix, $encode = FALSE)
    {
        $urlArray = array();

        foreach ($values as $u)
        {
            if (!empty($u)) {
                $urlArray[] = array($prefix => $u);
            }
        }

        if( $encode == TRUE )
        {
            return \Zend_Json::encode($urlArray);
        }

        return $urlArray;

    }

}
