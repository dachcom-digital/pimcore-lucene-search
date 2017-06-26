<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Configuration\Configuration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteMapController extends FrontendController
{
    public function renderAction()
    {
        if ($this->configuration->getConfig('sitemap:render') === FALSE) {
            throw new NotFoundHttpException('no sitemap.xml found.');
        }

        $requestQuery = $this->requestStack->getMasterRequest()->query;
        $siteMapFile = $requestQuery->get('sitemap');

        if (strpos($siteMapFile, '/') !== FALSE) {
            // not allowed since site map file name is generated from domain name
            throw new \Exception(get_class($this) . ': Attempted access to invalid sitemap [' . $siteMapFile . ']');
        }

        $requestedSiteMap = Configuration::SITEMAP_DIR_PATH . '/' . $siteMapFile;
        $indexSiteMap = Configuration::SITEMAP_DIR_PATH . '/sitemap.xml';

        $content = NULL;

        if ($requestQuery->get('sitemap') && is_file($requestedSiteMap)) {
            $content = file_get_contents($requestedSiteMap);
        } else if (is_file($indexSiteMap)) {
            $content = file_get_contents($indexSiteMap);
        } else {
            \Pimcore\Logger::debug('LuceneSearch: sitemap request - but no sitemap available to deliver');
            throw new NotFoundHttpException('no sitemap.xml found.');
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}