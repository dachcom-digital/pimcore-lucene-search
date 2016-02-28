<?php

namespace LuceneSearch\Tool;

class Tool {

    public static function getCrawlerQuery() {

        $queryFile = PIMCORE_PLUGINS_PATH . '/LuceneSearch/db/query.sql';

        return file_get_contents( $queryFile );

    }

}
