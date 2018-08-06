<?php

namespace LuceneSearchBundle\Task;

use LuceneSearchBundle\Logger\AbstractLogger;

class TaskManager
{
    /**
     * @var array
     */
    private $tasks;

    /**
     * @var
     */
    public $logger;

    /**
     * @var
     */
    public $taskIterators = [];

    /**
     * TaskManager constructor.
     */
    public function __construct()
    {
        $this->tasks = [];
    }

    /**
     * @param AbstractTask $task
     */
    public function addTask($task, $id)
    {
        $this->tasks[] = ['id' => $id, 'task' => $task];
    }

    /**
     * @param AbstractLogger $logger
     */
    public function setLogger(AbstractLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $taskIterators
     */
    public function setTaskIterators(array $taskIterators)
    {
        $this->taskIterators = $taskIterators;
    }

    /**
     * @param array $options
     *
     * @return bool
     * @throws \Exception
     */
    public function processTaskChain($options = [])
    {
        $processData = [];

        if (empty($this->taskIterators)) {
            throw new \Exception('no valid task iterators defined!');
        }

        foreach ($this->taskIterators as $iteratorIndex => $iterator) {

            foreach ($this->tasks as $taskIndex => $task) {

                /** @var AbstractTask $taskClass */
                $taskClass = $task['task'];

                $options['iterator'] = $iterator;

                $taskClass->setIsFirstCycle($iteratorIndex == 0);
                $taskClass->setIsFirstTask($taskIndex == 0);
                $taskClass->setIsLastCycle($iteratorIndex === count($this->taskIterators) - 1);
                $taskClass->setIsLastTask($taskIndex === count($this->tasks) - 1);
                $taskClass->setOptions($options);

                if ($taskClass->isValid()) {
                    $taskClass->setLogger($this->logger);
                    $processData = $taskClass->process($processData);
                } else {
                    $this->logger->log('There was an error while processing task (' . $task['id'] . '). please check your logs.');
                    exit;
                }
            }
        }

        return true;
    }
}