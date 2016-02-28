<?php

namespace LuceneSearch\Tool;

class Tool {

    public static function getCrawlerQuery() {

        $queryFile = PIMCORE_PLUGINS_PATH . '/LuceneSearch/db/query.sql';

        return file_get_contents( $queryFile );

    }

    public static function generateSitemap()
    {
        $sitemapDir = PIMCORE_WEBSITE_PATH . "/var/search/sitemap";

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
            $sitemapDirAvailable =true;
        }

        if($sitemapDirAvailable)
        {
            $db = \Pimcore\Db::get();

            $hosts = $db->fetchAll("SELECT DISTINCT host from plugin_lucenesearch_contents");

            if(is_array($hosts))
            {
                //create domain sitemaps
                foreach($hosts as $row)
                {
                    $host = $row['host'];
                    $data = $db->fetchAll("SELECT * FROM plugin_lucenesearch_contents WHERE host = '".$host."' AND content != 'canonical' AND content!='noindex' ORDER BY uri", array());

                    $name = str_replace(".","-",$host);
                    $filePath = $sitemapDir . "/sitemap-".$name.".xml";

                    $fh = fopen($filePath, 'w');
                    fwrite($fh,'<?xml version="1.0" encoding="UTF-8"?>'."\r\n");
                    fwrite($fh,'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                    fwrite($fh,"\r\n");

                    foreach($data as $row)
                    {
                        $uri = str_replace("&pimcore_outputfilters_disabled=1","",$row['uri']);
                        $uri = str_replace("?pimcore_outputfilters_disabled=1","",$uri);
                        fwrite($fh,'<url>'."\r\n");
                        fwrite($fh,'    <loc>'.htmlspecialchars($uri,ENT_QUOTES).'</loc>'."\r\n");
                        fwrite($fh,'</url>'."\r\n");
                    }

                    fwrite($fh,'</urlset>'."\r\n");
                    fclose($fh);
                }

                //create sitemap index file
                $filePath = $sitemapDir . "/sitemap.xml";
                $fh = fopen($filePath, 'w');
                fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
                fwrite($fh, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                fwrite($fh, "\r\n");

                foreach ($hosts as $row)
                {
                    $host = $row['host'];
                    $name = str_replace(".", "-", $host);

                    //first host must be main domain - see hint in plugin settings
                    $currenthost = $hosts[0]['host'];
                    fwrite($fh, '<sitemap>' . "\r\n");
                    fwrite($fh, '    <loc>http://' . $currenthost . "/plugin/LuceneSearch/frontend/sitemap/?sitemap=sitemap-" . $name . ".xml" . '</loc>' . "\r\n");
                    fwrite($fh, '</sitemap>' . "\r\n");
                }

                fwrite($fh, '</sitemapindex>' . "\r\n");
                fclose($fh);

            } else
            {
                \Logger::warn("LuceneSearch_Tool: could not generate sitemaps, did not find any hosts in index.");
            }

        } else
        {
            \Logger::emerg("LuceneSearch_Tool: Cannot generate sitemap. Sitemap directory [ ".$sitemapDir." ]  not available/not writeable and cannot be created");
        }

    }

}
