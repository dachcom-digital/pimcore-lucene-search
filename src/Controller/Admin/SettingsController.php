<?php

namespace LuceneSearchBundle\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use LuceneSearchBundle\Config\ConfigManager;
use LuceneSearchBundle\Processor\Organizer\Handler\StateHandler;

class SettingsController extends AdminController
{
    public function getLogAction()
    {
        $logFile = ConfigManager::CRAWLER_LOG_FILE_PATH;
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

        /** @var ConfigManager $configManager */
        $configManager = $this->container->get('lucene_search.config_manager');
        $stateHandler = $this->container->get('lucene_search.organizer.state_handler');
        $currentState = $stateHandler->getCrawlerState();

        $configComplete = $stateHandler->getConfigCompletionState() === 'complete';

        if ($configComplete === FALSE ||
            $currentState === StateHandler::CRAWLER_STATE_ACTIVE ||
            $configManager->getStateConfig('forceStart') === TRUE
        ) {
            $canStart = FALSE;
        }

        $canStop = TRUE;

        if ($configComplete === FALSE ||
            $currentState !== StateHandler::CRAWLER_STATE_ACTIVE ||
            $configManager->getStateConfig('forceStop') === TRUE
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
        $stateHandler = $this->container->get('lucene_search.organizer.state_handler');
        $stateHandler->forceCrawlerStartOnNextMaintenance(TRUE);

        return $this->json(['success' => TRUE]);
    }

    /**
     *
     */
    public function stopCrawlerAction()
    {
        $stateHandler = $this->container->get('lucene_search.organizer.state_handler');
        $stateHandler->stopCrawler(TRUE);

        return $this->json(['success' => TRUE]);
    }

}
