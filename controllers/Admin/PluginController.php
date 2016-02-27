<?php

use Pimcore\Controller\Action\Admin;
use LuceneSearch\Plugin;
use LuceneSearch\Model\Configuration;

class LuceneSearch_Admin_PluginController extends Admin {

    public function init()
    {
        parent::init();
    }

    public function settingsAction()
    {
        $this->view->translate = Plugin::getTranslate();
    }

    public function getStateAction()
    {
        $dings = new \LuceneSearch\Plugin;
        $dings->maintenance();
        exit;

        $frontendButtonDisabled = false;

        if(Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() or !Plugin::frontendConfigComplete())
        {
            $frontendButtonDisabled = true;   
        }

        $message = str_replace("-------------------------------------------- ", "", Plugin::getPluginState());

        $frontendStopButtonDisabled = false;

        if(!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() or Plugin::frontendCrawlerStopLocked() )
        {
            $frontendStopButtonDisabled = true;
        }

        $this->_helper->json(
            array(
                "message" => $message,
                "frontendButtonDisabled" => $frontendButtonDisabled,
                "frontendStopButtonDisabled" => $frontendStopButtonDisabled
            )
        );
    }

    public function stopFrontendCrawlerAction()
    {
        $playNice = true;
        if($this->_getParam("force"))
        {
            $playNice=false;
        }

        $success = Plugin::stopFrontendCrawler($playNice,true);
        $this->_helper->json(array("success" => $success));
    }

    public function startFrontendCrawlerAction()
    {
        Plugin::forceCrawlerStartOnNextMaintenance("frontend");
        $this->_helper->json(array("success" => true));
    }

    public function getFrontendUrlsAction() {

        $urls = Configuration::get("frontend.urls");
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, "url");

        $this->_helper->json(array("urls" => $urlArray));
    }


    public function getFrontendAllowedAction()
    {
        $urls = Configuration::get("frontend.validLinkRegexes");
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, "regex");

        $this->_helper->json(array("allowed" => $urlArray));
    }

    public function getFrontendForbiddenAction()
    {
        $urls = Configuration::get("frontend.invalidLinkRegexesEditable");
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, "regex");

        $this->_helper->json(array("forbidden" => $urlArray));
    }


    public function getFrontendCategoriesAction()
    {
        $urls = explode(",", Configuration::get("frontend.categories"));
        $urlArray = \LuceneSearch\Tool\ConfigParser::parseValues($urls, "category");

        $this->_helper->json(array("categories" => $urlArray));
    }

    public function setConfigAction()
    {
        $values = \Zend_Json::decode($this->_getParam("data"));

        //general settings
        Configuration::set("frontend.enabled", FALSE);

        if(!Configuration::get("frontend.enabled") && $values["search.frontend.enabled"])
        {
            Configuration::set("frontend.enabled", TRUE);
        }

        //frontend settings
        Configuration::set("frontend.ignoreLanguage", FALSE);
        if ($values["search.frontend.ignoreLanguage"])
        {
            Configuration::set("frontend.ignoreLanguage", TRUE);
        }

        Configuration::set("frontend.fuzzySearch", FALSE);
        if ($values["search.frontend.fuzzySearch"])
        {
            Configuration::set("frontend.fuzzySearch", TRUE);
        }

        Configuration::set("frontend.ownHostOnly", FALSE);
        if ($values["search.frontend.ownHostOnly"])
        {
            Configuration::set("frontend.ownHostOnly", TRUE);
        }

        if (is_numeric($values["search.frontend.crawler.maxThreads"]))
        {
            Configuration::set("frontend.crawler.maxThreads", $values["search.frontend.crawler.maxThreads"]);
        }

        if (is_numeric($values["search.frontend.crawler.maxLinkDepth"]))
        {
            Configuration::set("frontend.crawler.maxLinkDepth", $values["search.frontend.crawler.maxLinkDepth"]);

        }
        else
        {
            Configuration::set("frontend.crawler.maxLinkDepth", 15);
        }

        if( !empty($values["search.frontend.categories"]))
        {
            $categories = explode(',', $values["search.frontend.categories"]);
            Configuration::set("frontend.categories", $categories);
        }

        if( !empty($values["search.frontend.urls"]))
        {
            $frontendUrls = explode(',', $values["search.frontend.urls"]);
            Configuration::set("frontend.urls", $frontendUrls);
        }

        if( !empty($values["search.frontend.validLinkRegexes"]))
        {
            $validLinkRegexes = explode(',', $values["search.frontend.validLinkRegexes"]);
            Configuration::set("frontend.validLinkRegexes", $validLinkRegexes);
        }

        if( !empty($values["search.frontend.invalidLinkRegexesEditable"]))
        {
            $invalidLinkRegexesEditable = explode(',', $values["search.frontend.invalidLinkRegexesEditable"]);
            Configuration::set("frontend.invalidLinkRegexesEditable", $invalidLinkRegexesEditable);
        }

        Configuration::set("frontend.crawler.contentStartIndicator", $values["search.frontend.crawler.contentStartIndicator"]);
        Configuration::set("frontend.crawler.contentEndIndicator", $values["search.frontend.crawler.contentEndIndicator"]);

        $this->_helper->json(array("success" => true));

    }

}