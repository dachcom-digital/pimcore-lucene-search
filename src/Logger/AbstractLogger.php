<?php

namespace LuceneSearchBundle\Logger;

abstract class AbstractLogger
{
    /**
     * @param      $message
     * @param      $level
     * @param bool $logToBackend
     * @param bool $logToSystem
     *
     * @return void
     */
    public function log($message, $level = 'debug', $logToBackend = FALSE, $logToSystem = FALSE)
    {
       \Pimcore\Logger::log($message, $level);
    }
}