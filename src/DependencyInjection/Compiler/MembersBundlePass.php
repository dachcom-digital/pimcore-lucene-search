<?php

namespace LuceneSearchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MembersBundlePass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('members.manager.restriction')) {
            return;
        }

        $luceneSearchBundleConnector = $container->getDefinition('lucene_search.connector.bundle');
        foreach($this->getRequiredServices() as $service) {
            $luceneSearchBundleConnector->addMethodCall(
                'registerBundleService',
                [$service, new Reference($service)]
            );
        }
    }

    /**
     * @return array
     */
    private function getRequiredServices()
    {
        return [
            'members.security.restriction.uri',

        ];
    }
}