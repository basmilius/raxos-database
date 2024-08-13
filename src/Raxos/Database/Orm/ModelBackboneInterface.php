<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Definition\MacroDefinition;

/**
 * Interface ModelBackboneInterface
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
interface ModelBackboneInterface
{

    /**
     * Adds the given instance.
     *
     * @param Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addInstance(Model $instance): void;

    /**
     * Returns a new instance of the model.
     *
     * @return TModel&Model
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createInstance(): Model;

    /**
     * Removes the given instance.
     *
     * @param Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function removeInstance(Model $instance): void;

    /**
     * Computes the value of the given macro.
     *
     * @param TModel&Model $instance
     * @param MacroDefinition $definition
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function computeMacro(Model $instance, MacroDefinition $definition): mixed;

    /**
     * Saves the model.
     * If a model is marked as new, all set fields are treated as modified.
     * If a model is not new, only the modified fields are saved.
     *
     * @param Model $instance
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function save(Model $instance): void;

    /**
     * Runs the queued save tasks.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function saveTasks(): void;

    /**
     * Returns TRUE if the model is modified. If a key is given
     * only that key is checked.
     *
     * @param string|null $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 12-08-2024
     */
    public function isModified(?string $key): bool;

    /**
     * Returns the value for the given key.
     *
     * @param TModel&Model $instance
     * @param string $key
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getValue(Model $instance, string $key): mixed;

    /**
     * Returns TRUE if the given key exists.
     *
     * @param TModel&Model $instance
     * @param string $key
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(Model $instance, string $key): bool;

    /**
     * Sets the value of the given key.
     *
     * @param TModel&Model $instance
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(Model $instance, string $key, mixed $value): void;

    /**
     * Removes the value for the given key.
     *
     * @param TModel&Model $instance
     * @param string $key
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(Model $instance, string $key): void;

}
