<?php

namespace LuceneSearchBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class LuceneSearchBundle extends AbstractPimcoreBundle
{
    /**
     * {@inheritdoc}
     */
    public function getInstaller()
    {
        return $this->container->get('lucene_search.installer');
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
