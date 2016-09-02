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

        $indexDir = \LuceneSearch\Plugin::getFrontendSearchIndex();

        if ($indexDir)
        {
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

                self::setCrawlerState('frontend', 'started', true);

                try
                {
                    $parser = new Parser();

                    $parser
                        ->setDepth( Configuration::get('frontend.crawler.maxLinkDepth') )
                        ->setValidLinkRegexes( Configuration::get('frontend.validLinkRegexes') )
                        ->setInvalidLinkRegexes( $invalidLinkRegexes )
                        ->setSearchStartIndicator(Configuration::get('frontend.crawler.contentStartIndicator'))
                        ->setSearchEndIndicator(Configuration::get('frontend.crawler.contentEndIndicator'))
                        ->setAllowSubdomain( FALSE )
                        ->setAllowedSchemes( Configuration::get('frontend.allowedSchemes') )
                        ->setDownloadLimit( Configuration::get('frontend.crawler.maxDownloadLimit') )
                        ->setSeed( $urls[0] );

                    if( Configuration::get('frontend.auth.useAuth') === TRUE )
                    {
                        $parser->setAuth( Configuration::get('frontend.auth.username'), Configuration::get('frontend.auth.password') );
                    }

                    $parser->startParser($urls);

                    $parser->optimizeIndex();

                }

                catch(\Exception $e) { }

                self::setCrawlerState('frontend', 'finished', false);

                //only remove index, if tmp exists!
                $tmpIndex = str_replace('/index', '/tmpindex', $indexDir);

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
                    \Pimcore\Logger::err('LuceneSearch: skipped index replacing. no tmp index found.');
                }

            }
            catch (\Exception $e)
            {
                \Pimcore\Logger::err($e);
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

        self::setStopLock('frontend', false);
        self::setCrawlerState('frontend', 'finished', false);

        return true;

    }

    /**
     * @param string $crawler frontend | backend
     * @param string $action started | finished
     * @param bool $running
     * @param bool $setTime
     * @return void
     */
    public static function setCrawlerState($crawler, $action, $running, $setTime = true)
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

    public static function setStopLock($crawler, $flag = true)
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