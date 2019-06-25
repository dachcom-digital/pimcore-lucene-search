<?php

namespace LuceneSearchBundle\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Organizer\Handler\StateHandler;

class SettingsController extends AdminController
{
    public function getLogAction()
    {
        $logFile = Configuration::CRAWLER_LOG_FILE_PATH;
        $data = '';

        if (file_exists($logFile)) {
            $data = file_get_contents($logFile);
        }

        return $this->json(['logData' => $data]);
    }

    public function getStateAction(Configuration $configManager, StateHandler $stateHandler)
    {
        $canStart = true;

        $currentState = $stateHandler->getCrawlerState();

        $configComplete = $stateHandler->getConfigCompletionState() === 'complete';

        if ($configComplete === false ||
            $currentState === StateHandler::CRAWLER_STATE_ACTIVE ||
            $stateHandler->isCrawlerInForceStart() === true
        ) {
            $canStart = false;
        }

        $canStop = true;

        if ($configComplete === false ||
            $currentState !== StateHandler::CRAWLER_STATE_ACTIVE ||
            $stateHandler->isCrawlerInForceStop() === true
        ) {
            $canStop = false;
        }

        return $this->json(
            [
                'state'    => $stateHandler->getCrawlerStateDescription(),
                'enabled'  => $configManager->getConfig('enabled'),
                'canStart' => $canStart,
                'canStop'  => $canStop
            ]
        );
    }

    public function startCrawlerAction(StateHandler $stateHandler)
    {
        $stateHandler->forceCrawlerStartOnNextMaintenance();

        return $this->json(['success' => true]);
    }

    public function stopCrawlerAction(StateHandler $stateHandler)
    {
        $stateHandler->stopCrawler(true);

        return $this->json(['success' => true]);
    }

}
