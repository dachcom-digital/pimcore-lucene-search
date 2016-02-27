<?php

namespace LuceneSearch\Console\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use LuceneSearch\Model\Configuration;
use LuceneSearch\Model\Crawler;

class FrontendCrawlCommand extends AbstractCommand
{
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $currentRevision = null;

        if( $input->getArgument('crawl') == 'crawl' )
        {
            $this->output->writeln("<comment>Start Crawling</comment>");

            \Logger::log("LuceneSearch: Starting crawl", \Zend_Log::DEBUG);

            $indexDir = \LuceneSearch\Plugin::getFrontendSearchIndex();

            $this->output->writeln("<comment>rm -Rf ". str_replace('/index','/tmpindex', $indexDir) ."</comment>");

            //TODO nix specific
            exec("rm -Rf ".str_replace("/index","/tmpindex", $indexDir) );

            $urls = Configuration::get('frontend.urls');
            $validLinkRegexes =  Configuration::get('frontend.validLinkRegexes');
            $invalidLinkRegexes = array( Configuration::get('frontend.invalidLinkRegexes') );

            Configuration::set('frontend.crawler.running', TRUE);
            Configuration::set('frontend.crawler.started', time());

            $crawler = new Crawler($validLinkRegexes, $invalidLinkRegexes,10, 30, Configuration::get('frontend.crawler.contentStartIndicator'),Configuration::get('frontend.crawler.contentEndIndicator'));
            $crawler->findLinks($urls);

            Configuration::set('frontend.crawler.running', FALSE);
            Configuration::set('frontend.crawler.started', time());

            \Logger::log("LuceneSearch_Plugin: replacing old index ...", \Zend_Log::DEBUG);

            //TODO nix specific
            exec("rm -Rf " . $indexDir);
            $tmpIndex = str_replace("/index", "/tmpindex", $indexDir);
            exec("cp -R " . substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1));

            $this->output->writeln("<comment>rm -Rf ". $indexDir ."</comment>");
            $this->output->writeln("<comment>cp -R ". substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1) ."</comment>");

            \Logger::log("Search_PluginPhp: replaced old index", \Zend_Log::DEBUG);
            \Logger::log("Search_PluginPhp: Finished crawl", \Zend_Log::DEBUG);

            $this->output->writeln("LuceneSearch: Finished crawl");

        }
    }
}
