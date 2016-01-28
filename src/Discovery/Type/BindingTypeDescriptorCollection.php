<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Discovery\Type;

use OutOfBoundsException;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Util\TwoDimensionalHashMap;

/**
 * A store for binding type descriptors.
 *
 * Each descriptor has a composite key:
 *
 *  * The name of the type.
 *  * The module that defines the type.
 *
 * The store implements transparent merging of types defined within different
 * modules, but with the same type name.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingTypeDescriptorCollection
{
    /**
     * @var TwoDimensionalHashMap
     */
    private $map;

    /**
     * Creates the store.
     */
    public function __construct()
    {
        $this->map = new TwoDimensionalHashMap();
    }

    /**
     * Adds a type descriptor.
     *
     * @param BindingTypeDescriptor $typeDescriptor The type descriptor.
     */
    public function add(BindingTypeDescriptor $typeDescriptor)
    {
        $this->map->set($typeDescriptor->getTypeName(), $typeDescriptor->getContainingModule()->getName(), $typeDescriptor);
    }

    /**
     * Removes a type descriptor.
     *
     * This method ignores non-existing type descriptors.
     *
     * @param string $typeName   The name of the type.
     * @param string $moduleName The name of the module containing the type.
     */
    public function remove($typeName, $moduleName)
    {
        $this->map->remove($typeName, $moduleName);
    }

    /**
     * Returns a type descriptor.
     *
     * @param string $typeName   The name of the type.
     * @param string $moduleName The name of the module containing the type.
     *
     * @return BindingTypeDescriptor The type descriptor.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name/module.
     */
    public function get($typeName, $moduleName)
    {
        return $this->map->get($typeName, $moduleName);
    }

    /**
     * Returns the first type descriptor with the given name.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor The type descriptor.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name.
     */
    public function getFirst($typeName)
    {
        return $this->map->getFirst($typeName);
    }

    /**
     * Returns the enabled type descriptor for a given type name.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor|null The enabled type descriptor or `null`
     *                                    if no enabled descriptor was found.
     */
    public function getEnabled($typeName)
    {
        if (!$this->contains($typeName)) {
            return null;
        }

        foreach ($this->listByTypeName($typeName) as $typeDescriptor) {
            if ($typeDescriptor->isEnabled()) {
                return $typeDescriptor;
            }
        }

        return null;
    }

    /**
     * Returns all type descriptors set for the given name.
     *
     * @param string $typeName The name of the type.
     *
     * @return BindingTypeDescriptor[] The type descriptors.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name.
     */
    public function listByTypeName($typeName)
    {
        return $this->map->listByPrimaryKey($typeName);
    }

    /**
     * Returns whether a type descriptor was set for the given name/module.
     *
     * @param string      $typeName   The name of the type.
     * @param string|null $moduleName The name of the module containing the
     *                                type.
     *
     * @return bool Returns `true` if a type descriptor was set for the given
     *              name/module.
     */
    public function contains($typeName, $moduleName = null)
    {
        return $this->map->contains($typeName, $moduleName);
    }

    /**
     * Returns the names of the modules defining types with the given name.
     *
     * @param string|null $typeName The name of the type.
     *
     * @return string[] The module names.
     *
     * @throws OutOfBoundsException If no type descriptor was set for the
     *                              given name.
     */
    public function getModuleNames($typeName = null)
    {
        return $this->map->getSecondaryKeys($typeName);
    }

    /**
     * Returns the names of all type descriptors.
     *
     * @return string[] The names of the stored types.
     */
    public function getTypeNames()
    {
        return $this->map->getPrimaryKeys();
    }

    /**
     * Returns the contents of the collection as array.
     *
     * @return BindingTypeDescriptor[][] A multi-dimensional array containing
     *                                   all types indexed first by name, then
     *                                   by module name.
     */
    public function toArray()
    {
        return $this->map->toArray();
    }

    /**
     * Returns whether the collection is empty.
     *
     * @return bool Returns `true` if the collection is empty and `false`
     *              otherwise.
     */
    public function isEmpty()
    {
        return $this->map->isEmpty();
    }
}
