<?php

namespace LuceneSearchBundle\Tool;

use LuceneSearchBundle\LuceneSearchBundle;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;

use Symfony\Component\Filesystem\Filesystem;
use Pimcore\Model\Property;
use LuceneSearchBundle\Configuration\Configuration;
use Symfony\Component\Yaml\Yaml;

class Install extends AbstractInstaller
{
    /**
     * @var string
     */
    private $installSourcesPath;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * Install constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();

        $this->installSourcesPath = __DIR__ . '/../Resources/install';
        $this->fileSystem = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        $this->installOrUpdateConfigFile();
        $this->createDirectories();
        $this->installProperties();
    }

    /**
     * {@inheritdoc}
     */
    public function update()
    {
        $this->installOrUpdateConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        if ($this->fileSystem->exists(Configuration::SYSTEM_CONFIG_FILE_PATH)) {
            $this->fileSystem->remove(Configuration::SYSTEM_CONFIG_FILE_PATH);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled()
    {
        return $this->fileSystem->exists(Configuration::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function canBeInstalled()
    {
        return !$this->fileSystem->exists(Configuration::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function canBeUninstalled()
    {
        return $this->fileSystem->exists(Configuration::SYSTEM_CONFIG_FILE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function needsReloadAfterInstall()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function canBeUpdated()
    {
        $needUpdate = false;
        if ($this->fileSystem->exists(Configuration::SYSTEM_CONFIG_FILE_PATH)) {
            $config = Yaml::parse(file_get_contents(Configuration::SYSTEM_CONFIG_FILE_PATH));
            if ($config['version'] !== LuceneSearchBundle::BUNDLE_VERSION) {
                $needUpdate = true;
            }
        }

        return $needUpdate;
    }

    /**
     * install or update config file
     */
    private function installOrUpdateConfigFile()
    {
        if (!$this->fileSystem->exists(Configuration::SYSTEM_CONFIG_DIR_PATH)) {
            $this->fileSystem->mkdir(Configuration::SYSTEM_CONFIG_DIR_PATH);
        }

        $config = ['version' => LuceneSearchBundle::BUNDLE_VERSION];
        $yml = Yaml::dump($config);
        file_put_contents(Configuration::SYSTEM_CONFIG_FILE_PATH, $yml);

        if (!$this->fileSystem->exists(Configuration::STATE_FILE_PATH)) {
            $content = serialize(Configuration::STATE_DEFAULT_VALUES);
            $this->fileSystem->appendToFile(Configuration::STATE_FILE_PATH, $content);
        }

    }

    /**
     * @return bool
     */
    public function createDirectories()
    {
        if (!$this->fileSystem->exists(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH)) {
            $this->fileSystem->mkdir(Configuration::CRAWLER_PERSISTENCE_STORE_DIR_PATH, 0755);
        }

        if (!$this->fileSystem->exists(Configuration::INDEX_DIR_PATH)) {
            $this->fileSystem->mkdir(Configuration::INDEX_DIR_PATH, 0755);
        }

        if (!$this->fileSystem->exists(Configuration::INDEX_DIR_PATH_STABLE)) {
            $this->fileSystem->mkdir(Configuration::INDEX_DIR_PATH_STABLE, 0755);
        }

        if (!$this->fileSystem->exists(Configuration::INDEX_DIR_PATH_GENESIS)) {
            $this->fileSystem->mkdir(Configuration::INDEX_DIR_PATH_GENESIS, 0755);
        }

        return true;
    }

    public function installProperties()
    {
        $propertiesToInstall = [
            'assigned_language' => [
                'name'        => 'Assigned Language',
                'description' => 'Set a specific language which lucene search should respect while crawling.'
            ],
            'assigned_country'  => [
                'name'        => 'Assigned Country',
                'description' => 'Set a specific country which lucene search should respect while crawling.'
            ]
        ];

        foreach ($propertiesToInstall as $propertyKey => $propertyData) {

            $defProperty = Property\Predefined::getByKey($propertyKey);

            if (!$defProperty instanceof Property\Predefined) {

                $data = 'all,';
                if ($propertyKey === 'assigned_language') {
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
                $property->setInheritable(false);
                $property->save();
            }
        }

    }

}