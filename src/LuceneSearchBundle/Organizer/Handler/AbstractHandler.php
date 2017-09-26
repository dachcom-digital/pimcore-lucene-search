<?php

namespace LuceneSearchBundle\Organizer\Handler;

use LuceneSearchBundle\Configuration\Configuration;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\TranslatorInterface;

abstract class AbstractHandler
{
    /**
     * @var Configuration
     */
    protected $configuration;

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
     * @param Configuration       $configuration
     * @param TranslatorInterface $translator
     */
    public function __construct(Configuration $configuration, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->configuration = $configuration;
        $this->fileSystem = new FileSystem();
    }

    /**
     * @todo check locale
     *
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