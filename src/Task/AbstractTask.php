<?php

namespace LuceneSearchBundle\Task;

use LuceneSearchBundle\Configuration\Configuration;
use LuceneSearchBundle\Connector\BundleConnector;
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
     * @var BundleConnector
     */
    protected $bundleConnector;

    /**
     * AbstractLogger
     */
    protected $logger;

    /**
     * array
     */
    protected $options;

    /**
     * @var bool
     */
    protected $isLastCycle;

    /**
     * @var bool
     */
    protected $isLastTask;

    /**
     * @var bool
     */
    protected $isFirstCycle;

    /**
     * @var bool
     */
    protected $isFirstTask;

    /**
     * Worker constructor.
     *
     * @param Configuration     $configuration
     * @param HandlerDispatcher $handlerDispatcher
     */
    public function __construct(Configuration $configuration, HandlerDispatcher $handlerDispatcher, BundleConnector $bundleConnector)
    {
        $this->configuration = $configuration;
        $this->handlerDispatcher = $handlerDispatcher;
        $this->bundleConnector = $bundleConnector;
    }

    /**
     * @param AbstractLogger $logger
     *
     * @return $this
     */
    public function setLogger(AbstractLogger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options = [])
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param        $message
     * @param string $level
     * @param bool   $logToBackend
     * @param bool   $logToSystem
     *
     * @return void
     */
    public function log($message, $level = 'debug', $logToBackend = TRUE, $logToSystem = TRUE)
    {
        $this->logger->log($message, $level, $logToBackend, $logToSystem);
    }

    /**
     * @param bool $isLastCycle
     */
    public function setIsLastCycle($isLastCycle = FALSE) {
        $this->isLastCycle = $isLastCycle;
    }

    /**
     * @return bool
     */
    public function isLastCycle() {
        return $this->isLastCycle;
    }

    /**
     * @param bool $isLastTask
     */
    public function setIsLastTask($isLastTask = FALSE) {
        $this->isLastTask = $isLastTask;

    }

    /**
     * @return bool
     */
    public function isLastTask() {
        return $this->isLastTask;
    }


    /**
     * @param bool $isFirstCycle
     */
    public function setIsFirstCycle($isFirstCycle = FALSE) {
        $this->isFirstCycle = $isFirstCycle;
    }

    /**
     * @return bool
     */
    public function isFirstCycle() {
        return $this->isFirstCycle;
    }

    /**
     * @param bool $isFirstTask
     */
    public function setIsFirstTask($isFirstTask = FALSE) {
        $this->isFirstTask = $isFirstTask;

    }

    /**
     * @return bool
     */
    public function isFirstTask() {
        return $this->isLastTask;
    }

}