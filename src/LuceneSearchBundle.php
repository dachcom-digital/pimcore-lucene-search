<?php

namespace LuceneSearchBundle;

use LuceneSearchBundle\DependencyInjection\Compiler\TaskPass;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LuceneSearchBundle extends AbstractPimcoreBundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TaskPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getInstaller()
    {
        return $this->container->get('lucene_search.tool.installer');
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
