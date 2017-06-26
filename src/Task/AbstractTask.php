<?php

namespace LuceneSearchBundle\Task;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Logger\AbstractLogger;
use LuceneSearchBundle\Organizer\Dispatcher\HandlerDispatcher;

abstract class AbstractTask implements TaskInterface
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var HandlerDispatcher
     */
    protected $handlerDispatcher;

    /**
     * AbstractLogger
     */
    protected $logger;

    /**
     * array
     */
    protected $options;

    /**
     * Worker constructor.
     *
     * @param Configuration     $configuration
     * @param HandlerDispatcher $handlerDispatcher
     */
    public function __construct(Configuration $configuration, HandlerDispatcher $handlerDispatcher)
    {
        $this->configuration = $configuration;
        $this->handlerDispatcher = $handlerDispatcher;
    }

    public function setLogger(AbstractLogger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setOptions(array $options = [])
    {
        $this->options = $options;
        return $this;
    }

    public function log($message, $level = 'debug', $logToBackend = FALSE, $logToSystem = FALSE)
    {
        $this->logger->log($message, $level, $logToBackend, $logToSystem);
    }
}