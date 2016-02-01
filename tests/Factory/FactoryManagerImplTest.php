<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_MockObject_MockObject;
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Event\GenerateFactoryEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Php\Clazz;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Api\Server\Server;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Manager\Factory\FactoryManagerImpl;
use Puli\Manager\Factory\Generator\DefaultGeneratorRegistry;
use Puli\Manager\Php\ClassWriter;
use Puli\Manager\Tests\ManagerTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\Glob\Test\TestUtil;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FactoryManagerImplTest extends ManagerTestCase
{
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var DefaultGeneratorRegistry
     */
    private $registry;

    /**
     * @var ClassWriter
     */
    private $realWriter;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ClassWriter
     */
    private $fakeWriter;

    /**
     * @var ModuleList
     */
    private $modules;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var FactoryManagerImpl
     */
    private $manager;

    protected function setUp()
    {
        $this->tempDir = TestUtil::makeTempDir('puli-manager', __CLASS__);

        @mkdir($this->tempDir.'/home');
        @mkdir($this->tempDir.'/root');

        $this->initContext($this->tempDir.'/home', $this->tempDir.'/root');

        $this->context->getConfig()->set(Config::FACTORY_OUT_FILE, 'MyFactory.php');
        $this->context->getConfig()->set(Config::FACTORY_OUT_CLASS, 'Puli\MyFactory');
        $this->context->getConfig()->set(Config::FACTORY_IN_FILE, 'MyFactory.php');
        $this->context->getConfig()->set(Config::FACTORY_IN_CLASS, 'Puli\MyFactory');

        $this->registry = new DefaultGeneratorRegistry();
        $this->realWriter = new ClassWriter();
        $this->fakeWriter = $this->getMockBuilder('Puli\Manager\Php\ClassWriter')
            ->disableOriginalConstructor()
            ->getMock();
        $this->modules = new ModuleList();
        $this->modules->add(new Module(new ModuleFile('vendor/module1'), __DIR__));
        $this->modules->add(new Module(new ModuleFile('vendor/module2'), __DIR__));
        $this->modules->add(new Module(new ModuleFile('vendor/module3'), __DIR__));
        $this->modules->add(new Module(new ModuleFile('vendor/module4'), __DIR__));
        $this->modules->get('vendor/module1')->getModuleFile()->setOverriddenModules(array('vendor/module2', 'vendor/module4'));
        $this->modules->get('vendor/module3')->getModuleFile()->setOverriddenModules(array('vendor/module1'));
        $this->servers = new ServerCollection(array(
            new Server('localhost', 'symlink', 'public_html', '/%s'),
            new Server('example.com', 'rsync', 'ssh://example.com', 'http://example.com/%s'),
        ));
        $this->manager = new FactoryManagerImpl($this->context, $this->registry, $this->realWriter, $this->modules, $this->servers);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testIsFactoryClassAutoGenerated()
    {
        $this->assertTrue($this->manager->isFactoryClassAutoGenerated());

        $this->context->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $this->assertFalse($this->manager->isFactoryClassAutoGenerated());

        $this->context->getConfig()->set(Config::FACTORY_AUTO_GENERATE, true);

        $this->assertTrue($this->manager->isFactoryClassAutoGenerated());
    }

    public function testGenerateFactoryClass()
    {
        $this->manager->generateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');

        $expected = <<<EOF
<?php

namespace Puli;

use Puli\Discovery\Api\Discovery;
use Puli\Discovery\Binding\Initializer\ResourceBindingInitializer;
use Puli\Discovery\JsonDiscovery;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\JsonRepository;
use Puli\UrlGenerator\Api\UrlGenerator;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use RuntimeException;

/**
 * Creates Puli's core services.
 *
 * This class was auto-generated by Puli.
 *
 * IMPORTANT: Before modifying the code below, set the "factory.auto-generate"
 * configuration key to false:
 *
 *     $ puli config factory.auto-generate false
 *
 * Otherwise any modifications will be overwritten!
 */
class MyFactory
{
    /**
     * Creates the resource repository.
     *
     * @return ResourceRepository The created resource repository.
     */
    public function createRepository()
    {
        if (!interface_exists('Puli\Repository\Api\ResourceRepository')) {
            throw new RuntimeException('Please install puli/repository to create ResourceRepository instances.');
        }

        \$repo = new JsonRepository(__DIR__.'/.puli/path-mappings.json', __DIR__, true);

        return \$repo;
    }

    /**
     * Creates the resource discovery.
     *
     * @param ResourceRepository \$repo The resource repository to read from.
     *
     * @return Discovery The created discovery.
     */
    public function createDiscovery(ResourceRepository \$repo)
    {
        if (!interface_exists('Puli\Discovery\Api\Discovery')) {
            throw new RuntimeException('Please install puli/discovery to create Discovery instances.');
        }

        \$discovery = new JsonDiscovery(__DIR__.'/.puli/bindings.json', array(
            new ResourceBindingInitializer(\$repo),
        ));

        return \$discovery;
    }

    /**
     * Creates the URL generator.
     *
     * @param Discovery \$discovery The discovery to read from.
     *
     * @return UrlGenerator The created URL generator.
     */
    public function createUrlGenerator(Discovery \$discovery)
    {
        if (!interface_exists('Puli\UrlGenerator\Api\UrlGenerator')) {
            throw new RuntimeException('Please install puli/url-generator to create UrlGenerator instances.');
        }

        \$generator = new DiscoveryUrlGenerator(\$discovery, array(
            'localhost' => '/%s',
            'example.com' => 'http://example.com/%s',
        ));

        return \$generator;
    }

    /**
     * Returns the order in which the installed modules should be loaded
     * according to the override statements.
     *
     * @return string[] The sorted module names.
     */
    public function getModuleOrder()
    {
        \$order = array(
            'vendor/module2',
            'vendor/module4',
            'vendor/module1',
            'vendor/module3',
        );

        return \$order;
    }
}

EOF;

        $this->assertSame($expected, $contents);
    }

    public function testGenerateFactoryClassAtCustomRelativePath()
    {
        $this->manager->generateFactoryClass('MyCustomFile.php');

        $this->assertFileExists($this->rootDir.'/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testGenerateFactoryClassAtCustomAbsolutePath()
    {
        $this->manager->generateFactoryClass($this->rootDir.'/path/MyCustomFile.php');

        $this->assertFileExists($this->rootDir.'/path/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/path/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testGenerateFactoryClassWithCustomClassName()
    {
        $this->manager->generateFactoryClass(null, 'MyCustomClass');

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyCustomClass', $contents);
    }

    public function testGenerateFactoryClassWithChangeStream()
    {
        $this->context->getConfig()->set(Config::REPOSITORY_OPTIMIZE, true);
        $this->context->getConfig()->set(Config::CHANGE_STREAM_TYPE, 'key-value-store');
        $this->context->getConfig()->set(Config::CHANGE_STREAM_STORE_TYPE, 'json');
        $this->context->getConfig()->set(Config::CHANGE_STREAM_STORE_PATH, '{$puli-dir}/changelog.json');

        $this->manager->generateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');

        $expected = <<<EOF
    /**
     * Creates the resource repository.
     *
     * @return ResourceRepository The created resource repository.
     */
    public function createRepository()
    {
        if (!interface_exists('Puli\Repository\Api\ResourceRepository')) {
            throw new RuntimeException('Please install puli/repository to create ResourceRepository instances.');
        }

        \$store = new JsonFileStore(__DIR__.'/.puli/changelog.json');
        \$stream = new KeyValueStoreChangeStream(\$store);
        \$repo = new OptimizedJsonRepository(__DIR__.'/.puli/path-mappings.json', __DIR__, false, \$stream);

        return \$repo;
    }
EOF;

        $this->assertContains($expected, $contents);
    }

    public function testGenerateFactoryClassDispatchesEvent()
    {
        $this->dispatcher->expects($this->once())
            ->method('hasListeners')
            ->with(PuliEvents::GENERATE_FACTORY)
            ->willReturn(true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(PuliEvents::GENERATE_FACTORY)
            ->willReturnCallback(function ($eventName, GenerateFactoryEvent $event) {
                $class = $event->getFactoryClass();

                PHPUnit_Framework_Assert::assertTrue($class->hasMethod('createRepository'));
                PHPUnit_Framework_Assert::assertTrue($class->hasMethod('createDiscovery'));

                $class->addMethod(new Method('createCustom'));
            });

        $this->manager->generateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertContains('public function createCustom()', $contents);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfPathEmpty()
    {
        $this->manager->generateFactoryClass('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfPathNoString()
    {
        $this->manager->generateFactoryClass(1234);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfClassNameEmpty()
    {
        $this->manager->generateFactoryClass(null, '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateFactoryClassFailsIfClassNameNoString()
    {
        $this->manager->generateFactoryClass(null, 1234);
    }

    public function testAutoGenerateFactoryClass()
    {
        $this->manager->autoGenerateFactoryClass();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $contents = file_get_contents($this->rootDir.'/MyFactory.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyFactory', $contents);
    }

    public function testAutoGenerateFactoryClassDoesNothingIfAutoGenerateDisabled()
    {
        $this->context->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $this->manager->autoGenerateFactoryClass();

        $this->assertFileNotExists($this->rootDir.'/MyFactory.php');
    }

    public function testAutoGenerateFactoryClassGeneratesWithCustomParameters()
    {
        $this->manager->autoGenerateFactoryClass('MyCustomFile.php', 'MyCustomClass');

        $this->assertFileExists($this->rootDir.'/MyCustomFile.php');
        $contents = file_get_contents($this->rootDir.'/MyCustomFile.php');
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertContains('class MyCustomClass', $contents);
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFound()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundAtCustomRelativePath()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass('MyCustomFile.php');
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundAtCustomAbsolutePath()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('Puli\MyFactory', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir.'/path', $class->getDirectory());
            });

        $manager->refreshFactoryClass($this->rootDir.'/path/MyCustomFile.php');
    }

    public function testRefreshFactoryClassGeneratesClassIfFileNotFoundWithCustomClass()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('MyCustomClass', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyFactory.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass(null, 'MyCustomClass');
    }

    public function testRefreshFactoryClassGeneratesIfOlderThanRootModuleFile()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->rootModuleFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassDoesNotRegenerateIfNoRootModuleFile()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        // The class has been generated. No need to refresh it as no puli.json
        // exists.
        touch($this->rootDir.'/MyFactory.php');

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesWithCustomParameters()
    {
        $rootDir = $this->rootDir;
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        touch($this->rootDir.'/MyCustomFile.php');
        sleep(1);
        touch($this->rootModuleFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass')
            ->willReturnCallback(function (Clazz $class) use ($rootDir) {
                PHPUnit_Framework_Assert::assertSame('MyCustomClass', $class->getClassName());
                PHPUnit_Framework_Assert::assertSame('MyCustomFile.php', $class->getFileName());
                PHPUnit_Framework_Assert::assertSame($rootDir, $class->getDirectory());
            });

        $manager->refreshFactoryClass('MyCustomFile.php', 'MyCustomClass');
    }

    public function testRefreshFactoryClassDoesNotGenerateIfNewerThanRootModuleFile()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        touch($this->rootModuleFile->getPath());
        sleep(1);
        touch($this->rootDir.'/MyFactory.php');

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassGeneratesIfOlderThanConfigFile()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        touch($this->rootModuleFile->getPath());
        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->configFile->getPath());

        $this->fakeWriter->expects($this->once())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassDoesNotGenerateIfNewerThanConfigFile()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        touch($this->rootModuleFile->getPath());
        touch($this->configFile->getPath());
        sleep(1);
        touch($this->rootDir.'/MyFactory.php');

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $manager->refreshFactoryClass();
    }

    public function testRefreshFactoryClassDoesNotGenerateIfAutoGenerateDisabled()
    {
        $manager = new FactoryManagerImpl($this->context, $this->registry, $this->fakeWriter, $this->modules, $this->servers);

        // Older than config file -> would normally be generated
        touch($this->rootDir.'/MyFactory.php');
        sleep(1);
        touch($this->rootModuleFile->getPath());

        $this->fakeWriter->expects($this->never())
            ->method('writeClass');

        $this->context->getConfig()->set(Config::FACTORY_AUTO_GENERATE, false);

        $manager->refreshFactoryClass();
    }

    public function testCreateFactoryGeneratesFactoryClass()
    {
        $this->assertFalse(class_exists('Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory1', false));

        $this->context->getConfig()->set(Config::FACTORY_OUT_CLASS, 'Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory1');
        $this->context->getConfig()->set(Config::FACTORY_IN_CLASS, 'Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory1');

        $factory = $this->manager->createFactory();

        $this->assertInstanceOf('Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory1', $factory);
    }

    public function testCreateFactoryGeneratesFactoryClassAtCustomLocation()
    {
        $className = $this->context->getConfig()->get(Config::FACTORY_IN_CLASS);

        $this->assertFileNotExists($this->rootDir.'/MyFactory.php');
        $this->assertFalse(class_exists($className, false));

        $this->context->getConfig()->set(Config::FACTORY_OUT_FILE, 'MyFactory.php');
        $this->context->getConfig()->set(Config::FACTORY_IN_FILE, 'MyFactory.php');

        $factory = $this->manager->createFactory();

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $this->assertInstanceOf($className, $factory);
    }

    public function testCreateFactoryWithParameters()
    {
        $this->assertFileNotExists($this->rootDir.'/MyFactory.php');
        $this->assertFalse(class_exists('Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory2', false));

        $factory = $this->manager->createFactory('MyFactory.php', 'Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory2');

        $this->assertFileExists($this->rootDir.'/MyFactory.php');
        $this->assertInstanceOf('Puli\Manager\Tests\Factory\Fixtures\TestGeneratedFactory2', $factory);
    }

    public function testCreateFactoryWithExistingClass()
    {
        $this->assertFalse(class_exists('Puli\Manager\Tests\Factory\Fixtures\TestFactoryNAL', false));

        $this->context->getConfig()->set(Config::FACTORY_IN_FILE, __DIR__.'/Fixtures/TestFactoryNotAutoLoadable.php');
        $this->context->getConfig()->set(Config::FACTORY_IN_CLASS, 'Puli\Manager\Tests\Factory\Fixtures\TestFactoryNAL');

        $factory = $this->manager->createFactory();

        $this->assertInstanceOf('Puli\Manager\Tests\Factory\Fixtures\TestFactoryNAL', $factory);
    }

    public function testCreateFactoryWithExistingAutoLoadableClass()
    {
        $this->assertFalse(class_exists('Puli\Manager\Tests\Factory\Fixtures\TestFactory', false));

        $this->context->getConfig()->set(Config::FACTORY_IN_FILE, null);
        $this->context->getConfig()->set(Config::FACTORY_IN_CLASS, 'Puli\Manager\Tests\Factory\Fixtures\TestFactory');

        $factory = $this->manager->createFactory();

        $this->assertInstanceOf('Puli\Manager\Tests\Factory\Fixtures\TestFactory', $factory);
    }
}
