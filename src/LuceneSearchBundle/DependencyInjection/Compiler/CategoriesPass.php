<?php

namespace LuceneSearchBundle\DependencyInjection\Compiler;

use LuceneSearchBundle\Configuration\Categories\CategoriesInterface;
use LuceneSearchBundle\Configuration\Configuration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CategoriesPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        $categoryServiceName = $container->getParameter('lucene_search.categories');

        if ($container->hasDefinition($categoryServiceName)) {

            $categoriesService = $container->get($categoryServiceName);
            if (!$categoriesService instanceof CategoriesInterface) {
                throw new \Exception(get_class($categoriesService) . ' needs to implement the CategoriesInterface.');
            }

            $container->getDefinition(Configuration::class)->addMethodCall('setCategoryService',
                [new Reference($categoryServiceName)]);
        }
    }
}