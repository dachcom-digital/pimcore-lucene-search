<?php

namespace LuceneSearchBundle\DependencyInjection\Compiler;

use LuceneSearchBundle\Configuration\Categories\CategoriesInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
            if(!$categoriesService instanceof CategoriesInterface) {
                throw new \Exception(get_class($categoriesService) . ' needs to implement the CategoriesInterface.');
            }

            $categories = $categoriesService->getCategories();
            $container->getDefinition('lucene_search.configuration')->addMethodCall('setCategories', [$categories]);
        }
    }
}