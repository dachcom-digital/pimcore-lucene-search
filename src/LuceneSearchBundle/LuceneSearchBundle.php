<?php

namespace LuceneSearchBundle;

use LuceneSearchBundle\DependencyInjection\Compiler\CategoriesPass;
use LuceneSearchBundle\DependencyInjection\Compiler\TaskPass;
use LuceneSearchBundle\Tool\Install;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LuceneSearchBundle extends AbstractPimcoreBundle
{
    use PackageVersionTrait;

    const PACKAGE_NAME = 'dachcom-digital/lucene-search';

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
    public function getInstaller()
    {
        return $this->container->get(Install::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getJsPaths()
    {
        return [
            '/bundles/lucenesearch/js/backend/startup.js',
            '/bundles/lucenesearch/js/backend/settings.js'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCssPaths()
    {
        return [
            '/bundles/lucenesearch/css/admin.css'
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getComposerPackageName(): string
    {
        return self::PACKAGE_NAME;
    }

}
