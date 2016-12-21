<?php

use Pimcore\Controller\Action\Admin;

use LuceneSearch\Plugin;
use LuceneSearch\Tool;
use LuceneSearch\Model\Configuration;


class LuceneSearch_Admin_PluginController extends Admin {

    public function init()
    {
        parent::init();
    }

    public function getSettingsAction()
    {
        $config = new Configuration\Listing();

        $valueArray = array();

        foreach ($config->getConfigurations() as $c) {

            $data = $c->getData();
            $valueArray[$c->getKey()] = $data;
        }

        $frontendButtonDisabled = FALSE;

        if(Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() or !Plugin::frontendConfigComplete())
        {
            $frontendButtonDisabled = TRUE;
        }

        $frontendStopButtonDisabled = FALSE;

        if(!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() or Plugin::frontendCrawlerStopLocked() )
        {
            $frontendStopButtonDisabled = TRUE;
        }

        $response = array(
            'values' => $valueArray,
            'crawler' => array(
                'state' => Plugin::getPluginState(),
                'canStart' => !$frontendButtonDisabled,
                'canStop' => !$frontendStopButtonDisabled
            )
        );

        $this->_helper->json($response);
        $this->_helper->json(FALSE);
    }

    public function getStateAction()
    {
        $frontendButtonDisabled = FALSE;

        if(Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() || !Plugin::frontendConfigComplete())
        {
            $frontendButtonDisabled = TRUE;
        }

        $frontendStopButtonDisabled = FALSE;

        if(!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerStopLocked() )
        {
            $frontendStopButtonDisabled = TRUE;
        }

        $this->_helper->json(
            array(
                'message' => Plugin::getPluginState(),
                'frontendButtonDisabled' => $frontendButtonDisabled,
                'frontendStopButtonDisabled' => $frontendStopButtonDisabled
            )
        );
    }

    public function stopFrontendCrawlerAction()
    {
        $success = Tool\Executer::stopCrawler();
        $this->_helper->json(array('success' => $success));
    }

    public function startFrontendCrawlerAction()
    {
        Plugin::forceCrawlerStartOnNextMaintenance('frontend');
        $this->_helper->json(array('success' => TRUE));
    }

    public function getFrontendUrlsAction() {

        $urls = Configuration::get('frontend.urls');
        $urlArray = Tool\ConfigParser::parseValues($urls, 'url');

        $this->_helper->json($urlArray);
    }

    public function getFrontendAllowedAction()
    {
        $urls = Configuration::get('frontend.validLinkRegexes');
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, 'regex');

