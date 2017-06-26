<?php

namespace LuceneSearchBundle\Logger;

use Symfony\Component\Console\Output;

class ConsoleLogger extends Logger
{
    /**
     * @var bool
     */
    var $consoleOutput = FALSE;

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
    public function log($message, $level = 'debug', $logToBackend = FALSE, $logToSystem = FALSE)
    {
        parent::log($message, $level, $logToBackend, $logToSystem);

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
}