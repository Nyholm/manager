<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Repository;

use Exception;
use RuntimeException;

/**
 * Thrown when a duplicate path mapping is detected.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DuplicatePathMappingException extends RuntimeException
{
    /**
     * Creates an exception for a duplicate repository path.
     *
     * @param string         $repositoryPath The mapped repository path.
     * @param string         $moduleName     The name of the module containing
     *                                       the mapping.
     * @param Exception|null $cause          The exception that caused this
     *                                       exception.
     *
     * @return static The created exception.
     */
    public static function forRepositoryPath($repositoryPath, $moduleName, Exception $cause = null)
    {
        return new static(sprintf(
            'The path "%s" is already mapped in module "%s".',
            $repositoryPath,
            $moduleName
        ), 0, $cause);
    }
}
