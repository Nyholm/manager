<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Factory\Generator\KeyValueStore;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Puli\Manager\Factory\Generator\KeyValueStore\PredisStoreGenerator;
use Puli\Manager\Tests\Factory\Generator\AbstractGeneratorTest;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PredisStoreGeneratorTest extends AbstractGeneratorTest
{
    private static $supported;

    /**
     * @var PredisStoreGenerator
     */
    private $generator;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $client = new Client();

        try {
            $client->connect();
            $client->disconnect();
            self::$supported = true;
        } catch (ConnectionException $e) {
            self::$supported = false;
        }
    }

    protected function setUp()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Predis is not available or Redis is not running.');
        }

        parent::setUp();

        $this->generator = new PredisStoreGenerator();
    }

    public function testGenerateService()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);

        $expected = <<<'EOF'
$client = new Client(array(
    'host' => '127.0.0.1',
    'port' => 6379,
));
$store = new PredisStore($client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomHost()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'host' => 'localhost',
        ));

        $expected = <<<'EOF'
$client = new Client(array(
    'host' => 'localhost',
    'port' => 6379,
));
$store = new PredisStore($client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    public function testGenerateServiceWithCustomPort()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'port' => 1234,
        ));

        $expected = <<<'EOF'
$client = new Client(array(
    'host' => '127.0.0.1',
    'port' => 1234,
));
$store = new PredisStore($client);
EOF;

        $this->assertSame($expected, $this->method->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfHostNoString()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'host' => 1234,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateServiceFailsIfPortNoInteger()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry, array(
            'port' => false,
        ));
    }

    public function testRunGeneratedCode()
    {
        $this->generator->generateNewInstance('store', $this->method, $this->registry);

        $this->putCode($this->outputPath, $this->method);

        require $this->outputPath;

        $this->assertTrue(isset($store));
        $this->assertInstanceOf('Webmozart\KeyValueStore\PredisStore', $store);
    }
}
