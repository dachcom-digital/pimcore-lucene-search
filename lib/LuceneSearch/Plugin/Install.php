<?php

namespace LuceneSearch\Plugin;

use LuceneSearch\Model\Configuration;

class Install {

    public function __construct()
    {
    }

    public function installConfigFile()
    {
        Configuration::set('frontend.index', 'website/var/search/frontend/index/');
        Configuration::set('frontend.ignoreLanguage', FALSE);
        Configuration::set('frontend.ignoreCountry', TRUE);
        Configuration::set('frontend.ignoreRestriction', TRUE);
        Configuration::set('frontend.fuzzySearch', FALSE);
        Configuration::set('frontend.enabled', FALSE);
        Configuration::set('frontend.urls', array());
        Configuration::set('frontend.validLinkRegexes', array());
        Configuration::set('frontend.invalidLinkRegexesEditable', array());
        Configuration::set('frontend.invalidLinkRegexes', '@.*\.(js|JS|gif|GIF|jpg|JPG|png|PNG|ico|ICO|eps|jpeg|JPEG|bmp|BMP|css|CSS|sit|wmf|zip|ppt|mpg|xls|gz|rpm|tgz|mov|MOV|exe|mp3|MP3|kmz|gpx|kml|swf|SWF)$@');
        Configuration::set('frontend.categories', array());
        Configuration::set('frontend.allowedSchemes', array('http'));
        Configuration::set('frontend.ownHostOnly', FALSE);
        Configuration::set('frontend.crawler.maxLinkDepth', 15);
        Configuration::set('frontend.crawler.maxDownloadLimit', 0);
        Configuration::set('frontend.crawler.contentStartIndicator', '');
        Configuration::set('frontend.crawler.contentEndIndicator', '');
        Configuration::set('frontend.crawler.forceStart', FALSE);
        Configuration::set('frontend.crawler.running', FALSE);
        Configuration::set('frontend.crawler.started', FALSE);
        Configuration::set('frontend.crawler.finished', FALSE);
        Configuration::set('frontend.crawler.forceStop', FALSE);
        Configuration::set('frontend.crawler.forceStopInitiated', FALSE);

        return TRUE;
    }

    public function createDirectories()
    {
        //create folder for search in website
        if( !is_dir( (PIMCORE_WEBSITE_PATH . '/var/search' ) ) )
        {
            mkdir(PIMCORE_WEBSITE_PATH . '/var/search', 0755, true);
        }

        $index = PIMCORE_DOCUMENT_ROOT . '/' . Configuration::get('frontend.index');

        if (!empty($index) and !is_dir($index))
        {
            mkdir($index, 0755, true);
            chmod($index, 0755);
        }

        return TRUE;

    }

    public function createRedirect()
    {
        //add redirect for sitemap.xml
        $redirect = new \Pimcore\Model\Redirect();
        $redirect->setValues(array('source' => '/\/sitemap.xml/', 'target' => '/plugin/LuceneSearch/frontend/sitemap', 'statusCode' => 301, 'priority' => 10));
        $redirect->save();

        return TRUE;
    }

    public function removeConfig()
    {
        $configFile = \Pimcore\Config::locateConfigFile('lucenesearch_configurations.php');

        if (is_file( $configFile ))
        {
            rename($configFile, $configFile  . '.BACKUP');
        }
    }

}