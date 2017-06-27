<?php

namespace LuceneSearchBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use LuceneSearchBundle\Configuration\Configuration as BundleConfiguration;

use Symfony\Component\Yaml\Yaml;

class LuceneSearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator([__DIR__.'/../Resources/config']));
        $loader->load('services.yml');

        $bundleConfig = Yaml::parse(file_get_contents(BundleConfiguration::SYSTEM_CONFIG_FILE_PATH));

        $configManagerDefinition = $container->getDefinition('lucene_search.configuration');
        $configManagerDefinition->addMethodCall('setConfig', [ $config ]);
        $configManagerDefinition->addMethodCall('setSystemConfig', [ $bundleConfig ]);
    }

}