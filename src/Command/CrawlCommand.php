<?php

namespace LuceneSearchBundle\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('lucenesearch')
            ->setDescription('LuceneSearch Website Crawler')
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
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $currentRevision = NULL;

        /** @var \LuceneSearchBundle\Processor\Processor $processor */
        $processor = $this->getContainer()->get('lucene_search.processor');

        if ($input->getArgument('crawl') === 'crawl') {

            $this->output->writeln('<comment>LuceneSearch: Start Crawling</comment>');

            $processor->addLogOutput($output);
            $processor->runCrawler($input->getOption('force'));

            //Tool\Executer::generateSitemap();

            $this->output->writeln('LuceneSearch: Finished crawl');
        }

    }

}