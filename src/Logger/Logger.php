<?php

namespace LuceneSearchBundle\Logger;

use LuceneSearchBundle\Configuration\Configuration;

class Logger extends AbstractLogger
{
    /**
     * @var bool
     */
    var $backendLog = TRUE;

    /**
     * @param      $message
     * @param      $level
     * @param bool $logToBackend
     * @param bool $logToSystem
     *
     * @return bool
     */
    public function log($message, $level = 'debug', $logToBackend = FALSE, $logToSystem = FALSE)
    {
        $this->addToBackendLog($message, $level, $logToBackend, $logToSystem);

        return TRUE;
    }

    /**
     * @param string $message
     * @param string $level
     * @param bool   $addToBackendLog
     * @param bool   $addToSystemLog
     *
     * @return bool
     */
    public function addToBackendLog($message = '', $level = 'debug', $addToBackendLog = TRUE, $addToSystemLog = TRUE)
    {
        if ($addToSystemLog === TRUE) {
            \Pimcore\Logger::log('LuceneSearch: ' . $message, $this->getRealLevel($level));
        }

        if ($addToBackendLog === TRUE) {
            $file = Configuration::CRAWLER_LOG_FILE_PATH;
            $current = '';
            if (file_exists($file)) {
                $current = file_get_contents($file);
            }
            $current .= date('d.m.Y H:i') . '|' . $this->getRealLevel($level) . '|' . $message . "\n";
            file_put_contents($file, $current);
        }

        return TRUE;
    }

    /**
     * @param $level
     *
     * @return string
     */
    private function getRealLevel($level)
    {
        if ($level === 'debugHighlight') {
            return 'debug';
        }

        return $level;
    }
}