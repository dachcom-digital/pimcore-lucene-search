<?php

namespace LuceneSearchBundle\Task\Sitemap;

use LuceneSearchBundle\Task\AbstractTask;
use LuceneSearchBundle\Configuration\Configuration;

class SitemapTask extends AbstractTask
{
    /**
     * @var \Zend_Search_Lucene
     */
    protected $index = NULL;

    /**
     * @var null
     */
    protected $siteMapDir = NULL;

    /**
     * @return bool
     */
    public function isValid()
    {
        return TRUE;
    }

    /**
     * @param mixed $data
     *
     * @return bool
     */
    public function process($data)
    {
        $this->logger->setPrefix('task.sitemap');
        $siteMapConfig = $this->configuration->getConfig('sitemap');

        if($siteMapConfig['render'] === FALSE) {
            $this->log('skip generating of sitemap because it\'s disabled in settings.');
            return TRUE;
        }

        $this->index = \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);
        $this->generateSiteMap();

        return TRUE;
    }

    private function generateSiteMap()
    {
        $this->prepareSiteMapFolder();

        if (is_null($this->siteMapDir)) {
            $this->log('cannot generate sitemap. Sitemap directory [ ' . $this->siteMapDir . ' ]  not available/not writeable and cannot be created', 'emergency');
        }

        $hosts = $this->getValidHosts();

        if (!is_array($hosts)) {
            $this->log('could not generate sitemaps, did not find any hosts in index.');
        }
        
        foreach ($hosts as $hostName) {

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();

            $hostTerm = new \Zend_Search_Lucene_Index_Term($hostName, 'host');
            $hostQuery = new \Zend_Search_Lucene_Search_Query_Term($hostTerm);
            $query->addSubquery($hostQuery, TRUE);

            $hostTerm = new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_default');
            $hostQuery = new \Zend_Search_Lucene_Search_Query_Term($hostTerm);
            $query->addSubquery($hostQuery, TRUE);

            $hits = $this->index->find($query);

            $name = str_replace('.', '-', $hostName);
            $filePath = $this->siteMapDir . '/sitemap-' . $name . '.xml';

            $fh = fopen($filePath, 'w');
            fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
            fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
            fwrite($fh, "\r\n");

            for ($i = 0; $i < (count($hits)); $i++) {

                $url = $hits[$i]->getDocument()->getField('url');
                $uri = str_replace(['?pimcore_outputfilters_disabled=1', '&pimcore_outputfilters_disabled=1'], '', $url->value);

                fwrite($fh, '<url>' . "\r\n");
                fwrite($fh, '    <loc>' . htmlspecialchars($uri, ENT_QUOTES) . '</loc>' . "\r\n");
                fwrite($fh, '</url>' . "\r\n");
            }

            fwrite($fh, '</urlset>' . "\r\n");
            fclose($fh);
        }

        $filePath = $this->siteMapDir . '/sitemap.xml';
        $fh = fopen($filePath, 'w');
        fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
        fwrite($fh, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
        fwrite($fh, "\r\n");

        foreach ($hosts as $hostName) {
            $name = str_replace('.', '-', $hostName);

            //first host must be main domain - see hint in plugin settings
            $currenthost = $hosts[0];
            fwrite($fh, '<sitemap>' . "\r\n");
            //@todo: implement sitemap xml route
            fwrite($fh, '    <loc>http://' . $currenthost . '/sitemap/?sitemap=sitemap-' . $name . '.xml' . '</loc>' . "\r\n");
            fwrite($fh, '</sitemap>' . "\r\n");

            $this->log($hostName . ' for sitemap.xml added.');
        }

        fwrite($fh, '</sitemapindex>' . "\r\n");
        fclose($fh);

    }

    /**
     * Get all allowed hosts.
     */
    private function getValidHosts()
    {
        $urls = $this->configuration->getConfig('seeds');

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
        $siteMapDir = Configuration::SITEMAP_DIR_PATH;

        if (is_dir($siteMapDir) && !is_writable($siteMapDir)) {
            $siteMapDirAvailable = FALSE;
        } else if (!is_dir($siteMapDir)) {
            $siteMapDirAvailable = mkdir($siteMapDir, 0755, TRUE);
            chmod($siteMapDir, 0755);
        } else {
            $siteMapDirAvailable = TRUE;
        }

        if ($siteMapDirAvailable == TRUE) {
            $this->siteMapDir = $siteMapDir;
        }
    }
}