<?php

namespace LuceneSearch;

use Pimcore\API\Plugin as PluginLib;

use LuceneSearch\Plugin\Install;
use LuceneSearch\Model\Configuration;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface {

    /**
     * @var \Zend_Translate
     */
    protected static $_translate;

    public function __construct($jsPaths = NULL, $cssPaths = NULL, $alternateIndexDir = NULL)
    {
        define('LUCENESEARCH__PLUGIN_CONFIG', PIMCORE_PLUGINS_PATH . '/LuceneSearch/plugin.xml');

        parent::__construct($jsPaths, $cssPaths);

    }

    public function init()
    {
        parent::init();

        \Pimcore::getEventManager()->attach('system.maintenance', array($this, 'maintenanceJob'));
        \Pimcore::getEventManager()->attach('system.console.init', function (\Zend_EventManager_Event $e) {

            $application = $e->getTarget();
            $application->add(new \LuceneSearch\Console\Command\FrontendCrawlCommand());

        });
    }

    /**
     * @static
     * @return string
     */
    public static function getPluginState()
    {
        if (self::isInstalled())
        {
            $message = '';

            if (Configuration::get('frontend.enabled'))
            {
                if(Configuration::getCoreSetting('running'))
                {
                    $message .= self::getTranslate()->_('lucenesearch_frontend_crawler_running') . '. ';
                } else {
                    $message .= self::getTranslate()->_('lucenesearch_frontend_crawler_not_running') . '. ';
                }

                $started = 'never';
                $finished = 'never';

                if( !is_bool( Configuration::getCoreSetting('started')))
                {
                    $started = date('d.m.Y H:i', (double)Configuration::getCoreSetting('started'));
                }

                if( !is_bool( Configuration::getCoreSetting('finished')))
                {
                    $finished = date('d.m.Y H:i', (double) Configuration::getCoreSetting('finished'));
                }

                $message .= self::getTranslate()->_('lucenesearch_frontend_crawler_last_started') . ': ' . $started . '. ';
                $message .= self::getTranslate()->_('lucenesearch_frontend_crawler_last_finished') . ': ' . $finished . '. ';

                if (!self::frontendConfigComplete())
                {
                    $message .= 'ERROR:' . self::getTranslate()->_('lucenesearch_frontend_config_incomplete');
                }
                else
                {
                    if (Configuration::getCoreSetting('forceStart'))
                    {
                        $message .= self::getTranslate()->_('lucenesearch_frontend_crawler') . ': ';
                        $message .= self::getTranslate()->_('lucenesearch_frontend_crawler_start_on_next_maintenance');
                    }
                }

            }

            return $message;
        }

        return FALSE;
    }

    /**
     *  indicates whether this plugins is currently installed
     * @return boolean $isInstalled
     */
    public static function isInstalled()
    {
        $indexDir = self::getFrontendSearchIndex();
        return (!is_null($indexDir) && is_dir($indexDir));
    }

    /**
     * @return boolean $readyForInstall
     */
    public static function readyForInstall()
    {
        return TRUE;
    }

    /**
     *
     * @param string $language
     * @return string path to the translation file relative to plugin directory
     */
    public static function getTranslationFile($language)
    {
        if (is_file(PIMCORE_PLUGINS_PATH . '/LuceneSearch/static/texts/' . $language . '.csv'))
        {
            return '/LuceneSearch/static/texts/' . $language . '.csv';
        }
        else
        {
            return '/LuceneSearch/static/texts/en.csv';
        }
    }


    /**
     * Reads the location for the frontend search index from search config file and returns path if exists
     *
     * @return string $path
     */
    public static function getFrontendSearchIndex()
    {
        $searchConf = Configuration::get('frontend.index');

        if( is_null( $searchConf ) )
        {
            return NULL;
        }

        if (is_dir($searchConf))
        {
            return $searchConf;
        }
        else if (is_dir(PIMCORE_DOCUMENT_ROOT . '/' . $searchConf))
        {
            return PIMCORE_DOCUMENT_ROOT . '/' . $searchConf;
        }

        return NULL;

    }

    /**
     *  install function
     * @return string $message statusmessage to display in frontend
     */
    public static function install()
    {
        try
        {
            $install = new Install();
            $install->installConfigFile();
            $install->installProperties();
            $install->createDirectories();
            $install->createRedirect();
        }
        catch (\Exception $e)
        {
            \Pimcore\Logger::crit($e);
            return self::getTranslate()->_('lucenesearch_install_failed');
        }

        if (Configuration::get('frontend.enabled') )
        {
            self::forceCrawlerStartOnNextMaintenance('frontend');
        }

        return self::getTranslate()->_('lucenesearch_install_successfully');

    }

    /**
     * uninstall function
     * @return string $message status message to display in frontend
     */
    public static function uninstall()
    {
        $install = new Install();

        $install->removeConfig();

        $index = self::getFrontendSearchIndex();
        $success = FALSE;

        if (!empty($index))
        {
            $success = recursiveDelete($index);
        }

        if ($success)
        {
            return self::getTranslate()->_('lucenesearch_uninstalled_successfully');
        }
        else
        {
            return self::getTranslate()->_('lucenesearch_uninstall_failed');
        }

    }

    /**
     * @param String $lang
     * @return \Zend_Translate
     */
    public static function getTranslate($lang = NULL)
    {
        if (self::$_translate instanceof \Zend_Translate) {
            return self::$_translate;
        }
        if(is_null($lang)) {
            try {
                $lang = \Zend_Registry::get('Zend_Locale')->getLanguage();
            } catch (\Exception $e) {
                $lang = 'en';
            }
        }

        self::$_translate = new \Zend_Translate(
            'csv',
            PIMCORE_PLUGINS_PATH .self::getTranslationFile($lang),
            $lang,
            array('delimiter' => ',')
        );
        return self::$_translate;
    }

    /**
     * @return bool
     */
    public static function frontendCrawlerRunning()
    {
        //do not use the configuration here!
        if (is_file( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp' ) )
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * @static
     * @return bool
     */
    public static function frontendCrawlerStopLocked()
    {
        if (Configuration::getCoreSetting('forceStop'))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * @return bool
     */
    public static function frontendCrawlerScheduledForStart()
    {
        if (Configuration::getCoreSetting('forceStart'))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * @static
     * @return boolean
     */
    public static function frontendConfigComplete()
    {
        $frontEndUrls = Configuration::get('frontend.urls');
        $validLinkRegexes = Configuration::get('frontend.validLinkRegexes');

        if (!empty($frontEndUrls) && !empty($validLinkRegexes))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * @static
     * @return bool
     */
    public static function stopFrontendCrawler()
    {
        return Tool\Executer::stopCrawler();
    }

    public function frontendCrawl()
    {
        if (self::frontendConfigComplete())
        {
            Tool\Executer::runCrawler();
            Tool\Executer::generateSitemap();
        }
        else
        {
            \Pimcore\Logger::debug('LuceneSearch_Plugin: Did not start frontend crawler, because config incomplete');
        }
    }

    /**
     * @param string $crawler frontend | backend
     * @return void
     */
    public static function forceCrawlerStartOnNextMaintenance( $crawler )
    {
        Configuration::setCoreSetting('forceStart', TRUE);
        \Pimcore\Logger::debug('LuceneSearch: forced to starting crawl');
    }

    /**
     * Hook called when maintenance script is called
     */
    public function maintenanceJob()
    {
        if (self::isInstalled())
        {
            $currentHour = date('H', time());

            //Frontend recrawl
            $running = self::frontendCrawlerRunning();

            $enabled = Configuration::get('frontend.enabled');
            $lastStarted = Configuration::getCoreSetting('started');
            $lastFinished = Configuration::getCoreSetting('finished');
            $forceStart = Configuration::getCoreSetting('forceStart');
            $aDayAgo = time() - (24 * 60 * 60);

            /**
             * + If Crawler is enabled
             * + If Crawler is not running
             * + If last start of Crawler is initial or a day ago
             * + If it's between 1 + 3 o clock in the night
             * + OR if its force
             * => RUN
             */
            if ($enabled && !$running && ( ( (is_bool($lastStarted) || $lastStarted <= $aDayAgo) && $currentHour > 1 && $currentHour < 3) || $forceStart))
            {
                \Pimcore\Logger::debug('starting frontend recrawl...');
                $this->frontendCrawl();

                /**
                 * + If Crawler is Running
                 * + If last stop of crawler is before last start
                 * + If last start is older than one day
                 * => We have some errors: EXIT CRAWLING!
                 */
            } else if( $running && $lastFinished < $lastStarted && $lastStarted <= $aDayAgo)
            {
                \Pimcore\Logger::error('LuceneSearch: There seems to be a problem with the search crawler! Trying to stop it.');
                $this->stopFrontendCrawler();
            }

        }
        else
        {
            \Pimcore\Logger::debug('LuceneSearch: Plugin is not installed - no maintenance to do for this plugin.');
        }
    }

    /**
     *
     * @param string $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     * @return Array $hits
     */
    public static function wildcardFindTerms($queryStr, $index)
    {
        if ($index != NULL)
        {
            $pattern = new \Zend_Search_Lucene_Index_Term($queryStr . '*');
            $userQuery = new \Zend_Search_Lucene_Search_Query_Wildcard($pattern);
            \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(2);
            $index->find($userQuery);
            $terms = $userQuery->getQueryTerms();

            return $terms;
        }
    }

    /**
     *  finds similar terms
     * @param string $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     * @param integer $prefixLength optionally specify prefix length, default 0
     * @param float $similarity optionally specify similarity, default 0.5
     * @return string[] $similarSearchTerms
     */
    public static function fuzzyFindTerms($queryStr, $index, $prefixLength = 0, $similarity = 0.5)
    {
        if ($index != NULL)
        {
            \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength($prefixLength);
            $term = new \Zend_Search_Lucene_Index_Term($queryStr);
            $fuzzyQuery = new \Zend_Search_Lucene_Search_Query_Fuzzy($term, $similarity);

            $hits = $index->find($fuzzyQuery);
            $terms = $fuzzyQuery->getQueryTerms();

            return $terms;
        }
    }
}