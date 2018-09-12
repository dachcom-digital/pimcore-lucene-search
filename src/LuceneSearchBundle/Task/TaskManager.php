<?php

namespace LuceneSearchBundle\Task;

use LuceneSearchBundle\Doctrine\DBAL\ConnectionKeepAlive;
use LuceneSearchBundle\Logger\AbstractLogger;

class TaskManager
{
    /**
     * @var array
     */
    protected $tasks;

    /**
     * @var
     */
    protected $logger;

    /**
     * @var
     */
    protected $taskIterators = [];

    /**
     * @var ConnectionKeepAlive
     */
    protected $keepAlive;

    /**
     * TaskManager constructor.
     */
    public function __construct()
    {
        $this->tasks = [];
    }

    /**
     * @param $task
     * @param $id
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
     * @throws \Exception
     */
    public function processTaskChain($options = [])
    {
        $processData = [];

        if (empty($this->taskIterators)) {
            throw new \Exception('no valid task iterators defined!');
        }

        $this->bootChain();

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
                    $this->shutDownChain();
                    $this->logger->log('There was an error while processing task (' . $task['id'] . '). please check your logs.');
                    exit;
                }
            }
        }

        $this->shutDownChain();
    }

    private function bootChain()
    {
        \Pimcore::collectGarbage();

        $this->keepAlive = new ConnectionKeepAlive();
        $this->keepAlive->addConnection(\Pimcore\Db::getConnection());
        $this->keepAlive->attach();
    }

    private function shutDownChain()
    {
        $this->keepAlive->detach();
    }
}