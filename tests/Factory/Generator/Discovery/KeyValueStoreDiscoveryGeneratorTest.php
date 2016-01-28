<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\Discovery;

use Puli\Manager\Api\Php\Import;
use Puli\Manager\Api\Php\Method;
use Puli\Manager\Factory\Generator\Discovery\KeyValueStoreDiscoveryGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KeyValueStoreDiscoveryGeneratorTest extends AbstractGeneratorTest
{
    /**
     * @var KeyValueStoreDiscoveryGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->generator = new KeyValueStoreDiscoveryGenerator();
    }

    protected function putCode($path, Method $method)
    {
        // In the generated class, the repository is passed as argument.
        // Create a repository here so that we can run the code successfully.
        $method->getClass()->addImport(new Import('Puli\Manager\Tests\Factory\Generator\Fixtures\TestRepository'));

        $method->setBody(
<<<EOF
\$repo = new TestRepository();
{$method->getBody()}
EOF
        );

        parent::putCode($path, $method);
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $expected = <<<'EOF'
$store = new JsonFileStore(__DIR__.'/bindings.json');
$discovery = new KeyValueStoreDiscovery($store, array(
    new ResourceBindingInitializer($repo),
));
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceForTypeNull()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'store' => array('type' => null),
        ));

        $expected = <<<'EOF'
$store = new NullStore();
$discovery = new KeyValueStoreDiscovery($store, array(
    new ResourceBindingInitializer($repo),
));
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfNoRootDir()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfRootDirNoString()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry, array(
            'root-dir' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfStoreNotArray()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
            'store' => 1234,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('discovery', $this->method, $this->registry, array(
            'root-dir' => $this->rootDir,
        ));

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($discovery));
        $this->assertInstanceOf('Puli\Discovery\KeyValueStoreDiscovery', $discovery);
    }
}