        $this->_helper->json($urlArray);
    }

    public function getFrontendForbiddenAction()
    {
        $urls = Configuration::get('frontend.invalidLinkRegexesEditable');
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, 'regex');

        $this->_helper->json($urlArray);
    }

    public function getFrontendCategoriesAction()
    {
        $urls = Configuration::get('frontend.categories');
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, 'category');

        $this->_helper->json($urlArray);
    }

    public function setSettingAction()
    {
        $values = \Zend_Json::decode($this->_getParam('data'));

        //general settings
        Configuration::set('frontend.enabled', FALSE);

        if(!Configuration::get('frontend.enabled') && $values['search.frontend.enabled'])
        {
            Configuration::set('frontend.enabled', TRUE);
        }

        Configuration::set('frontend.ignoreLanguage', FALSE);
        if ($values['frontend.ignoreLanguage'])
        {
            Configuration::set('frontend.ignoreLanguage', TRUE);
        }

        Configuration::set('frontend.ignoreCountry', FALSE);
        if ($values['frontend.ignoreCountry'])
        {
            Configuration::set('frontend.ignoreCountry', TRUE);
        }

        Configuration::set('frontend.ignoreRestriction', FALSE);

        Configuration::set('frontend.auth.useAuth', FALSE);
        Configuration::set('frontend.auth.username', '');
        Configuration::set('frontend.auth.password', '');
        Configuration::set('frontend.restriction.class', '');
        Configuration::set('frontend.restriction.method', '');

        if ($values['frontend.ignoreRestriction'])
        {
            Configuration::set('frontend.ignoreRestriction', TRUE);
        }
        else
        {
            if ($values['frontend.auth.useAuth'])
            {
                Configuration::set('frontend.auth.useAuth', TRUE);
                Configuration::set('frontend.auth.username', $values['frontend.auth.username']);
                Configuration::set('frontend.auth.password', $values['frontend.auth.password']);
            }

            Configuration::set('frontend.restriction.class', $values['frontend.restriction.class']);
            Configuration::set('frontend.restriction.method', $values['frontend.restriction.method']);

        }

        Configuration::set('frontend.fuzzySearch', FALSE);
        if ($values['frontend.fuzzySearch'])
        {
            Configuration::set('frontend.fuzzySearch', TRUE);
        }

        Configuration::set('frontend.ownHostOnly', FALSE);
        if ($values['frontend.ownHostOnly'])
        {
            Configuration::set('frontend.ownHostOnly', TRUE);
        }

        Configuration::set('frontend.sitemap.render', FALSE);
        if ($values['frontend.sitemap.render'])
        {
            Configuration::set('frontend.sitemap.render', TRUE);
        }

        if (is_numeric($values['frontend.crawler.maxLinkDepth']))
        {
            Configuration::set('frontend.crawler.maxLinkDepth', (int) $values['frontend.crawler.maxLinkDepth']);
        }
        else
        {
            Configuration::set('frontend.crawler.maxLinkDepth', 15);
        }

        if (is_numeric($values['frontend.crawler.maxDownloadLimit']))
        {
            Configuration::set('frontend.crawler.maxDownloadLimit', (int) $values['frontend.crawler.maxDownloadLimit']);
        }
        else
        {
            Configuration::set('frontend.crawler.maxDownloadLimit', 0);
        }

        //Frontend Urls must end with an trailing slash
        $_frontendUrls = $values['frontend.urls'];
        $frontendUrls = array();

        if( is_array( $_frontendUrls ) )
        {
            foreach( $_frontendUrls as $seedUrl)
            {
                $frontendUrls[] = rtrim($seedUrl, '/' ) . '/';
            }
        }

        Configuration::set('frontend.urls', $frontendUrls);

        Configuration::set('frontend.allowedSchemes', $values['frontend.allowedSchemes']);
        Configuration::set('frontend.categories', $values['frontend.categories']);
        Configuration::set('frontend.validLinkRegexes', $values['frontend.validLinkRegexes']);
        Configuration::set('frontend.invalidLinkRegexesEditable', $values['frontend.invalidLinkRegexesEditable']);

        Configuration::set('frontend.crawler.contentStartIndicator', $values['frontend.crawler.contentStartIndicator']);
        Configuration::set('frontend.crawler.contentEndIndicator', $values['frontend.crawler.contentEndIndicator']);

        Configuration::set('frontend.crawler.contentExcludeStartIndicator', $values['frontend.crawler.contentExcludeStartIndicator']);
        Configuration::set('frontend.crawler.contentExcludeEndIndicator', $values['frontend.crawler.contentExcludeEndIndicator']);

        if (is_numeric($values['frontend.view.maxPerPage']))
        {
            Configuration::set('frontend.view.maxPerPage', (int) $values['frontend.view.maxPerPage']);
        }
        else
        {
            Configuration::set('frontend.view.maxPerPage', 10);
        }

        if (is_numeric($values['frontend.view.maxSuggestions']))
        {
            Configuration::set('frontend.view.maxSuggestions', (int) $values['frontend.view.maxSuggestions']);
        }
        else
        {
            Configuration::set('frontend.view.maxSuggestions', 10);
        }

        if (is_numeric($values['boost.documents']))
        {
            Configuration::set('boost.documents', (int) $values['boost.documents']);
        }
        else
        {
            Configuration::set('boost.documents', 1);
        }

        if (is_numeric($values['boost.assets']))
        {
            Configuration::set('boost.assets', (int) $values['boost.assets']);
        }
        else
        {
            Configuration::set('boost.assets', 1);
        }

        $this->_helper->json( array('success' => TRUE) );

    }

}