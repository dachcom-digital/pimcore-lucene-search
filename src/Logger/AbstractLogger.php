<?php

namespace LuceneSearchBundle\Logger;

abstract class AbstractLogger
{
    protected $prefix;

    /**
     * @param      $message
     * @param      $level
     * @param bool $logToBackend
     * @param bool $logToSystem
     *
     * @return void
     */
    public function log($message, $level = 'debug', $logToBackend = TRUE, $logToSystem = TRUE)
    {
       \Pimcore\Logger::log($message, $level);
    }

    /**
     * @param $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = '[' . rtrim($prefix) . '] ';
    }

    /**
     * @return mixed
     */
    protected function getPrefix()
    {
        return $this->prefix;
    }
}