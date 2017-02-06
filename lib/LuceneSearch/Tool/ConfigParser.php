<?php

namespace LuceneSearch\Tool;

class ConfigParser
{
    /**
     * @param      $values
     * @param      $prefix
     * @param bool $encode
     *
     * @return array|string
     */
    public static function parseValues($values, $prefix, $encode = FALSE)
    {
        $urlArray = [];

        foreach ($values as $u) {
            if (!empty($u)) {
                $urlArray[] = [$prefix => $u];
            }
        }

        if ($encode == TRUE) {
            return \Zend_Json::encode($urlArray);
        }

        return $urlArray;
    }

}
