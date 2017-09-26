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

    /**
     *
     */
    public function getStateAction()
    {
        $canStart = TRUE;

        /** @var Configuration $configManager */
        $configManager = $this->container->get(Configuration::class);
        /** @var StateHandler $stateHandler */
        $stateHandler = $this->container->get(StateHandler::class);
        $currentState = $stateHandler->getCrawlerState();

        $configComplete = $stateHandler->getConfigCompletionState() === 'complete';

        if ($configComplete === FALSE ||
            $currentState === StateHandler::CRAWLER_STATE_ACTIVE ||
            $stateHandler->isCrawlerInForceStart() === TRUE
        ) {
            $canStart = FALSE;
        }

        $canStop = TRUE;

        if ($configComplete === FALSE ||
            $currentState !== StateHandler::CRAWLER_STATE_ACTIVE ||
            $stateHandler->isCrawlerInForceStop() === TRUE
        ) {
            $canStop = FALSE;
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

    /**
     *
     */
    public function startCrawlerAction()
    {
        $stateHandler = $this->container->get(StateHandler::class);
        $stateHandler->forceCrawlerStartOnNextMaintenance(TRUE);

        return $this->json(['success' => TRUE]);
    }

    /**
     *
     */
    public function stopCrawlerAction()
    {
        $stateHandler = $this->container->get(StateHandler::class);
        $stateHandler->stopCrawler(TRUE);

        return $this->json(['success' => TRUE]);
    }

}
