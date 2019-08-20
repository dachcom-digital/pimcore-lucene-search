<?php

namespace LuceneSearchBundle\EventListener;

use LuceneSearchBundle\Modifier\QueuedDocumentModifier;
use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Organizer\Dispatcher\HandlerDispatcher;
use Pimcore\Maintenance\TaskInterface;

class MaintenanceQueueListener implements TaskInterface
{
    /**
     * @var HandlerDispatcher
     */
    protected $handlerDispatcher;

    /**
     * @var QueuedDocumentModifier
     */
    protected $queuedDocumentModifier;

    /**
     * @param HandlerDispatcher      $handlerDispatcher
     * @param QueuedDocumentModifier $queuedDocumentModifier
     */
    public function __construct(HandlerDispatcher $handlerDispatcher, QueuedDocumentModifier $queuedDocumentModifier)
    {
        $this->handlerDispatcher = $handlerDispatcher;
        $this->queuedDocumentModifier = $queuedDocumentModifier;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $mainCrawlerIsActive = $this->handlerDispatcher->getStateHandler()->getCrawlerState() === StateHandler::CRAWLER_STATE_ACTIVE;

        // new index is on its way. wait for new index arrival.
        if ($mainCrawlerIsActive === true) {
            return;
        }

        $this->queuedDocumentModifier->resolveQueue();
    }
}