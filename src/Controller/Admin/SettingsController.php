<?php

namespace LuceneSearchBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Pimcore\Bundle\AdminBundle\Controller\AdminController;

class SettingsController extends AdminController
{
    /**
     *
     */
    public function getSettingsAction(Request $request)
    {
        $config = new Configuration\Listing();

        $valueArray = [];

        foreach ($config->getConfigurations() as $c) {

            $data = $c->getData();
            $valueArray[$c->getKey()] = $data;
        }

        $frontendButtonDisabled = FALSE;

        if (Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() or !Plugin::frontendConfigComplete()) {
            $frontendButtonDisabled = TRUE;
        }

        $frontendStopButtonDisabled = FALSE;

        if (!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() or Plugin::frontendCrawlerStopLocked()) {
            $frontendStopButtonDisabled = TRUE;
        }

        $response = [
            'values'  => $valueArray,
            'crawler' => [
                'state'    => Plugin::getPluginState(),
                'canStart' => !$frontendButtonDisabled,
                'canStop'  => !$frontendStopButtonDisabled
            ]
        ];

        $this->json($response);
    }

    public function getLogAction()
    {
        $logFile = PIMCORE_WEBSITE_VAR . '/search/log.txt';
        $data = '';

        if (file_exists($logFile)) {
            $data = file_get_contents($logFile);
        }

        $this->_helper->json(['logData' => $data]);
    }

    /**
     *
     */
    public function getStateAction()
    {
        $frontendButtonDisabled = FALSE;

        if (Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerScheduledForStart() || !Plugin::frontendConfigComplete()) {
            $frontendButtonDisabled = TRUE;
        }

        $frontendStopButtonDisabled = FALSE;

        if (!Plugin::frontendConfigComplete() || !Plugin::frontendCrawlerRunning() || Plugin::frontendCrawlerStopLocked()) {
            $frontendStopButtonDisabled = TRUE;
        }

        $this->_helper->json(
            [
                'message'                    => Plugin::getPluginState(),
                'frontendButtonDisabled'     => $frontendButtonDisabled,
                'frontendStopButtonDisabled' => $frontendStopButtonDisabled
            ]
        );
    }

    /**
     *
     */
    public function startFrontendCrawlerAction()
    {
        Plugin::forceCrawlerStartOnNextMaintenance('frontend');
        $this->_helper->json(['success' => TRUE]);
    }

    /**
     *
     */
    public function stopFrontendCrawlerAction()
    {
        $success = Tool\Executer::stopCrawler();
        $this->_helper->json(['success' => $success]);
    }

}
