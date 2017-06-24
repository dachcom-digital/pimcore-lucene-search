<?php

namespace LuceneSearchBundle\Processor\Organizer\Handler;

use LuceneSearchBundle\Config\ConfigManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\TranslatorInterface;

abstract class AbstractHandler
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * AbstractHandler constructor.
     *
     * @param ConfigManager       $configManager
     * @param TranslatorInterface $translator
     */
    public function __construct(ConfigManager $configManager, TranslatorInterface $translator)
    {
        $this->configManager = $configManager;
        $this->translator   = $translator;

        $this->fileSystem = new FileSystem();
    }

    /**
     * @todo check locale
     * @param $key
     *
     * @return mixed
     */
    protected function getTranslation($key)
    {
        $translationCatalog = $this->translator->getCatalogue('en');
        $translations = $translationCatalog->get($key, 'admin');

        return $translations;
    }

}