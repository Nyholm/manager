<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Server;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ModuleFileServerManagerLoadedTest extends ModuleFileServerManagerUnloadedTest
{
    protected function populateDefaultManager()
    {
        parent::populateDefaultManager();

        // Load the servers
        $this->serverManager->getServers();
    }
}
