<?php

namespace LuceneSearchBundle\Task;

use LuceneSearchBundle\Logger\AbstractLogger;

interface TaskInterface
{
    /**
     * @param AbstractLogger $logger
     *
     * @return mixed
     */
    public function setLogger(AbstractLogger $logger);

    /**
     * @param array $options
     *
     * @return mixed
     */
    public function setOptions(array $options = []);

    /**
     * @return bool
     */
    public function isValid();

    /**
     * @param mixed $processChainData contains data from previous processed task.
     *
     * @return mixed
     */
    public function process($processChainData);

    /**
     * @param      $message
     * @param      $level
     * @param bool $logToBackend
     * @param bool $logToSystem
     *
     * @return mixed
     */
    public function log($message, $level, $logToBackend = true, $logToSystem = true);

    public function setIsLastCycle($isLastCycle = false);

    public function isLastCycle();

    public function setIsLastTask($isLastTask = false);

    public function isLastTask();

    public function setIsFirstCycle($isFirstCycle = false);

    public function isFirstCycle();

    public function setIsFirstTask($istFirstTask = false);

    public function isFirstTask();
}