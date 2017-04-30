<?php

namespace LuceneSearch\Console\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use LuceneSearch\Tool;
use LuceneSearch\Model\Logger\Engine;

class FrontendCrawlCommand extends AbstractCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('lucenesearch:frontend:crawl')
            ->setDescription('LuceneSearch Frontend Crawl')
            ->addArgument(
                'crawl',
                InputArgument::OPTIONAL,
                'Crawl Website Pages with LuceneSearch.'
            )->addOption('force', 'f',
            InputOption::VALUE_NONE,
                'Force Crawl Start');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return NULL
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $currentRevision = NULL;

        if ($input->getArgument('crawl') == 'crawl') {

            $this->output->writeln('<comment>LuceneSearch: Start Crawling</comment>');

            $logEngine = new Engine();
            $logEngine->setConsoleOutput($output);

            Tool\Executer::runCrawler($logEngine, $input->getOption('force'));
            Tool\Executer::generateSitemap();

            $this->output->writeln('LuceneSearch: Finished crawl');
        }

        return NULL;
    }
}