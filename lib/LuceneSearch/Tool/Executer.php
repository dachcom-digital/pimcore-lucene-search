<?php

namespace LuceneSearch\Tool;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Model\Parser;
use LuceneSearch\Model\SitemapBuilder;
use LuceneSearch\Model\Logger\Engine;

class Executer
{
    /**
     * @param $logEngine
     * @param bool $force
     *
     * @return bool
     * @throws \Exception
     */
    public static function runCrawler(Engine $logEngine, $force = FALSE)
    {
        if (Configuration::getCoreSetting('running') === TRUE) {

            if($force === TRUE) {
                self::setCrawlerState('frontend', 'finished', FALSE);
            } else {
                return FALSE;
            }
        }

        $indexDir = \LuceneSearch\Plugin::getFrontendSearchIndex();

        if ($indexDir) {
            self::_prepareCrawl($indexDir);

            try {
                $urls = Configuration::get('frontend.urls');
                $invalidLinkRegexSystem = Configuration::get('frontend.invalidLinkRegexes');
                $invalidLinkRegexEditable = Configuration::get('frontend.invalidLinkRegexesEditable');

                if (!empty($invalidLinkRegexEditable) && !empty($invalidLinkRegexSystem)) {
                    $invalidLinkRegex = array_merge($invalidLinkRegexEditable, [$invalidLinkRegexSystem]);
                } else if (!empty($invalidLinkRegexEditable)) {
                    $invalidLinkRegex = $invalidLinkRegexEditable;
                } else if (!empty($invalidLinkRegexSystem)) {
                    $invalidLinkRegex = [$invalidLinkRegexSystem];
                } else {
                    $invalidLinkRegex = [];
                }

                self::setCrawlerState('frontend', 'started', TRUE);

                try {
                    foreach ($urls as $seed) {

                        $parser = new Parser($logEngine);
                        $parser
                            ->setDepth(Configuration::get('frontend.crawler.maxLinkDepth'))
                            ->setValidLinkRegexes(Configuration::get('frontend.validLinkRegexes'))
                            ->setContentMaxSize(Configuration::get('frontend.crawler.contentMaxSize'))
                            ->setInvalidLinkRegexes($invalidLinkRegex)
                            ->setSearchStartIndicator(Configuration::get('frontend.crawler.contentStartIndicator'))
                            ->setSearchEndIndicator(Configuration::get('frontend.crawler.contentEndIndicator'))
                            ->setSearchExcludeStartIndicator(Configuration::get('frontend.crawler.contentExcludeStartIndicator'))
                            ->setSearchExcludeEndIndicator(Configuration::get('frontend.crawler.contentExcludeEndIndicator'))
                            ->setAllowSubdomain(FALSE)
                            ->setValidMimeTypes(Configuration::get('frontend.allowedMimeTypes'))
                            ->setAllowedSchemes(Configuration::get('frontend.allowedSchemes'))
                            ->setDownloadLimit(Configuration::get('frontend.crawler.maxDownloadLimit'))
                            ->setDocumentBoost(Configuration::get('boost.documents'))
                            ->setAssetBoost(Configuration::get('boost.assets'))
                            ->setSeed($seed);

                        if (Configuration::get('frontend.auth.useAuth') === TRUE) {
                            $parser->setAuth(Configuration::get('frontend.auth.username'), Configuration::get('frontend.auth.password'));
                        }

                        $parser->startParser();
                        $parser->optimizeIndex();

                    }
                } catch (\Exception $e) {
                }

                self::setCrawlerState('frontend', 'finished', FALSE);
                self::_cleanUpCrawl($indexDir);
            } catch (\Exception $e) {
                \Pimcore\Logger::error($e);
                throw $e;
            }
        }
    }

    /**
     * @static
     * @return bool
     */
    public static function stopCrawler()
    {
        \Pimcore\Logger::debug('LuceneSearch: forcing frontend crawler stop');

        self::setStopLock('frontend', FALSE);
        self::setCrawlerState('frontend', 'finished', FALSE);

        return TRUE;
    }

    /**
     * @param string $crawler frontend | backend
     * @param string $action  started | finished
     * @param bool   $running
     * @param bool   $setTime
     *
     * @return void
     */
    public static function setCrawlerState($crawler, $action, $running, $setTime = TRUE)
    {
        Configuration::setCoreSetting('forceStart', FALSE);
        Configuration::setCoreSetting('running', $running);

        if ($action == 'started' && $running == TRUE) {
            touch(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
        }

        if ($action == 'finished' && $running == FALSE) {
            if (is_file(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp')) {
                unlink(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
            }
        }

        if ($setTime) {
            Configuration::setCoreSetting($action, time());
        }
    }

    /**
     * @param      $crawler
     * @param bool $flag
     */
    public static function setStopLock($crawler, $flag = TRUE)
    {
        $stop = TRUE;

        if (!$flag) {
            $stop = FALSE;
        }

        Configuration::setCoreSetting('forceStop', $stop);
    }

    /**
     * @return bool
     */
    public static function generateSitemap()
    {
        if (Configuration::get('frontend.sitemap.render') === FALSE) {
            return FALSE;
        }

        $builder = new SitemapBuilder();
        $builder->generateSitemap();

        return TRUE;
    }

    /**
     * @param string $indexDir
     */
    private static function _prepareCrawl($indexDir = '')
    {
        //tmp crawling folder preparation
        exec('rm -Rf ' . PIMCORE_SYSTEM_TEMP_DIRECTORY . '/ls-crawler-tmp');
        mkdir(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/ls-crawler-tmp');

        //remove old log
        if (file_exists(PIMCORE_WEBSITE_VAR . '/search/log.txt')) {
            unlink(PIMCORE_WEBSITE_VAR . '/search/log.txt');
        }

        exec('rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));

        \Pimcore\Logger::debug('LuceneSearch: create table lucene_search_index');
        \Pimcore\Logger::debug('LuceneSearch: rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));
        \Pimcore\Logger::debug('LuceneSearch: Starting crawl');
    }

    /**
     * @param string $indexDir
     */
    private static function _cleanUpCrawl($indexDir = '')
    {
        //remove tmp crawling folder
        exec('rm -Rf ' . PIMCORE_SYSTEM_TEMP_DIRECTORY . '/ls-crawler-tmp');

        //only remove index, if tmp exists!
        $tmpIndex = str_replace('/index', '/tmpindex', $indexDir);

        //remove lucene search index tmp folder
        \Pimcore\Logger::debug('LuceneSearch: drop table lucene_search_index');

        //remove old log
        if (file_exists(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-filter.tmp')) {
            unlink(PIMCORE_TEMPORARY_DIRECTORY . '/lucene-filter.tmp');
        }

        if (is_dir($tmpIndex)) {
            exec('rm -Rf ' . $indexDir);
            exec('cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));

            \Pimcore\Logger::debug('LuceneSearch: rm -Rf ' . $indexDir);
            \Pimcore\Logger::debug('LuceneSearch: cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));
            \Pimcore\Logger::debug('LuceneSearch: replaced old index');
            \Pimcore\Logger::debug('LuceneSearch: Finished crawl');
        } else {
            \Pimcore\Logger::error('LuceneSearch: skipped index replacing. no tmp index found.');
        }
    }
}