<?php

namespace LuceneSearchBundle\Tool;

use Pimcore\Config;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;

use Symfony\Component\Filesystem\Filesystem;
use Pimcore\Model\Property;
use Psr\Log\LoggerInterface;
use LuceneSearchBundle\Service\ConfigManager;

class Install extends AbstractInstaller
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $installSourcesPath;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->installSourcesPath = __DIR__ . '/../Resources/install';
        $this->fileSystem = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        $this->copyConfigFiles();
        $this->createDirectories();
        $this->installProperties();

        return TRUE;
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        if ($this->fileSystem->exists(ConfigManager::SYSTEM_CONFIG_FILE_PATH)) {
            $this->fileSystem->rename(
                ConfigManager::SYSTEM_CONFIG_FILE_PATH,
                PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/config_backup.yml'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled()
    {
        return $this->fileSystem->exists(ConfigManager::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function canBeInstalled()
    {
        return !$this->fileSystem->exists(ConfigManager::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function canBeUninstalled()
    {
        return $this->fileSystem->exists(ConfigManager::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function needsReloadAfterInstall()
    {
        return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function canBeUpdated()
    {
        return FALSE;
    }

    /**
     * copy sample config file - if not exists.
     */
    private function copyConfigFiles()
    {
        if (!$this->fileSystem->exists(ConfigManager::SYSTEM_CONFIG_FILE_PATH)) {
            $this->fileSystem->copy(
                $this->installSourcesPath . '/config.yml',
                ConfigManager::SYSTEM_CONFIG_FILE_PATH
            );
        }

        if (!$this->fileSystem->exists(ConfigManager::STATE_FILE_PATH)) {
            $content = serialize(ConfigManager::STATE_DEFAULT_VALUES);
            $this->fileSystem->appendToFile(ConfigManager::STATE_FILE_PATH, $content);
        }

    }

    /**
     * @return bool
     */
    public function createDirectories()
    {
        if (!$this->fileSystem->exists(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->fileSystem->mkdir(ConfigManager::CRAWLER_PERSISTENCE_STORE_DIR_PATH, 0755);
        }

        if (!$this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH)) {
            $this->fileSystem->mkdir(ConfigManager::INDEX_DIR_PATH, 0755);
        }

        if (!$this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_STABLE)) {
            $this->fileSystem->mkdir(ConfigManager::INDEX_DIR_PATH_STABLE, 0755);
        }

        if (!$this->fileSystem->exists(ConfigManager::INDEX_DIR_PATH_GENESIS)) {
            $this->fileSystem->mkdir(ConfigManager::INDEX_DIR_PATH_GENESIS, 0755);
        }

        return TRUE;
    }

    public function installProperties()
    {
        $propertiesToInstall = [
            'assigned_language' => [
                'name' => 'Assigned Language',
                'description' => 'Set a specific language which lucene search should respect while crawling.'
            ],
            'assigned_country' => [
                'name' => 'Assigned Country',
                'description' => 'Set a specific country which lucene search should respect while crawling.'
            ]
        ];

        foreach($propertiesToInstall as $propertyKey => $propertyData) {

            $defProperty = Property\Predefined::getByKey($propertyKey);

            if (!$defProperty instanceof Property\Predefined) {

                $data = 'all,';
                if($propertyKey === 'assigned_language') {
                    $languages = \Pimcore\Tool::getValidLanguages();
                    foreach ($languages as $language) {
                        $data .= $language . ',';
                    }
                }

                $data = rtrim($data, ',');

                $property = new Property\Predefined();
                $property->setType('select');
                $property->setName($propertyData['name']);
                $property->setKey($propertyKey);
                $property->setDescription($propertyData['description']);
                $property->setCtype('asset');
                $property->setData('all');
                $property->setConfig($data);
                $property->setInheritable(FALSE);
                $property->save();
            }
        }

    }

}