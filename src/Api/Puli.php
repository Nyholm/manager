<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api;

use LogicException;
use Psr\Log\LoggerInterface;
use Puli\Discovery\Api\Discovery;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Config\ConfigFileManager;
use Puli\Manager\Api\Context\Context;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Factory\FactoryManager;
use Puli\Manager\Api\Installation\InstallationManager;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Package\PackageManager;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Puli\Manager\Api\Repository\RepositoryManager;
use Puli\Manager\Api\Server\ServerManager;
use Puli\Manager\Api\Storage\Storage;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Asset\DiscoveryAssetManager;
use Puli\Manager\Config\ConfigFileConverter;
use Puli\Manager\Config\ConfigFileManagerImpl;
use Puli\Manager\Config\ConfigFileStorage;
use Puli\Manager\Config\DefaultConfig;
use Puli\Manager\Config\EnvConfig;
use Puli\Manager\Discovery\DiscoveryManagerImpl;
use Puli\Manager\Factory\FactoryManagerImpl;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;
use Puli\Manager\Filesystem\FilesystemStorage;
use Puli\Manager\Installation\InstallationManagerImpl;
use Puli\Manager\Installer\PackageFileInstallerManager;
use Puli\Manager\Package\PackageFileConverter;
use Puli\Manager\Package\PackageFileStorage;
use Puli\Manager\Package\PackageManagerImpl;
use Puli\Manager\Package\RootPackageFileConverter;
use Puli\Manager\Package\RootPackageFileManagerImpl;
use Puli\Manager\Php\ClassWriter;
use Puli\Manager\Repository\RepositoryManagerImpl;
use Puli\Manager\Server\PackageFileServerManager;
use Puli\Manager\Util\System;
use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Api\ResourceRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;
use Webmozart\Json\Conversion\JsonConverter;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\Json\Migration\MigratingConverter;
use Webmozart\Json\Migration\MigrationManager;
use Webmozart\Json\Validation\ValidatingConverter;
use Webmozart\PathUtil\Path;

