<?php

use Pimcore\Controller\Action\Admin;
use LuceneSearch\Plugin;
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

        $frontendButtonDisabled = false;

        if(Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() or !Plugin::frontendConfigComplete())
        {
            $frontendButtonDisabled = true;
        }

        $frontendStopButtonDisabled = false;

        if(!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() or Plugin::frontendCrawlerStopLocked() )
        {
            $frontendStopButtonDisabled = true;
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
        $this->_helper->json(false);
    }

    public function getStateAction()
    {
        $frontendButtonDisabled = false;

        if(Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() or !Plugin::frontendConfigComplete())
        {
            $frontendButtonDisabled = true;   
        }

        $frontendStopButtonDisabled = false;

        if(!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() or Plugin::frontendCrawlerStopLocked() )
        {
            $frontendStopButtonDisabled = true;
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
        $playNice = true;
        if($this->_getParam('force'))
        {
            $playNice=false;
        }

        $success = Plugin::stopFrontendCrawler($playNice,true);
        $this->_helper->json(array('success' => $success));
    }

    public function startFrontendCrawlerAction()
    {
        Plugin::forceCrawlerStartOnNextMaintenance('frontend');
        $this->_helper->json(array('success' => true));
    }

    public function getFrontendUrlsAction() {

        $urls = Configuration::get('frontend.urls');
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, 'url');

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

        if (is_numeric($values['frontend.crawler.maxThreads']))
        {
            Configuration::set('frontend.crawler.maxThreads', (int) $values['frontend.crawler.maxThreads']);
        }

        if (is_numeric($values['frontend.crawler.maxLinkDepth']))
        {
            Configuration::set('frontend.crawler.maxLinkDepth', (int) $values['frontend.crawler.maxLinkDepth']);
        }
        else
        {
            Configuration::set('frontend.crawler.maxLinkDepth', 15);
        }

        Configuration::set('frontend.categories', $values['frontend.categories']);
        Configuration::set('frontend.urls', $values['frontend.urls']);
        Configuration::set('frontend.validLinkRegexes', $values['frontend.validLinkRegexes']);
        Configuration::set('frontend.invalidLinkRegexesEditable', $values['frontend.invalidLinkRegexesEditable']);

        Configuration::set('frontend.crawler.contentStartIndicator', $values['frontend.crawler.contentStartIndicator']);
        Configuration::set('frontend.crawler.contentEndIndicator', $values['frontend.crawler.contentEndIndicator']);

        $this->_helper->json(array('success' => true));

    }

}