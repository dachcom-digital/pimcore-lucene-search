<?php

namespace LuceneSearchBundle\Logger;

use Symfony\Component\Console\Output;

class ConsoleLogger extends Logger
{
    /**
     * @var bool
     */
    var $consoleOutput = false;

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
     * @return void
     */
    public function log($message, $level = 'debug', $logToBackend = true, $logToSystem = true)
    {
        parent::log($message, $level, $logToBackend, $logToSystem);
        $this->addToConsoleLog($message, $level);
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
            return false;
        }

        if ($this->verbosity !== Output\OutputInterface::VERBOSITY_VERBOSE) {
            return false;
        }

        $message = $this->getPrefix() . $message;

        $debugLevel = 'fg=white';
        if ($level === 'debug') {
            $debugLevel = 'fg=white';
        } elseif ($level === 'debugHighlight') {
            $debugLevel = 'comment';
        } elseif ($level === 'info') {
            $debugLevel = 'comment';
        } elseif ($level === 'error') {
            $debugLevel = 'error';
        }

        $string = sprintf('<%s>' . str_replace('%', '%%', $message) . '</%s>', $debugLevel, $debugLevel);
        $this->consoleOutput->writeln($string, $this->verbosity);
    }
}