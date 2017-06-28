<?php

namespace LuceneSearchBundle\Twig\Extension;

use LuceneSearchBundle\Configuration\Configuration;

class CategoriesExtension extends \Twig_Extension
{
    /**
     * @var Configuration
     */
    var $configuration;

    /**
     * CategoriesExtension constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('lucene_search_get_categories', [$this, 'getCategoriesList'])
        ];
    }

    /**
     * @param null $options
     *
     * @return array
     */
    public function getCategoriesList($options = NULL)
    {
        $categories = $this->configuration->getCategories();
        return $categories;
    }
}