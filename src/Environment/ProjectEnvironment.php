<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Environment;

use Puli\Discovery\ResourceDiscovery;
use Puli\Repository\ResourceRepository;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Config\ConfigFile\ConfigFileStorage;
use Puli\RepositoryManager\Config\DefaultConfig;
use Puli\RepositoryManager\Config\EnvConfig;
use Puli\RepositoryManager\FileNotFoundException;
use Puli\RepositoryManager\Generator\RegistryGenerator;
use Puli\RepositoryManager\NoDirectoryException;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;
use Puli\RepositoryManager\Package\PackageFile\RootPackageFile;
use Puli\RepositoryManager\Plugin\ManagerPlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

/**
 * The environment of a Puli project.
 *
 * This class contains both global environment information (see
 * {@link GlobalEnvironment}) and information local to a Puli project. It
 * provides access to the project's root directory and the root puli.json
 * of the project.
 *
 * Use {@link getConfig()} to access the project configuration.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ProjectEnvironment extends GlobalEnvironment
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RootPackageFile
     */
    private $rootPackageFile;

    /**
     * @var ResourceRepository
     */
    private $repository;

    /**
     * @var ResourceDiscovery
     */
    private $discovery;

    /**
     * Creates the project environment.
     *
     * The passed home directory will be be scanned for a file "config.json".
     * If that file exists, it is loaded into memory. Use {@link getConfig()} to
     * access the project configuration.
     *
     * The passed root directory will be be scanned for a file "puli.json".
     * If that file exists, it is loaded into memory. Use
     * {@link getRootPackageFile()} to access the package file.
     *
     * @param string                   $homeDir            The path to Puli's home directory.
     * @param string                   $rootDir            The path to the project directory.
     * @param ConfigFileStorage        $configFileStorage  The global config file storage.
     * @param PackageFileStorage       $packageFileStorage The package file storage.
     * @param EventDispatcherInterface $dispatcher         The event dispatcher.
     *
     * @throws FileNotFoundException If the home/project directory does not exist.
     * @throws NoDirectoryException If the home/project directory is not a directory.
     */
    public function __construct($homeDir, $rootDir, ConfigFileStorage $configFileStorage, PackageFileStorage $packageFileStorage, EventDispatcherInterface $dispatcher)
    {
        if (!file_exists($rootDir)) {
            throw new FileNotFoundException(sprintf(
                'Could not load Puli environment: The root %s does not exist.',
                $rootDir
            ));
        }

        if (!is_dir($rootDir)) {
            throw new NoDirectoryException(sprintf(
                'Could not load Puli environment: The root %s is a file. '.
                'Expected a directory.',
                $rootDir
            ));
        }

        parent::__construct($homeDir, $configFileStorage, $dispatcher);

        // Get the global config.json, if any
        $configFile = $this->getConfigFile();

        $this->rootDir = $rootDir;
        $this->rootPackageFile = $packageFileStorage->loadRootPackageFile(
            $rootDir.'/puli.json',
            $configFile ? $configFile->getConfig() : new DefaultConfig()
        );

        // Override the configuration with the one from the root package
        $this->setConfig(new EnvConfig($this->rootPackageFile->getConfig()));

        foreach ($this->rootPackageFile->getPluginClasses() as $pluginClass) {
            /** @var ManagerPlugin $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this, $this->getEventDispatcher());
        }
    }

    /**
     * Returns the path to the project's root directory.
     *
     * @return string The root directory path.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Returns the root package file of the project.
     *
     * @return RootPackageFile The root package file.
     */
    public function getRootPackageFile()
    {
        return $this->rootPackageFile;
    }

    /**
     * Returns the resource repository of the project.
     *
     * @return ResourceRepository The resource repository.
     */
    public function getRepository()
    {
        if (!$this->repository) {
            $registryClass = $this->getConfig()->get(Config::REGISTRY_CLASS);

            if (!class_exists($registryClass)) {
                $this->loadRegistry();
            }

            $this->repository = $registryClass::getRepository();
        }

        return $this->repository;
    }

    /**
     * Returns the resource discovery of the project.
     *
     * @return ResourceDiscovery The resource discovery.
     */
    public function getDiscovery()
    {
        if (!$this->discovery) {
            $registryClass = $this->getConfig()->get(Config::REGISTRY_CLASS);

            if (!class_exists($registryClass)) {
                $this->loadRegistry();
            }

            $this->discovery = $registryClass::getDiscovery();
        }

        return $this->discovery;
    }

    private function loadRegistry()
    {
        $registryFile = Path::makeAbsolute(
            $this->getConfig()->get(Config::REGISTRY_FILE),
            $this->rootDir
        );

        $configFile = $this->getConfigFile() ? $this->getConfigFile()->getPath() : null;
        $rootPackageFile = $this->rootPackageFile->getPath();

        // Refresh the file if the configuration has been updated
        if (!file_exists($registryFile)
            || ($configFile && filemtime($registryFile) < filemtime($configFile))
            || (filemtime($registryFile) < filemtime($rootPackageFile))) {
            $generator = new RegistryGenerator();
            $generator->generateRegistry($this->rootDir, $this->getConfig());
        }

        require_once $registryFile;
    }
}
