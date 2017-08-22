<?php

namespace LuceneSearchBundle\Organizer\Dispatcher;

use LuceneSearchBundle\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Organizer\Handler\StoreHandler;

class HandlerDispatcher
{
    /**
     * @var StateHandler
     */
    protected $stateHandler;

    /**
     * @var StoreHandler
     */
    protected $storeHandler;

    /**
     * Dispatcher constructor.
     *
     * @param StateHandler $stateHandler
     * @param StoreHandler $storeHandler
     */
    public function __construct(StateHandler $stateHandler, StoreHandler $storeHandler)
    {
        $this->stateHandler = $stateHandler;
        $this->storeHandler = $storeHandler;
    }

    /**
     * @return StateHandler
     */
    public function getStateHandler() {
        return $this->stateHandler;
    }

    /**
     * @return StoreHandler
     */
    public function getStoreHandler() {
        return $this->storeHandler;
    }

}