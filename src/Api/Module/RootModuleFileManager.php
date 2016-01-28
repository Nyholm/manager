<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Module;

use Puli\Manager\Api\Config\ConfigManager;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Storage\WriteException;
use Webmozart\Expression\Expression;
use Webmozart\Json\Migration\MigrationFailedException;

/**
 * Manages changes to the root module file.
 *
 * Use this class to make persistent changes to the puli.json of a project.
 * Whenever you call methods in this class, the changes will be written to disk.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RootModuleFileManager extends ConfigManager
{
    /**
     * Returns the project context.
     *
     * @return ProjectContext The project context.
     */
    public function getContext();

    /**
     * Returns the managed module file.
     *
     * @return RootModuleFile The managed module file.
     */
    public function getModuleFile();

    /**
     * Returns the module name configured in the module file.
     *
     * @return null|string The configured module name.
     */
    public function getModuleName();

    /**
     * Sets the module name configured in the module file.
     *
     * @param string $moduleName The module name.
     */
    public function setModuleName($moduleName);

    /**
     * Adds a plugin class to the module file.
     *
     * The plugin class must be passed as fully-qualified name of a class that
     * implements {@link PuliPlugin}. Plugin constructors must not have
     * mandatory parameters.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function addPluginClass($pluginClass);

    /**
     * Removes a plugin class from the module file.
     *
     * If the module file does not contain the class, this method does nothing.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     */
    public function removePluginClass($pluginClass);

    /**
     * Removes the plugin classes from the module file that match the given
     * expression.
     *
     * @param Expression $expr The search criteria.
     */
    public function removePluginClasses(Expression $expr);

    /**
     * Removes all plugin classes from the module file.
     *
     * If the module file does not contain any classes, this method does
     * nothing.
     */
    public function clearPluginClasses();

    /**
     * Returns whether the module file contains a plugin class.
     *
     * @param string $pluginClass The fully qualified plugin class name.
     *
     * @return bool Returns `true` if the module file contains the given
     *              plugin class and `false` otherwise.
     */
    public function hasPluginClass($pluginClass);

    /**
     * Returns whether the module file contains any plugin classes.
     *
     * You can optionally pass an expression to check whether the manager has
     * plugin classes matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the manager has plugin classes in the root
     *              module and `false` otherwise. If an expression is passed,
     *              this method only returns `true` if the manager has plugin
     *              classes matching the expression.
     */
    public function hasPluginClasses(Expression $expr = null);

    /**
     * Returns all installed plugin classes.
     *
     * @return string[] The fully qualified plugin class names.
     */
    public function getPluginClasses();

    /**
     * Returns all installed plugin classes matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return string[] The fully qualified plugin class names matching the
     *                  expression.
     */
    public function findPluginClasses(Expression $expr);

    /**
     * Sets an extra key in the file.
     *
     * The file is saved directly after setting the key.
     *
     * @param string $key   The key name.
     * @param mixed  $value The stored value.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function setExtraKey($key, $value);

    /**
     * Sets the extra keys in the file.
     *
     * The file is saved directly after setting the keys.
     *
     * @param string[] $values A list of values indexed by their key names.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function setExtraKeys(array $values);

    /**
     * Removes an extra key from the file.
     *
     * The file is saved directly after removing the key.
     *
     * @param string $key The name of the removed extra key.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function removeExtraKey($key);

    /**
     * Removes the extra keys from the module file that match the given
     * expression.
     *
     * The file is saved directly after removing the keys.
     *
     * @param Expression $expr The search criteria.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function removeExtraKeys(Expression $expr);

    /**
     * Removes all extra keys from the file.
     *
     * The file is saved directly after removing the keys.
     *
     * @throws WriteException If the file cannot be written.
     */
    public function clearExtraKeys();

    /**
     * Returns whether an extra key exists.
     *
     * @param string $key The extra key to search.
     *
     * @return bool Returns `true` if the file contains the key and `false`
     *              otherwise.
     */
    public function hasExtraKey($key);

    /**
     * Returns whether the file contains any extra keys.
     *
     * You can optionally pass an expression to check whether the file contains
     * extra keys matching the expression.
     *
     * @param Expression|null $expr The search criteria.
     *
     * @return bool Returns `true` if the file contains extra keys and `false`
     *              otherwise. If an expression is passed, this method only
     *              returns `true` if the file contains extra keys matching the
     *              expression.
     */
    public function hasExtraKeys(Expression $expr = null);

    /**
     * Returns the value of a configuration key.
     *
     * @param string $key     The name of the extra key.
     * @param mixed  $default The value to return if the key was not set.
     *
     * @return mixed The value of the key or the default value, if none is set.
     */
    public function getExtraKey($key, $default = null);

    /**
     * Returns the values of all extra keys set in the file.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function getExtraKeys();

    /**
     * Returns the values of all extra keys matching the given expression.
     *
     * @param Expression $expr The search criteria.
     *
     * @return array A mapping of configuration keys to values.
     */
    public function findExtraKeys(Expression $expr);

    /**
     * Migrates the root module file to the given version.
     *
     * @param string $targetVersion The target version string.
     *
     * @throws MigrationFailedException If the migration fails.
     * @throws WriteException           If the file cannot be written.
     */
    public function migrate($targetVersion);
}
