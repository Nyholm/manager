<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Config;

use Puli\Manager\Api\Config\ConfigFile;
use Puli\Manager\Api\Config\ConfigFileManager;
use Puli\Manager\Api\Context\Context;
use Puli\Manager\Json\JsonStorage;

/**
 * Manages changes to the global configuration file.
 *
 * Use this class to make persistent changes to the global config.json.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConfigFileManagerImpl extends AbstractConfigManager implements ConfigFileManager
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var ConfigFile
     */
    private $configFile;

    /**
     * @var JsonStorage
     */
    private $jsonStorage;

    /**
     * Creates the configuration manager.
     *
     * @param Context     $context     The global context.
     * @param JsonStorage $jsonStorage The configuration file storage.
     */
    public function __construct(Context $context, JsonStorage $jsonStorage)
    {
        $this->context = $context;
        $this->jsonStorage = $jsonStorage;
        $this->configFile = $context->getConfigFile();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->configFile->getConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * {@inheritdoc}
     */
    protected function saveConfigFile()
    {
        $this->jsonStorage->saveConfigFile($this->configFile);
    }
}
