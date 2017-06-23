<?php

namespace LuceneSearchBundle\Processor;

use Symfony\Component\Filesystem\Filesystem;
use LuceneSearchBundle\Processor\Organizer\Dispatcher\HandlerDispatcher;
use LuceneSearchBundle\Processor\Organizer\Handler\StateHandler;
use LuceneSearchBundle\Logger\Engine;
use LuceneSearchBundle\Config\ConfigManager;

use LuceneSearchBundle\Processor\Crawler\Crawler;
use LuceneSearchBundle\Processor\Parser\Parser;

class Processor
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var Engine
     */
    protected $logger;

    /**
     * @var HandlerDispatcher
     */
    protected $handlerDispatcher;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var string
     */
    protected $contextType = NULL;

    /**
     * Worker constructor.
     *
     * @param ConfigManager     $configManager
     * @param Engine            $logger
     * @param HandlerDispatcher $handlerDispatcher
     */
    public function __construct(ConfigManager $configManager, Engine $logger, HandlerDispatcher $handlerDispatcher)
    {
        $this->configManager = $configManager;
        $this->logger = $logger;
        $this->handlerDispatcher = $handlerDispatcher;
        $this->fileSystem = new Filesystem();

        $this->parser = new Parser();
    }

    /**
     * @param $output
     */
    public function addLogOutput($output)
    {
        $this->logger->setConsoleOutput($output);
    }

    /**
     * @param bool $force
     *
     * @return bool
     * @throws \Exception
     */
    public function runCrawler($force = FALSE)
    {
        if ($this->handlerDispatcher->getStateHandler()->getCrawlerState() == StateHandler::CRAWLER_STATE_ACTIVE) {
            if ($force === TRUE) {
                $this->handlerDispatcher->getStateHandler()->stopCrawler(TRUE);
            } else {
                return FALSE;
            }
        }

        $this->prepareCrawlerStart();

        $this->parser->setLogger($this->logger);
        $this->parser->setDocumentBoost($this->configManager->getConfig('boost:documents'));
        $this->parser->setAssetBoost($this->configManager->getConfig('boost:assets'));
        $this->parser->setAssetTmpDir(ConfigManager::CRAWLER_TMP_ASSET_DIR_PATH);

        try {

            foreach ($this->getSeeds() as $seed) {

                $crawler = new Crawler();
                $crawler->setLogger($this->logger);

                $crawler
                    ->setAllowSubdomain(FALSE)
                    ->setDepth($this->configManager->getConfig('crawler:max_link_depth'))
                    ->setValidLinks($this->configManager->getConfig('filter:valid_links'))
                    ->setInvalidLinks($this->getInvalidLinks())
                    ->setContentMaxSize($this->configManager->getConfig('crawler:content_max_size'))
                    ->setSearchStartIndicator($this->configManager->getConfig('crawler:content_start_indicator'))
                    ->setSearchEndIndicator($this->configManager->getConfig('crawler:content_end_indicator'))
                    ->setSearchExcludeStartIndicator($this->configManager->getConfig('crawler:content_exclude_start_indicator'))
                    ->setSearchExcludeEndIndicator($this->configManager->getConfig('crawler:content_exclude_end_indicator'))
                    ->setValidMimeTypes($this->configManager->getConfig('allowed_mime_types'))
                    ->setAllowedSchemes($this->configManager->getConfig('allowed_schemes'))
                    ->setDownloadLimit($this->configManager->getConfig('crawler:max_download_limit'))
                    ->setSeed($seed);

                if ($this->configManager->getConfig('auth:use_auth') === TRUE) {
                    $crawler->setAuth($this->configManager->getConfig('auth:username'), $this->configManager->getConfig('auth:password'));
                }

                $crawlData = $crawler->fetchCrawlerResources();

                //parse all resources!
                /** @var \VDB\Spider\Resource $resource */
                foreach ($crawlData as $resource) {
                    if ($resource instanceof \VDB\Spider\Resource) {
                        $this->parser->parseResponse($resource);
                    } else {
                        //$this->log('[crawler] crawler resource not a instance of \VDB\Spider\Resource. Given type: ' . gettype($resource), 'notice');
                    }
                }

            }

            $this->parser->optimizeIndex();

            $this->prepareCrawlerStop();

        } catch (\Exception $e) {
            \Pimcore\Logger::error($e);
            throw $e;
        }
    }

    private function getSeeds()
    {
        $seeds = $this->configManager->getConfig('seeds');

        return $seeds;
    }

    private function getInvalidLinks()
    {
        $userInvalidLinks = $this->configManager->getConfig('filter:user_invalid_links');
        $coreInvalidLinks = $this->configManager->getConfig('filter:core_invalid_links');

        if (!empty($userInvalidLinks) && !empty($coreInvalidLinks)) {
            $invalidLinkRegex = array_merge($userInvalidLinks, [$coreInvalidLinks]);
        } else if (!empty($userInvalidLinks)) {
            $invalidLinkRegex = $userInvalidLinks;
        } else if (!empty($coreInvalidLinks)) {
            $invalidLinkRegex = [$coreInvalidLinks];
        } else {
            $invalidLinkRegex = [];
        }

        return $invalidLinkRegex;
    }

    private function prepareCrawlerStart()
    {
        $this->handlerDispatcher->getStoreHandler()->resetGenesisIndex();
        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();
        $this->handlerDispatcher->getStoreHandler()->resetLogs();

        $this->handlerDispatcher->getStateHandler()->startCrawler();
    }

    private function prepareCrawlerStop()
    {
        $this->handlerDispatcher->getStoreHandler()->resetPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->resetUriFilterPersistenceStore();
        $this->handlerDispatcher->getStoreHandler()->riseGenesisToStable();
        $this->handlerDispatcher->getStoreHandler()->resetAssetTmp();

        $this->handlerDispatcher->getStateHandler()->stopCrawler();
    }

}