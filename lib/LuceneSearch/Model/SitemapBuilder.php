<?php

namespace LuceneSearch\Model;

use LuceneSearch\Plugin;

class SitemapBuilder
{
    /**
     * @var \Zend_Search_Lucene
     */
    protected $index = NULL;

    /**
     * @var null
     */
    protected $sitemapDir = NULL;

    /**
     * SitemapBuilder constructor.
     */
    public function __construct()
    {
        $indexDir = Plugin::getFrontendSearchIndex();
        $this->index = \Zend_Search_Lucene::open($indexDir);
    }

    /**
     *
     */
    public function generateSiteMap()
    {
        $this->prepareSiteMapFolder();

        if (!is_null($this->sitemapDir)) {
            $hosts = $this->getValidHosts();

            if (is_array($hosts)) {
                foreach ($hosts as $hostName) {
                    $numDocs = $this->index->count();

                    $name = str_replace('.', '-', $hostName);
                    $filePath = $this->sitemapDir . '/sitemap-' . $name . '.xml';

                    $fh = fopen($filePath, 'w');
                    fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
                    fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                    fwrite($fh, "\r\n");

                    for ($i = 0; $i < $numDocs; $i++) {
                        $document = $this->index->getDocument($i);

                        $url = $document->getField('url');
                        if (strpos($url->value, '://' . $hostName) === FALSE) {
                            continue;
                        }

                        $uri = str_replace(['?pimcore_outputfilters_disabled=1', '&pimcore_outputfilters_disabled=1'], '', $url->value);

                        fwrite($fh, '<url>' . "\r\n");
                        fwrite($fh, '    <loc>' . htmlspecialchars($uri, ENT_QUOTES) . '</loc>' . "\r\n");
                        fwrite($fh, '</url>' . "\r\n");
                    }

                    fwrite($fh, '</urlset>' . "\r\n");
                    fclose($fh);
                }

                $filePath = $this->sitemapDir . '/sitemap.xml';
                $fh = fopen($filePath, 'w');
                fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
                fwrite($fh, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                fwrite($fh, "\r\n");

                foreach ($hosts as $hostName) {
                    $name = str_replace('.', '-', $hostName);

                    //first host must be main domain - see hint in plugin settings
                    $currenthost = $hosts[0];
                    fwrite($fh, '<sitemap>' . "\r\n");
                    fwrite($fh, '    <loc>http://' . $currenthost . '/plugin/LuceneSearch/frontend/sitemap/?sitemap=sitemap-' . $name . '.xml' . '</loc>' . "\r\n");
                    fwrite($fh, '</sitemap>' . "\r\n");

                    \Pimcore\Logger::debug('LuceneSearch: ' . $hostName . ' for sitemap.xml added.');
                }

                fwrite($fh, '</sitemapindex>' . "\r\n");
                fclose($fh);
            } else {
                \Pimcore\Logger::debug('LuceneSearch: could not generate sitemaps, did not find any hosts in index.');
            }
        } else {
            \Pimcore\Logger::emerg('LuceneSearch: Cannot generate sitemap. Sitemap directory [ ' . $this->sitemapDir . ' ]  not available/not writeable and cannot be created');
        }
    }

    /**
     * Get all allowed hosts.
     */
    private function getValidHosts()
    {
        $urls = Configuration::get('frontend.urls');

        if (empty($urls)) {
            return [];
        }

        $hosts = [];

        foreach ($urls as $url) {
            $parsedUrl = parse_url($url);
            $hosts[] = $parsedUrl['host'];
        }

        return $hosts;
    }

    /**
     *
     */
    private function prepareSiteMapFolder()
    {
        $sitemapDir = PIMCORE_WEBSITE_PATH . '/var/search/sitemap';

        if (is_dir($sitemapDir) && !is_writable($sitemapDir)) {
            $sitemapDirAvailable = FALSE;
        } else if (!is_dir($sitemapDir)) {
            $sitemapDirAvailable = mkdir($sitemapDir, 0755, TRUE);
            chmod($sitemapDir, 0755);
        } else {
            $sitemapDirAvailable = TRUE;
        }

        if ($sitemapDirAvailable == TRUE) {
            $this->sitemapDir = $sitemapDir;
        }
    }
}