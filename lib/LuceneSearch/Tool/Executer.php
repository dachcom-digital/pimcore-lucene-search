<?php

namespace LuceneSearch\Tool;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Model\Parser;

class Executer {

    public static function runCrawler()
    {
        $running = Configuration::get('frontend.crawler.running');

        if( $running === TRUE)
        {
            return FALSE;
        }

        $indexDir = \LuceneSearch\Plugin::getFrontendSearchIndex();

        if ($indexDir)
        {
            exec('rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));

            \Logger::debug('LuceneSearch: rm -Rf ' . str_replace('/index/', '/tmpindex', $indexDir));
            \Logger::log('LuceneSearch: Starting crawl', \Zend_Log::DEBUG);

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

                self::setCrawlerState('frontend', 'started', true, true);

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
                        ->setDownloadLimit( Configuration::get('frontend.crawler.maxDownloadLimit') )
                        ->setSeed( $urls[0] );

                    $parser->startParser($urls);

                    $parser->optimizeIndex();

                }

                catch(\Exception $e) { }

                self::setCrawlerState('frontend', 'finished', false, true);

                //only remove index, if tmp exists!
                $tmpIndex = str_replace('/index', '/tmpindex', $indexDir);

                if( is_dir( $tmpIndex ) )
                {
                    echo "\n";

                    exec('rm -Rf ' . $indexDir);
                    \Logger::debug('LuceneSearch: rm -Rf ' . $indexDir);


                    exec('cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));

                    \Logger::debug('LuceneSearch: cp -R ' . substr($tmpIndex, 0, -1) . ' ' . substr($indexDir, 0, -1));
                    \Logger::debug('LuceneSearch: replaced old index');
                    \Logger::info('LuceneSearch: Finished crawl');
                }
                else
                {
                    \Logger::err('LuceneSearch: skipped index replacing. no tmp index found.');
                }

            }
            catch (\Exception $e)
            {
                \Logger::err($e);
                throw $e;
            }

        }

    }

    /**
     * @static
     * @param bool $playNice
     * @param bool $isFrontendCall
     * @return bool
     */
    public static function stopCrawler($playNice = true, $isFrontendCall = false)
    {
        \Logger::debug('LuceneSearch: forcing frontend crawler stop');

        self::setStopLock('frontend', true);

        //just to make sure nothing else starts the crawler right now
        self::setCrawlerState('frontend', 'started', false);

        \Logger::debug('LuceneSearch: forcing frontend crawler stop.');

        self::setStopLock('frontend', false);
        self::setCrawlerState('frontend', 'finished', false);

        \Zend_Registry::set('dings', true);

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
        $run = FALSE;

        if ($running)
        {
            $run = TRUE;
        }

        Configuration::set($crawler .'.crawler.forceStart', FALSE);
        Configuration::set($crawler .'.crawler.running', $run);

        if( $action == 'started' && $running == TRUE )
        {
            touch( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
        }

        if( $action == 'finished' && $run == FALSE)
        {
            if( is_file( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp' ) )
            {
                unlink( PIMCORE_TEMPORARY_DIRECTORY . '/lucene-crawler.tmp');
            }
        }

        if ($setTime)
        {
            Configuration::set($crawler .'.crawler.' . $action, time());
        }
    }

    public static function setStopLock($crawler, $flag = true)
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

    public static function generateSitemap()
    {
        return FALSE;

        $sitemapDir = PIMCORE_WEBSITE_PATH . '/var/search/sitemap';

        if(is_dir($sitemapDir) && !is_writable($sitemapDir))
        {
            $sitemapDirAvailable = false;
        }
        else if( !is_dir($sitemapDir) )
        {
            $sitemapDirAvailable = mkdir($sitemapDir, 0755, true);
            chmod($sitemapDir, 0755);
        }
        else
        {
            $sitemapDirAvailable = true;
        }

        if($sitemapDirAvailable)
        {
            $db = \Pimcore\Db::get();

            $hosts = $db->fetchAll('SELECT DISTINCT host from plugin_lucenesearch_contents');

            if(is_array($hosts))
            {
                //create domain sitemaps
                foreach($hosts as $row)
                {
                    $host = $row['host'];
                    $data = $db->fetchAll('SELECT * FROM plugin_lucenesearch_contents WHERE host = "' . $host . '" AND content != "canonical" AND content!="noindex" ORDER BY uri', array());

                    $name = str_replace('.','-',$host);
                    $filePath = $sitemapDir . '/sitemap-'.$name.'.xml';

                    $fh = fopen($filePath, 'w');
                    fwrite($fh,'<?xml version="1.0" encoding="UTF-8"?>'."\r\n");
                    fwrite($fh,'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                    fwrite($fh,"\r\n");

                    foreach($data as $row)
                    {
                        $uri = str_replace('&pimcore_outputfilters_disabled=1','',$row['uri']);
                        $uri = str_replace('?pimcore_outputfilters_disabled=1','',$uri);
                        fwrite($fh,'<url>' . "\r\n");
                        fwrite($fh,'    <loc>'.htmlspecialchars($uri,ENT_QUOTES).'</loc>'."\r\n");
                        fwrite($fh,'</url>' . "\r\n");
                    }

                    fwrite($fh,'</urlset>' . "\r\n");
                    fclose($fh);
                }

                //create sitemap index file
                $filePath = $sitemapDir . '/sitemap.xml';
                $fh = fopen($filePath, 'w');
                fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
                fwrite($fh, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                fwrite($fh, "\r\n");

                foreach ($hosts as $row)
                {
                    $host = $row['host'];
                    $name = str_replace('.', '-', $host);

                    //first host must be main domain - see hint in plugin settings
                    $currenthost = $hosts[0]['host'];
                    fwrite($fh, '<sitemap>' . "\r\n");
                    fwrite($fh, '    <loc>http://' . $currenthost . '/plugin/LuceneSearch/frontend/sitemap/?sitemap=sitemap-' . $name . '.xml' . '</loc>' . "\r\n");
                    fwrite($fh, '</sitemap>' . "\r\n");
                }

                fwrite($fh, '</sitemapindex>' . "\r\n");
                fclose($fh);

            } else
            {
                \Logger::warn('LuceneSearch_Tool: could not generate sitemaps, did not find any hosts in index.');
            }

        } else
        {
            \Logger::emerg('LuceneSearch_Tool: Cannot generate sitemap. Sitemap directory [ '.$sitemapDir.' ]  not available/not writeable and cannot be created');
        }

    }
}