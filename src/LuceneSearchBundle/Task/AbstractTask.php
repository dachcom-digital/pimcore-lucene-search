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
    public function __construct(Configuration $configuration, HandlerDispatcher $handlerDispatcher)
    {
        $this->configuration = $configuration;
        $this->handlerDispatcher = $handlerDispatcher;
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
    public function log($message, $level = 'debug', $logToBackend = true, $logToSystem = true)
    {
        $this->logger->log($message, $level, $logToBackend, $logToSystem);
    }

    /**
     * @param bool $isLastCycle
     */
    public function setIsLastCycle($isLastCycle = false)
    {
        $this->isLastCycle = $isLastCycle;
    }

    /**
     * @return bool
     */
    public function isLastCycle()
    {
        return $this->isLastCycle;
    }

    /**
     * @param bool $isLastTask
     */
    public function setIsLastTask($isLastTask = false)
    {
        $this->isLastTask = $isLastTask;

    }

    /**
     * @return bool
     */
    public function isLastTask()
    {
        return $this->isLastTask;
    }

    /**
     * @param bool $isFirstCycle
     */
    public function setIsFirstCycle($isFirstCycle = false)
    {
        $this->isFirstCycle = $isFirstCycle;
    }

    /**
     * @return bool
     */
    public function isFirstCycle()
    {
        return $this->isFirstCycle;
    }

    /**
     * @param bool $isFirstTask
     */
    public function setIsFirstTask($isFirstTask = false)
    {
        $this->isFirstTask = $isFirstTask;

    }

    /**
     * @return bool
     */
    public function isFirstTask()
    {
        return $this->isLastTask;
    }

}