/**
 * The Puli service locator.
 *
 * Use this class to access the managers provided by this package:
 *
 * ```php
 * $puli = new Puli(getcwd());
 * $puli->start();
 *
 * $packageManager = $puli->getPackageManager();
 * ```
 *
 * The `Puli` class either operates in the global or a project context:
 *
 *  * The "global context" is not tied to a specific root package. A global
 *    context only loads the settings of the "config.json" file in the home
 *    directory. The `Puli` class operates in the global context if no
 *    project root directory is passed to the constructor. In the global
 *    context, only the global config file manager is available.
 *  * The "project context" is tied to a specific Puli project. You need to
 *    pass the path to the project's root directory to the constructor or to
 *    {@link setRootDirectory()}. The configuration of the "puli.json" file in
 *    the root directory is used to configure the managers.
 *
 * The `Puli` class creates four kinds of managers:
 *
 *  * The "config file manager" allows you to modify entries of the
 *    "config.json" file in the home directory.
 *  * The "package file manager" manages modifications to the "puli.json" file
 *    of a Puli project.
 *  * The "package manager" manages the package repository of a Puli project.
 *  * The "repository manager" manages the resource repository of a Puli
 *    project.
 *  * The "discovery manager" manages the resource discovery of a Puli project.
 *
 * The home directory is read from the context variable "PULI_HOME".
 * If this variable is not set, the home directory defaults to:
 *
 *  * `$HOME/.puli` on Linux, where `$HOME` is the context variable
 *    "HOME".
 *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the context
 *    variable "APPDATA".
 *
 * If none of these variables can be found, an exception is thrown.
 *
 * A .htaccess file is put into the home directory to protect it from web
 * access.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Puli
{
    /**
     * @var string|null
     */
    private $rootDir;

    /**
     * @var string
     */
    private $env;

    /**
     * @var EventDispatcherInterface|null
     */
    private $dispatcher;

    /**
     * @var Context|ProjectContext
     */
    private $context;

    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var Discovery
     */
    private $discovery;

    /**
     * @var object
     */
    private $factory;

    /**
     * @var FactoryManager
     */
    private $factoryManager;

    /**
     * @var ConfigFileManager
     */
    private $configFileManager;

    /**
     * @var RootPackageFileManager
     */
    private $rootPackageFileManager;

    /**
     * @var PackageManager
     */
    private $packageManager;

    /**
     * @var RepositoryManager
     */
    private $repositoryManager;

    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var InstallerManager
     */
    private $installerManager;

    /**
     * @var ServerManager
     */
    private $serverManager;

    /**
     * @var UrlGenerator
     */
    private $urlGenerator;

    /**
     * @var Storage|null
     */
    private $storage;

    /**
     * @var ConfigFileStorage|null
     */
    private $configFileStorage;

    /**
     * @var ConfigFileConverter|null
     */
    private $configFileConverter;

    /**
     * @var PackageFileStorage|null
     */
    private $packageFileStorage;

    /**
     * @var JsonConverter|null
     */
    private $packageFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $legacyPackageFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $rootPackageFileConverter;

    /**
     * @var JsonConverter|null
     */
    private $legacyRootPackageFileConverter;

    /**
     * @var JsonEncoder
     */
    private $jsonEncoder;

    /**
     * @var JsonDecoder
     */
    private $jsonDecoder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $pluginsEnabled = true;

    /**
     * Parses the system context for a home directory.
     *
     * @return null|string Returns the path to the home directory or `null`
     *                     if none was found.
     */
    private static function parseHomeDirectory()
    {
        try {
            $homeDir = System::parseHomeDirectory();

            System::denyWebAccess($homeDir);

            return $homeDir;
        } catch (InvalidConfigException $e) {
            // Context variable was not found -> no home directory
            // This happens often on web servers where the home directory is
            // not set manually
            return null;
        }
    }

    /**
     * Creates a new instance for the given Puli project.
     *
     * @param string|null $rootDir The root directory of the Puli project.
     *                             If none is passed, the object operates in
     *                             the global context. You can set or switch
     *                             the root directories later on by calling
     *                             {@link setRootDirectory()}.
     * @param string      $env     One of the {@link Environment} constants.
     *
     * @see Puli, start()
     */
    public function __construct($rootDir = null, $env = Environment::DEV)
    {
        $this->setRootDirectory($rootDir);
        $this->setEnvironment($env);
    }

    /**
     * Starts the service container.
     */
    public function start()
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        if (null !== $this->rootDir) {
            $this->context = $this->createProjectContext($this->rootDir, $this->env);
            $bootstrapFile = $this->context->getConfig()->get(Config::BOOTSTRAP_FILE);

            // Run the project's bootstrap file to enable project-specific
            // autoloading
            if (null !== $bootstrapFile) {
                // Backup autoload functions of the PHAR
                $autoloadFunctions = spl_autoload_functions();

                foreach ($autoloadFunctions as $autoloadFunction) {
                    spl_autoload_unregister($autoloadFunction);
                }

                // Add project-specific autoload functions
                require_once Path::makeAbsolute($bootstrapFile, $this->rootDir);

                // Prepend autoload functions of the PHAR again
                // This is needed if the user specific autoload functions were
                // added with $prepend=true (as done by Composer)
                // Classes in the PHAR should always take precedence
                for ($i = count($autoloadFunctions) - 1; $i >= 0; --$i) {
                    spl_autoload_register($autoloadFunctions[$i], true, true);
                }
            }
        } else {
            $this->context = $this->createGlobalContext();
        }

        $this->dispatcher = $this->context->getEventDispatcher();
        $this->started = true;

        // Start plugins once the container is running
        if ($this->rootDir && $this->pluginsEnabled) {
            $this->activatePlugins();
        }
    }

    /**
     * Returns whether the service container is started.
     *
     * @return bool Returns `true` if the container is started and `false`
     *              otherwise.
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets the root directory of the managed Puli project.
     *
     * @param string|null $rootDir The root directory of the managed Puli
     *                             project or `null` to start Puli outside of a
     *                             specific project.
     */
    public function setRootDirectory($rootDir)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        Assert::nullOrDirectory($rootDir);

        $this->rootDir = $rootDir ? Path::canonicalize($rootDir) : null;
    }

    /**
     * Sets the environment of the managed Puli project.
     *
     * @param string $env One of the {@link Environment} constants.
     */
    public function setEnvironment($env)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        Assert::oneOf($env, Environment::all(), 'The environment must be one of: %2$s. Got: %s');

        $this->env = $env;
    }

    /**
     * Retturns the environment of the managed Puli project.
     *
     * @return string One of the {@link Environment} constants.
     */
    public function getEnvironment()
    {
        return $this->env;
    }

    /**
     * Returns the root directory of the managed Puli project.
     *
     * If no Puli project is managed at the moment, `null` is returned.
     *
     * @return string|null The root directory of the managed Puli project or
     *                     `null` if none is set.
     */
    public function getRootDirectory()
    {
        return $this->rootDir;
    }

    /**
     * Sets the logger to use.
     *
     * @param LoggerInterface $logger The logger to use.
     */
    public function setLogger(LoggerInterface $logger)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->logger = $logger;
    }

    /**
     * Returns the logger.
     *
     * @return LoggerInterface The logger.
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Sets the event dispatcher to use.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        if ($this->started) {
            throw new LogicException('Puli is already started');
        }

        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns the used event dispatcher.
     *
     * @return EventDispatcherInterface|null The used logger.
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Enables all Puli plugins.
     */
    public function enablePlugins()
    {
        $this->pluginsEnabled = true;
    }

    /**
     * Disables all Puli plugins.
     */
    public function disablePlugins()
    {
        $this->pluginsEnabled = false;
    }

    /**
     * Returns whether Puli plugins are enabled.
     *
     * @return bool Returns `true` if Puli plugins will be loaded and `false`
     *              otherwise.
     */
    public function arePluginsEnabled()
    {
        return $this->pluginsEnabled;
    }

    /**
     * Returns the context.
     *
     * @return Context|ProjectContext The context.
     */
    public function getContext()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        return $this->context;
    }

    /**
     * Returns the resource repository of the project.
     *
     * @return EditableRepository The resource repository.
     */
    public function getRepository()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->context instanceof ProjectContext) {
            return null;
        }

        if (!$this->repo) {
            $this->repo = $this->getFactory()->createRepository();
        }

        return $this->repo;
    }

    /**
     * Returns the resource discovery of the project.
     *
     * @return EditableDiscovery The resource discovery.
     */
    public function getDiscovery()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->context instanceof ProjectContext) {
            return null;
        }

        if (!$this->discovery) {
            $this->discovery = $this->getFactory()->createDiscovery($this->getRepository());
        }

        return $this->discovery;
    }

    /**
     * @return object
     */
    public function getFactory()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factory && $this->context instanceof ProjectContext) {
            $this->factory = $this->getFactoryManager()->createFactory();
        }

        return $this->factory;
    }

    /**
     * @return FactoryManager
     */
    public function getFactoryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->factoryManager && $this->context instanceof ProjectContext) {
            $this->factoryManager = new FactoryManagerImpl(
                $this->context,
                new DefaultGeneratorRegistry(),
                new ClassWriter()
            );

            // Don't set via the constructor to prevent cyclic dependencies
            $this->factoryManager->setPackages($this->getPackageManager()->getPackages());
            $this->factoryManager->setServers($this->getServerManager()->getServers());
        }

        return $this->factoryManager;
    }

    /**
     * Returns the configuration file manager.
     *
     * @return ConfigFileManager The configuration file manager.
     */
    public function getConfigFileManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->configFileManager && $this->context->getHomeDirectory()) {
            $this->configFileManager = new ConfigFileManagerImpl(
                $this->context,
                $this->getConfigFileStorage()
            );
        }

        return $this->configFileManager;
    }

    /**
     * Returns the root package file manager.
     *
     * @return RootPackageFileManager The package file manager.
     */
    public function getRootPackageFileManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->rootPackageFileManager && $this->context instanceof ProjectContext) {
            $this->rootPackageFileManager = new RootPackageFileManagerImpl(
                $this->context,
                $this->getPackageFileStorage()
            );
        }

        return $this->rootPackageFileManager;
    }

    /**
     * Returns the package manager.
     *
     * @return PackageManager The package manager.
     */
    public function getPackageManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->packageManager && $this->context instanceof ProjectContext) {
            $this->packageManager = new PackageManagerImpl(
                $this->context,
                $this->getPackageFileStorage()
            );
        }

        return $this->packageManager;
    }

    /**
     * Returns the resource repository manager.
     *
     * @return RepositoryManager The repository manager.
     */
    public function getRepositoryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->repositoryManager && $this->context instanceof ProjectContext) {
            $this->repositoryManager = new RepositoryManagerImpl(
                $this->context,
                $this->getRepository(),
                $this->getPackageManager()->findPackages(Expr::method('isEnabled', Expr::same(true))),
                $this->getPackageFileStorage()
            );
        }

        return $this->repositoryManager;
    }

    /**
     * Returns the resource discovery manager.
     *
     * @return DiscoveryManager The discovery manager.
     */
    public function getDiscoveryManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->discoveryManager && $this->context instanceof ProjectContext) {
            $this->discoveryManager = new DiscoveryManagerImpl(
                $this->context,
                $this->getDiscovery(),
                $this->getPackageManager()->findPackages(Expr::method('isEnabled', Expr::same(true))),
                $this->getPackageFileStorage(),
                $this->logger
            );
        }

        return $this->discoveryManager;
    }

    /**
     * Returns the asset manager.
     *
     * @return AssetManager The asset manager.
     */
    public function getAssetManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->assetManager && $this->context instanceof ProjectContext) {
            $this->assetManager = new DiscoveryAssetManager(
                $this->getDiscoveryManager(),
                $this->getServerManager()->getServers()
            );
        }

        return $this->assetManager;
    }

    /**
     * Returns the installation manager.
     *
     * @return InstallationManager The installation manager.
     */
    public function getInstallationManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installationManager && $this->context instanceof ProjectContext) {
            $this->installationManager = new InstallationManagerImpl(
                $this->getContext(),
                $this->getRepository(),
                $this->getServerManager()->getServers(),
                $this->getInstallerManager()
            );
        }

        return $this->installationManager;
    }

    /**
     * Returns the installer manager.
     *
     * @return InstallerManager The installer manager.
     */
    public function getInstallerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->installerManager && $this->context instanceof ProjectContext) {
            $this->installerManager = new PackageFileInstallerManager(
                $this->getRootPackageFileManager(),
                $this->getPackageManager()->getPackages()
            );
        }

        return $this->installerManager;
    }

    /**
     * Returns the server manager.
     *
     * @return ServerManager The server manager.
     */
    public function getServerManager()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->serverManager && $this->context instanceof ProjectContext) {
            $this->serverManager = new PackageFileServerManager(
                $this->getRootPackageFileManager(),
                $this->getInstallerManager()
            );
        }

        return $this->serverManager;
    }

    /**
     * Returns the resource URL generator.
     *
     * @return UrlGenerator The resource URL generator.
     */
    public function getUrlGenerator()
    {
        if (!$this->started) {
            throw new LogicException('Puli was not started');
        }

        if (!$this->urlGenerator && $this->context instanceof ProjectContext) {
            $urlFormats = array();
            foreach ($this->getServerManager()->getServers() as $server) {
                $urlFormats[$server->getName()] = $server->getUrlFormat();
            }

            $this->urlGenerator = new DiscoveryUrlGenerator($this->getDiscovery(), $urlFormats);
        }

        return $this->urlGenerator;
    }

    /**
     * Returns the file storage.
     *
     * @return Storage The storage.
     */
    public function getStorage()
    {
        if (!$this->storage) {
            $this->storage = new FilesystemStorage();
        }

        return $this->storage;
    }

    /**
     * Returns the configuration file serializer.
     *
     * @return ConfigFileConverter The configuration file serializer.
     */
    public function getConfigFileConverter()
    {
        if (!$this->configFileConverter) {
            $this->configFileConverter = new ConfigFileConverter();
        }

        return $this->configFileConverter;
    }

    /**
     * Returns the package file converter.
     *
     * @return JsonConverter The package file converter.
     */
    public function getPackageFileConverter()
    {
        if (!$this->packageFileConverter) {
            $this->packageFileConverter = new ValidatingConverter(
                new PackageFileConverter(),
                __DIR__.'/../../res/schema/package-schema-'.PackageFileConverter::VERSION.'.json'
            );
        }

        return $this->packageFileConverter;
    }

    /**
     * Returns the package file serializer with support for legacy versions.
     *
     * @return JsonConverter The package file converter.
     */
    public function getLegacyPackageFileConverter()
    {
        if (!$this->legacyPackageFileConverter) {
            $this->legacyPackageFileConverter = new ValidatingConverter(
                new MigratingConverter(
                    $this->getPackageFileConverter(),
                    PackageFileConverter::VERSION,
                    new MigrationManager(array(
                        // add future migrations here
                    ))
                ),
                function (stdClass $jsonData) {
                    return __DIR__.'/../../res/schema/package-schema-'.$jsonData->version.'.json';
                }
            );
        }

        return $this->legacyPackageFileConverter;
    }

    /**
     * Returns the package file converter.
     *
     * @return JsonConverter The package file converter.
     */
    public function getRootPackageFileConverter()
    {
        if (!$this->rootPackageFileConverter) {
            $this->rootPackageFileConverter = new ValidatingConverter(
                new RootPackageFileConverter(),
                __DIR__.'/../../res/schema/package-schema-'.RootPackageFileConverter::VERSION.'.json'
            );
        }

        return $this->rootPackageFileConverter;
    }

    /**
     * Returns the package file serializer with support for legacy versions.
     *
     * @return JsonConverter The package file converter.
     */
    public function getLegacyRootPackageFileConverter()
    {
        if (!$this->legacyRootPackageFileConverter) {
            $this->legacyRootPackageFileConverter = new ValidatingConverter(
                new MigratingConverter(
                    $this->getRootPackageFileConverter(),
                    RootPackageFileConverter::VERSION,
                    new MigrationManager(array(
                        // add future migrations here
                    ))
                ),
                function (stdClass $jsonData) {
                    return __DIR__.'/../../res/schema/package-schema-'.$jsonData->version.'.json';
                }
            );
        }

        return $this->legacyRootPackageFileConverter;
    }

    /**
     * Returns the JSON encoder.
     *
     * @return JsonEncoder The JSON encoder.
     */
    public function getJsonEncoder()
    {
        if (!$this->jsonEncoder) {
            $this->jsonEncoder = new JsonEncoder();
            $this->jsonEncoder->setPrettyPrinting(true);
            $this->jsonEncoder->setEscapeSlash(false);
            $this->jsonEncoder->setTerminateWithLineFeed(true);
        }

        return $this->jsonEncoder;
    }

    /**
     * Returns the JSON decoder.
     *
     * @return JsonDecoder The JSON decoder.
     */
    public function getJsonDecoder()
    {
        if (!$this->jsonDecoder) {
            $this->jsonDecoder = new JsonDecoder();
        }

        return $this->jsonDecoder;
    }

    private function activatePlugins()
    {
        foreach ($this->context->getRootPackageFile()->getPluginClasses() as $pluginClass) {
            $this->validatePluginClass($pluginClass);

            /** @var PuliPlugin $plugin */
            $plugin = new $pluginClass();
            $plugin->activate($this);
        }
    }

    private function createGlobalContext()
    {
        $baseConfig = new DefaultConfig();
        $homeDir = self::parseHomeDirectory();

        if (null !== $configFile = $this->loadConfigFile($homeDir, $baseConfig)) {
            $baseConfig = $configFile->getConfig();
        }

        $config = new EnvConfig($baseConfig);

        return new Context($homeDir, $config, $configFile, $this->dispatcher);
    }

    /**
     * Creates the context of a Puli project.
     *
     * The home directory is read from the context variable "PULI_HOME".
     * If this variable is not set, the home directory defaults to:
     *
     *  * `$HOME/.puli` on Linux, where `$HOME` is the context variable
     *    "HOME".
     *  * `$APPDATA/Puli` on Windows, where `$APPDATA` is the context
     *    variable "APPDATA".
     *
     * If none of these variables can be found, an exception is thrown.
     *
     * A .htaccess file is put into the home directory to protect it from web
     * access.
     *
     * @param string $rootDir The path to the project.
     *
     * @return ProjectContext The project context.
     */
    private function createProjectContext($rootDir, $env)
    {
        Assert::fileExists($rootDir, 'Could not load Puli context: The root %s does not exist.');
        Assert::directory($rootDir, 'Could not load Puli context: The root %s is a file. Expected a directory.');

        $baseConfig = new DefaultConfig();
        $homeDir = self::parseHomeDirectory();

        if (null !== $configFile = $this->loadConfigFile($homeDir, $baseConfig)) {
            $baseConfig = $configFile->getConfig();
        }

        // Create a storage without the factory manager
        $packageFileStorage = new PackageFileStorage(
            $this->getStorage(),
            $this->getLegacyPackageFileConverter(),
            $this->getLegacyRootPackageFileConverter(),
            $this->getJsonEncoder(),
            $this->getJsonDecoder()
        );

        $rootDir = Path::canonicalize($rootDir);
        $rootFilePath = $this->rootDir.'/puli.json';

        try {
            $rootPackageFile = $packageFileStorage->loadRootPackageFile($rootFilePath, $baseConfig);
        } catch (FileNotFoundException $e) {
            $rootPackageFile = new RootPackageFile(null, $rootFilePath, $baseConfig);
        }

        $config = new EnvConfig($rootPackageFile->getConfig());

        return new ProjectContext($homeDir, $rootDir, $config, $rootPackageFile, $configFile, $this->dispatcher, $env);
    }

    /**
     * Returns the configuration file storage.
     *
     * @return ConfigFileStorage The configuration file storage.
     */
    private function getConfigFileStorage()
    {
        if (!$this->configFileStorage) {
            $this->configFileStorage = new ConfigFileStorage(
                $this->getStorage(),
                $this->getConfigFileConverter(),
                $this->getJsonEncoder(),
                $this->getJsonDecoder(),
                $this->getFactoryManager()
            );
        }

        return $this->configFileStorage;
    }

    /**
     * Returns the package file storage.
     *
     * @return PackageFileStorage The package file storage.
     */
    private function getPackageFileStorage()
    {
        if (!$this->packageFileStorage) {
            $this->packageFileStorage = new PackageFileStorage(
                $this->getStorage(),
                $this->getLegacyPackageFileConverter(),
                $this->getLegacyRootPackageFileConverter(),
                $this->getJsonEncoder(),
                $this->getJsonDecoder(),
                $this->getFactoryManager()
            );
        }

        return $this->packageFileStorage;
    }

    /**
     * Validates the given plugin class name.
     *
     * @param string $pluginClass The fully qualified name of a plugin class.
     */
    private function validatePluginClass($pluginClass)
    {
        if (!class_exists($pluginClass)) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s does not exist.',
                $pluginClass
            ));
        }

        if (!in_array('Puli\Manager\Api\PuliPlugin', class_implements($pluginClass))) {
            throw new InvalidConfigException(sprintf(
                'The plugin class %s must implement PuliPlugin.',
                $pluginClass
            ));
        }
    }

    private function loadConfigFile($homeDir, Config $baseConfig)
    {
        if (null === $homeDir) {
            return null;
        }

        Assert::fileExists($homeDir, 'Could not load Puli context: The home directory %s does not exist.');
        Assert::directory($homeDir, 'Could not load Puli context: The home directory %s is a file. Expected a directory.');

        // Create a storage without the factory manager
        $configStorage = new ConfigFileStorage(
            $this->getStorage(),
            $this->getConfigFileConverter(),
            $this->getJsonEncoder(),
            $this->getJsonDecoder()
        );

        $configPath = Path::canonicalize($homeDir).'/config.json';

        try {
            return $configStorage->loadConfigFile($configPath, $baseConfig);
        } catch (FileNotFoundException $e) {
            // It's ok if no config.json exists. We'll work with
            // DefaultConfig instead
            return null;
        }
    }
}
