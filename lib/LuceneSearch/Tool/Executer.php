<?php

namespace LuceneSearch\Tool;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Model\Parser;
use LuceneSearch\Model\SitemapBuilder;

class Executer {

    public static function runCrawler()
    {
        $running = Configuration::getCoreSetting('running');

        if( $running === TRUE)
        {
            return FALSE;
        }

        $db = \Pimcore\Db::get();

        $indexDir = \LuceneSearch\Plugin::getFrontendSearchIndex();

        if ($indexDir)
        {
            $db->query("DROP TABLE IF EXISTS `lucene_search_index`;");
            $db->query("CREATE TABLE `lucene_search_index` (
                      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                      `identifier` varchar(255) DEFAULT '',
                      `contentType` varchar(255) DEFAULT NULL,
                      `contentLanguage` varchar(255) DEFAULT NULL,
                      `host` text,
                      `uri` text,
                      `content` longblob,
                      PRIMARY KEY (`id`),
                      KEY `identifier` (`identifier`)
                    ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;");


            exec('rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));

            \Pimcore\Logger::debug('LuceneSearch: rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));
            \Pimcore\Logger::debug('LuceneSearch: Starting crawl');

            try
            {
                $urls = Configuration::get('frontend.urls');
                $invalidLinkRegexesSystem = Configuration::get('frontend.invalidLinkRegexes');
                $invalidLinkRegexesEditable = Configuration::get('frontend.invalidLinkRegexesEditable');

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

                self::setCrawlerState('frontend', 'started', TRUE);

                try
                {
                    foreach( $urls as $seed )
                    {
                        $parser = new Parser();

                        $parser
                            ->setDepth( Configuration::get('frontend.crawler.maxLinkDepth') )
                            ->setValidLinkRegexes( Configuration::get('frontend.validLinkRegexes') )
                            ->setInvalidLinkRegexes( $invalidLinkRegexes )
                            ->setSearchStartIndicator(Configuration::get('frontend.crawler.contentStartIndicator'))
                            ->setSearchEndIndicator(Configuration::get('frontend.crawler.contentEndIndicator'))
                            ->setSearchExcludeStartIndicator(Configuration::get('frontend.crawler.contentExcludeStartIndicator'))
                            ->setSearchExcludeEndIndicator(Configuration::get('frontend.crawler.contentExcludeEndIndicator'))
                            ->setAllowSubdomain( FALSE )
                            ->setAllowedSchemes( Configuration::get('frontend.allowedSchemes') )
                            ->setDownloadLimit( Configuration::get('frontend.crawler.maxDownloadLimit') )
                            ->setDocumentBoost( Configuration::get('boost.documents') )
                            ->setAssetBoost( Configuration::get('boost.assets') )
                            ->setSeed( $seed );

                        if( Configuration::get('frontend.auth.useAuth') === TRUE )
                        {
                            $parser->setAuth( Configuration::get('frontend.auth.username'), Configuration::get('frontend.auth.password') );
                        }

                        $parser->startParser();
                        $parser->optimizeIndex();
                    }

                } catch(\Exception $e) { }

                self::setCrawlerState('frontend', 'finished', FALSE);

                //only remove index, if tmp exists!
                $tmpIndex = str_replace('/index', '/tmpindex', $indexDir);

                //remove lucene search index tmp folder
                $db->query("DROP TABLE IF EXISTS `lucene_search_index`;");
                \Pimcore\Logger::debug('LuceneSearch: drop table lucene_search_index');

                if( is_dir( $tmpIndex ) )
                {
                    exec('rm -Rf ' . $indexDir);
                    \Pimcore\Logger::debug('LuceneSearch: rm -Rf ' . $indexDir);

                    exec('cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));

                    \Pimcore\Logger::debug('LuceneSearch: cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));
                    \Pimcore\Logger::debug('LuceneSearch: replaced old index');
                    \Pimcore\Logger::info('LuceneSearch: Finished crawl');
                }
                else
                {
                    \Pimcore\Logger::error('LuceneSearch: skipped index replacing. no tmp index found.');
                }

            }
            catch (\Exception $e)
            {
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
     * @param string $action started | finished
     * @param bool $running
     * @param bool $setTime
     * @return void
     */
    public static function setCrawlerState($crawler, $action, $running, $setTime = TRUE)
    {
        Configuration::setCoreSetting('forceStart', FALSE);
        Configuration::setCoreSetting('running', $running);

        if( $action == 'started' && $running == TRUE )
        {
            touch( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
        }

        if( $action == 'finished' && $running == FALSE)
        {
            if( is_file( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp' ) )
            {
                unlink( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
            }
        }

        if ($setTime)
        {
            Configuration::setCoreSetting($action, time());
        }
    }

    public static function setStopLock($crawler, $flag = TRUE)
    {
        $stop = TRUE;

        if (!$flag)
        {
            $stop = FALSE;
        }

        Configuration::setCoreSetting('forceStop', $stop);
    }

    public static function generateSitemap()
    {
        if( Configuration::get('frontend.sitemap.render') === FALSE )
        {
            return FALSE;
        }

        $builder = new SitemapBuilder();
        $builder->generateSitemap();

        return TRUE;
    }
}