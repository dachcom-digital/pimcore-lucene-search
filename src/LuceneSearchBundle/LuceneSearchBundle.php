<?php

namespace LuceneSearchBundle;

use LuceneSearchBundle\DependencyInjection\Compiler\CategoriesPass;
use LuceneSearchBundle\DependencyInjection\Compiler\TaskPass;
use LuceneSearchBundle\Tool\Install;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LuceneSearchBundle extends AbstractPimcoreBundle
{
    const BUNDLE_VERSION = '2.0.3';

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TaskPass());
        $container->addCompilerPass(new CategoriesPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return self::BUNDLE_VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstaller()
    {
        return $this->container->get(Install::class);
    }

    /**
     * @return string[]
     */
    public function getJsPaths()
    {
        return [
            '/bundles/lucenesearch/js/backend/startup.js',
            '/bundles/lucenesearch/js/backend/settings.js'
        ];
    }

    public function getCssPaths()
    {
        return [
            '/bundles/lucenesearch/css/admin.css'
        ];
    }
}
