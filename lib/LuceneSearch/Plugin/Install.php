<?php

namespace LuceneSearch\Plugin;

use LuceneSearch\Model\Configuration;
use Pimcore\Model\Property;

class Install
{
    /**
     * @return bool
     */
    public function installConfigFile()
    {
        $configFile = \Pimcore\Config::locateConfigFile('lucenesearch_configurations');

        //If file already exists, return!
        if (is_file($configFile . '.php')) {
            return TRUE;
        }

        if (is_file($configFile . '.BACKUP')) {
            rename($configFile . '.BACKUP', $configFile . '.php');

            return TRUE;
        }

        Configuration::set('frontend.index', 'website/var/search/frontend/index/');

        Configuration::set('frontend.ignoreLanguage', FALSE);
        Configuration::set('frontend.ignoreCountry', TRUE);
        Configuration::set('frontend.ignoreRestriction', TRUE);

        Configuration::set('frontend.restriction.class', '');
        Configuration::set('frontend.restriction.method', '');

        Configuration::set('frontend.auth.useAuth', FALSE);
        Configuration::set('frontend.auth.username', '');
        Configuration::set('frontend.auth.password', '');

        Configuration::set('frontend.fuzzySearch', FALSE);
        Configuration::set('frontend.enabled', FALSE);
        Configuration::set('frontend.urls', []);
        Configuration::set('frontend.validLinkRegexes', []);
        Configuration::set('frontend.invalidLinkRegexesEditable', []);
        Configuration::set('frontend.invalidLinkRegexes',
            '@.*\.(js|JS|gif|GIF|jpg|JPG|png|PNG|ico|ICO|eps|jpeg|JPEG|bmp|BMP|css|CSS|sit|wmf|zip|ppt|mpg|xls|gz|rpm|tgz|mov|MOV|exe|mp3|MP3|kmz|gpx|kml|swf|SWF)$@');
        Configuration::set('frontend.categories', []);
        Configuration::set('frontend.allowedSchemes', ['http']);
        Configuration::set('frontend.ownHostOnly', FALSE);
        Configuration::set('frontend.crawler.maxLinkDepth', 15);
        Configuration::set('frontend.crawler.maxDownloadLimit', 0);
        Configuration::set('frontend.crawler.contentStartIndicator', '');
        Configuration::set('frontend.crawler.contentEndIndicator', '');
        Configuration::set('frontend.crawler.contentExcludeStartIndicator', '');
        Configuration::set('frontend.crawler.contentExcludeEndIndicator', '');
        Configuration::set('frontend.sitemap.render', FALSE);

        Configuration::set('frontend.view.maxPerPage', 10);
        Configuration::set('frontend.view.maxSuggestions', 10);

        Configuration::set('boost.documents', 1);
        Configuration::set('boost.assets', 1);

        Configuration::setCoreSettings(

            [
                'forceStart' => FALSE,
                'forceStop'  => FALSE,
                'running'    => FALSE,
                'started'    => FALSE,
                'finished'   => FALSE
            ]
        );

        return TRUE;
    }

    /**
     *
     */
    public function installProperties()
    {
        $defProperty = Property\Predefined::getByKey('assigned_language');

        if (!$defProperty instanceof Property\Predefined) {
            $languages = \Pimcore\Tool::getValidLanguages();

            $data = 'all,';

            foreach ($languages as $language) {
                $data .= $language . ',';
            }

            $data = rtrim($data, ',');

            $property = new Property\Predefined();
            $property->setType('select');
            $property->setName('Assigned Language');
            $property->setKey('assigned_language');
            $property->setDescription('set a specific language which lucene search should respect while crawling.');
            $property->setCtype('asset');
            $property->setData('all');
            $property->setConfig($data);
            $property->setInheritable(FALSE);
            $property->save();
        }
    }

    /**
     * @return bool
     */
    public function createDirectories()
    {
        //create folder for search in website
        if (!is_dir((PIMCORE_WEBSITE_PATH . '/var/search'))) {
            mkdir(PIMCORE_WEBSITE_PATH . '/var/search', 0755, TRUE);
        }

        $index = PIMCORE_DOCUMENT_ROOT . '/' . Configuration::get('frontend.index');

        if (!empty($index) && !is_dir($index)) {
            mkdir($index, 0755, TRUE);
            chmod($index, 0755);
        }

        return TRUE;
    }

    /**
     * @return bool
     */
    public function createRedirect()
    {
        $redirects = new \Pimcore\Model\Redirect\Listing();

        $redirects->setCondition('source = ?', '/\/sitemap.xml/');
        $redirects->load();

        foreach ($redirects->getRedirects() as $redirect) {
            $redirect->delete();
        }

        //add redirect for sitemap.xml
        $redirect = new \Pimcore\Model\Redirect();
        $redirect->setValues(['source' => '/\/sitemap.xml/', 'target' => '/plugin/LuceneSearch/frontend/sitemap', 'statusCode' => 301, 'priority' => 10]);
        $redirect->save();

        return TRUE;
    }

    public function removeConfig()
    {
        $configFile = \Pimcore\Config::locateConfigFile('lucenesearch_configurations');

        if (is_file($configFile . '.php')) {
            rename($configFile, $configFile . '.BACKUP');
        }
    }

}