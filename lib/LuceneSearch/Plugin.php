<?php

namespace LuceneSearch;

use Pimcore\API\Plugin as PluginLib;

use LuceneSearch\Model\Crawler;
use LuceneSearch\Plugin\Install;
use LuceneSearch\Model\Configuration;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface {

    /**
     * @var \Zend_Translate
     */
    protected static $_translate;

    public function __construct($jsPaths = null, $cssPaths = null, $alternateIndexDir = null)
    {
        parent::__construct($jsPaths, $cssPaths);

    }

    public function init()
    {
        parent::init();

        define('LUCENESEARCH_CONFIGURATION_FILE', PIMCORE_CONFIGURATION_DIRECTORY . '/lucenesearch_configuration.php');

        \Pimcore::getEventManager()->attach("system.maintenance", array($this, 'maintenance'));

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
            $message = "";

            if (Configuration::get("frontend.enabled"))
            {
                if(Configuration::get("frontend.crawler.running"))
                {
                    $message .= self::getTranslate()->_("lucenesearch_frontend_crawler_running") . ". ";
                } else {
                    $message .= self::getTranslate()->_("lucenesearch_frontend_crawler_not_running") . ". ";
                }

                $started = 'never';
                $finished = 'never';

                if( !is_bool( Configuration::get("frontend.crawler.started")))
                {
                    $started = date('d.m.Y H:i', (double)Configuration::get("frontend.crawler.started"));
                }

                if( !is_bool( Configuration::get("frontend.crawler.finished")))
                {
                    $finished = date('d.m.Y H:i', (double) Configuration::get("frontend.crawler.finished"));
                }

                $message .= self::getTranslate()->_("lucenesearch_frontend_crawler_last_started") . ": " . $started . ". ";
                $message .= self::getTranslate()->_("lucenesearch_frontend_crawler_last_finished") . ": " . $finished . ". ";

                if (!self::frontendConfigComplete())
                {
                    $message .= " -------------------------------------------- ";
                    $message .= 'ERROR:' . self::getTranslate()->_('lucenesearch_frontend_config_incomplete');
                }
                else
                {
                    if (Configuration::get("frontend.crawler.forceStart"))
                    {
                        $message .= "------------------------------------------- ";
                        $message .= self::getTranslate()->_("lucenesearch_frontend_crawler") . ": ";
                        $message .= self::getTranslate()->_("searchPhp_rrontend_crawler_start_on_next_maintenance");
                    }
                }

                $message .= " -------------------------------------------- ";
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
        return true;
    }

    /**
     *
     * @param string $language
     * @return string path to the translation file relative to plugin direcory
     */
    public static function getTranslationFile($language)
    {
        if (is_file(PIMCORE_PLUGINS_PATH . "/LuceneSearch/static/texts/" . $language . ".csv"))
        {
            return PIMCORE_PLUGINS_PATH . "/LuceneSearch/static/texts/" . $language . ".csv";
        }
        else
        {
            return PIMCORE_PLUGINS_PATH . "/LuceneSearch/static/texts/en.csv";
        }
    }


    /**
     * Reads the location for the frontend search index from search config file and returns path if exists
     *
     * @return string $path
     */
    public static function getFrontendSearchIndex()
    {
        $searchConf = Configuration::get("frontend.index");

        if( is_null( $searchConf ) )
        {
            return null;
        }

        if (is_dir($searchConf))
        {
            return $searchConf;
        }
        else if (is_dir(PIMCORE_DOCUMENT_ROOT . "/" . $searchConf))
        {
            return PIMCORE_DOCUMENT_ROOT . "/" . $searchConf;
        }

        return null;

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
            $install->createDirectories();
            $install->createRedirect();

        }
        catch (\Exception $e)
        {
            \Logger::crit($e);
            return self::getTranslate()->_('lucenesearch_install_failed');
        }

        if (Configuration::get("frontend.enabled") )
        {
            self::forceCrawlerStartOnNextMaintenance("frontend");
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
        $success = false;

        if (!empty($index))
        {
            $success = recursiveDelete($index);
        }

        if ($success)
        {
            return self::getTranslate()->_("uninstalled_successfully");
        }
        else
        {
            return self::getTranslate()->_("uninstall_failed");
        }

    }

    /**
     * @return \Zend_Translate
     */
    public static function getTranslate($lang = null)
    {
        if (self::$_translate instanceof \Zend_Translate) {
            return self::$_translate;
        }
        if(is_null($lang)) {
            try {
                $lang = \Zend_Registry::get('Zend_Locale')->getLanguage();
            } catch (Exception $e) {
                $lang = 'en';
            }
        }

        self::$_translate = new \Zend_Translate(
            'csv',
            self::getTranslationFile($lang),
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
        if (Configuration::get("frontend.crawler.running")) return true;
        else return false;
    }

    /**
     * @static
     * @return bool
     */
    public static function frontendCrawlerStopLocked()
    {
        if (Configuration::get("frontend.crawler.forceStop")) return true;
        else return false;
    }

    /**
     * @return bool
     */
    public static function frontendCrawlerScheduledForStart()
    {
        if (Configuration::get("frontend.crawler.forceStart")) return true;
        else return false;
    }

    /**
     * @static
     * @return boolean
     */
    public static function frontendConfigComplete()
    {
        $frontEndUrls = Configuration::get("frontend.urls");
        $validLinkRegexes = Configuration::get("frontend.validLinkRegexes");

        if (!empty($frontEndUrls) && !empty($validLinkRegexes))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @static
     * @param bool $playNice
     * @return bool
     */
    public static function stopFrontendCrawler($playNice = true, $isFrontendCall = false)
    {
        \Logger::debug("LuceneSearch: forcing frontend crawler stop, play nice: [ $playNice ]");
        self::setStopLock("frontend", true);

        //just to make sure nothing else starts the crawler right now
        self::setCrawlerState("frontend", "started", false);

        $maxThreads = Configuration::get("frontend.crawler.maxThreads");

        $db = \Pimcore\Db::get();
        $db->query("DROP TABLE IF EXISTS `plugin_lucenesearch_frontend_crawler_todo`;");
        $db->query("DROP TABLE IF EXISTS `plugin_lucenesearch_indexer_todo`;");

        \Logger::debug("LuceneSearch: forcing frontend crawler stop - dropped tables");

        $pidFiles = array("maintainance_crawler-indexer");
        for ($i = 1; $i <= $maxThreads; $i++) {
            $pidFiles[] = "maintainance_crawler-" . $i;
        }

        $counter = 1;
        while ($pidFiles and count($pidFiles) > 0 and $counter < 10) {
            sort($pidFiles);
            for ($i = 0; $i < count($pidFiles); $i++) {
                $file = PIMCORE_SYSTEM_TEMP_DIRECTORY . "/" . $pidFiles[$i];
                if (!is_file($file)) {
                    unset($pidFiles[$i]);
                }
            }
            sleep(1);
            $counter++;
        }

        if (!$playNice) {

            if (is_file(PIMCORE_SYSTEM_TEMP_DIRECTORY . "/maintainance_LuceneSearch_Plugin.pid" and $isFrontendCall)) {
                $pidFiles[] = "maintainance_LuceneSearch_Plugin.pid";
            }

            //delete pid files of all  processes
            for ($i = 0; $i < count($pidFiles); $i++) {
                $file = PIMCORE_SYSTEM_TEMP_DIRECTORY . "/" . $pidFiles[$i];
                if (is_file($file) and !unlink($file))
                {
                    \Logger::emerg("LuceneSearch_Plugin: : Trying to force stop crawler, but cannot delete [ $file ]");
                }

                if (!is_file($file)) {
                    unset($pidFiles[$i]);
                }
            }
        }

        self::setStopLock("frontend", false);

        if (!$pidFiles or count($pidFiles) == 0)
        {
            self::setCrawlerState("frontend", "finished", false);
            return true;
        }

        return false;

    }

    public function frontendCrawl()
    {
        if (self::frontendConfigComplete())
        {
            ini_set('memory_limit', '2048M');
            ini_set("max_execution_time", "-1");

            $indexDir = self::getFrontendSearchIndex();

            if ($indexDir)
            {
                //TODO nix specific
                exec("rm -Rf " . str_replace("/index/", "/tmpindex", $indexDir));
                \Logger::debug("rm -Rf " . str_replace("/index/", "/tmpindex", $indexDir));

                try
                {
                    $urls = Configuration::get("frontend.urls");
                    $validLinkRegexes = Configuration::get("frontend.validLinkRegexes");

                    $invalidLinkRegexesSystem = Configuration::get("frontend.invalidLinkRegexes");
                    $invalidLinkRegexesEditable = Configuration::get("frontend.invalidLinkRegexesEditable");

                    if (!empty($invalidLinkRegexesEditable) and !empty($invalidLinkRegexesSystem))
                    {
                        $invalidLinkRegexes = array_merge($invalidLinkRegexesEditable, array($invalidLinkRegexesSystem));
                    }
                    else if (!empty($invalidLinkRegexesEditable))
                    {
                        $invalidLinkRegexes = $invalidLinkRegexesEditable;
                    }
                    else if (!empty($invalidLinkRegexesSystem))
                    {
                        $invalidLinkRegexes = array($invalidLinkRegexesSystem);
                    }
                    else
                    {
                        $invalidLinkRegexes = array();
                    }

                    self::setCrawlerState("frontend", "started", true);

                    $maxLinkDepth = Configuration::get("frontend.crawler.maxLinkDepth");

                    if (is_numeric($maxLinkDepth) and $maxLinkDepth > 0)
                    {
                        $crawler = new Crawler($validLinkRegexes, $invalidLinkRegexes, 10, 30, Configuration::get("frontend.crawler.contentStartIndicator"), Configuration::get("frontend.crawler.contentEndIndicator"), Configuration::get("frontend.crawler.maxThreads"), $maxLinkDepth);
                    }
                    else
                    {
                        $crawler = new Crawler($validLinkRegexes, $invalidLinkRegexes, 10, 30, Configuration::get("frontend.crawler.contentStartIndicator"), Configuration::get("frontend.crawler.contentEndIndicator"), Configuration::get("frontend.crawler.maxThreads"));
                    }

                    $crawler->findLinks($urls);

                    self::setCrawlerState("frontend", "finished", false);

                    \Logger::debug("LuceneSearch_Plugin: replacing old index ...");

                    $db = \Pimcore\Db::get();
                    $db->query("DROP TABLE IF EXISTS `plugin_lucenesearch_contents`;");
                    $db->query("RENAME TABLE `plugin_lucenesearch_contents_temp` TO `plugin_lucenesearch_contents`;");

                    //TODO nix specific
                    exec("rm -Rf " . $indexDir);
                    \Logger::debug("rm -Rf " . $indexDir);
                    $tmpIndex = str_replace("/index", "/tmpindex", $indexDir);
                    exec("cp -R " . substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1));
                    \Logger::debug("cp -R " . substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1));
                    \Logger::debug("LuceneSearch_Plugin: replaced old index");
                    \Logger::info("LuceneSearch_Plugin: Finished crawl");


                }
                catch (\Exception $e)
                {
                    \Logger::err($e);
                    throw $e;
                }
            }
        }
        else
        {
            \Logger::info("LuceneSearch_Plugin: Did not start frontend crawler, because config incomplete");
        }
    }

    /**
     * @param  $crawler frontend | backend
     * @return void
     */
    public static function forceCrawlerStartOnNextMaintenance($crawler)
    {
        Configuration::set($crawler .'.crawler.forceStart', TRUE);
    }


    /**
     * @param  string $crawler frontend | backend
     * @param string $action started | finished
     * @param bool $running
     * @return void
     */
    protected static function setCrawlerState($crawler, $action, $running, $setTime = true)
    {
        $run = FALSE;

        if ($running)
        {
            $run = TRUE;
        }

        Configuration::set($crawler .'.crawler.forceStart', FALSE);
        Configuration::set($crawler .'.crawler.running', $run);

        if ($setTime)
        {
            Configuration::set($crawler .'.crawler.' . $action, time());
        }
    }

    protected static function setStopLock($crawler, $flag = true)
    {
        $stop = TRUE;

        if (!$flag)
        {
            $stop = FALSE;
        }

        Configuration::set($crawler .'.crawler.forceStop', $stop);

        if ($stop)
        {
            Configuration::set($crawler .'.crawler.forceStopInitiated', time());
        }
    }

    /**
     * Hook called when maintenance script is called
     */
    public function maintenance()
    {
        if (self::isInstalled())
        {
            $currentHour = date("H", time());

            //Frontend recrawl
            $lastStarted = Configuration::get('frontend.crawler.started');
            $lastFinished = Configuration::get('frontend.crawler.finished');
            $running = Configuration::get('frontend.crawler.running');
            $aDayAgo = time() - (24 * 60 * 60);
            $forceStart = Configuration::get('frontend.crawler.forceStart');

            $forceStart = TRUE;

            $enabled = Configuration::get('frontend.enabled');

            if ($enabled && ((!$running && (is_bool($lastStarted) || $lastStarted <= $aDayAgo) && $currentHour > 1 && $currentHour < 3) || $forceStart))
            {
                \Logger::debug("starting frontend recrawl...");
                $this->frontendCrawl();
                Tool\Tool::generateSitemap();

            }
            else if ($running and ($lastFinished <= ($aDayAgo)))
            {
                //there seems to be a problem
                if ($lastFinished <= ($aDayAgo))
                {
                    \Logger::err("Search_PluginPhp: There seems to be a problem with the search crawler! Trying to stop it.");
                }

                $this->stopFrontendCrawler(false, false);
            }

        }
        else
        {
            \Logger::debug("LuceneSearch Plugin is not installed - no maintenance to do for this plugin.");
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

        if ($index != null) {

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
     * @param Zend_Search_Lucene_Interface $index
     * @param integer $prefixLength optionally specify prefix lengh, default 0
     * @param float $similarity optionally specify similarity, default 0.5
     * @return string[] $similarSearchTerms
     */
    public static function fuzzyFindTerms($queryStr, $index, $prefixLengh = 0, $similarity = 0.5)
    {

        if ($index != null) {

            \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength($prefixLengh);
            $term = new \Zend_Search_Lucene_Index_Term($queryStr);
            $fuzzyQuery = new \Zend_Search_Lucene_Search_Query_Fuzzy($term, $similarity);

            $hits = $index->find($fuzzyQuery);
            $terms = $fuzzyQuery->getQueryTerms();

            return $terms;
        }
    }

}

