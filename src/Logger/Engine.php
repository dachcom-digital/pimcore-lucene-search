<?php

namespace LuceneSearchBundle\Logger;

use Symfony\Component\Console\Output;
use LuceneSearchBundle\Config\ConfigManager;

class Engine
{
    /**
     * @var bool
     */
    var $consoleOutput = FALSE;

    /**
     * @var bool
     */
    var $backendLog = TRUE;

    /**
     * @var int
     */
    var $verbosity = Output\OutputInterface::VERBOSITY_QUIET;

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function setConsoleOutput($output)
    {
        $this->consoleOutput = $output;
        $this->verbosity = $output->getVerbosity();
    }

    /**
     * @param      $message
     * @param      $level
     * @param bool $logToBackend
     * @param bool $logToSystem
     *
     * @return bool
     */
    public function log($message, $level, $logToBackend = FALSE, $logToSystem = FALSE)
    {
        $this->addToBackendLog($message, $level, $logToBackend, $logToSystem);
        $this->addToConsoleLog($message, $level);

        return TRUE;
    }

    /**
     * print some lines to console if available
     *
     * @param $message
     * @param $level
     *
     * @return bool
     */
    protected function addToConsoleLog($message, $level = 'debug')
    {
        if (!$this->consoleOutput instanceof Output\OutputInterface) {
            return FALSE;
        }

        if ($this->verbosity !== Output\OutputInterface::VERBOSITY_VERBOSE) {
            return FALSE;
        }

        $debugLevel = 'fg=white';
        if ($level === 'debug') {
            $debugLevel = 'fg=white';
        } else if ($level === 'debugHighlight') {
            $debugLevel = 'comment';
        } else if ($level === 'info') {
            $debugLevel = 'comment';
        } else if ($level === 'error') {
            $debugLevel = 'error';
        }

        $string = sprintf('<%s>' . str_replace('%', '%%',$message). '</%s>', $debugLevel, $debugLevel);
        $this->consoleOutput->writeln($string, $this->verbosity);
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
            $file = ConfigManager::CRAWLER_LOG_FILE_PATH;
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