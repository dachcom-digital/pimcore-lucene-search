<?php

namespace LuceneSearch\Console\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Tool;

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
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $currentRevision = NULL;

        if ($input->getArgument('crawl') == 'crawl') {
            $this->output->writeln('<comment>LuceneSearch: Start Crawling</comment>');

            Tool\Executer::runCrawler();
            Tool\Executer::generateSitemap();

            $this->output->writeln('LuceneSearch: Finished crawl');
        }
    }
}