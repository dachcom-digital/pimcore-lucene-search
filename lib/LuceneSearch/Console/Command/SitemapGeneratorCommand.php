<?php

namespace LuceneSearch\Console\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use LuceneSearch\Tool;
use LuceneSearch\Model\Logger\Engine;

class SitemapGeneratorCommand extends AbstractCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('lucenesearch:sitemap')
            ->setDescription('LuceneSearch Sitemap Generator')
            ->addArgument(
                'generate',
                InputArgument::OPTIONAL,
                'Generate Sitemap with LuceneSearch.'
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

        if ($input->getArgument('generate') == 'generate') {
            $this->output->writeln('<comment>LuceneSearch: Sitemap Generator</comment>');
            Tool\Executer::generateSiteMap();
            $this->output->writeln('LuceneSearch: Sitemap finished');
        }

        return NULL;
    }
}