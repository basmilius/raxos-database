<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use JsonSerializable;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Collection\Arrayable;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Stringable;

/**
 * Interface ModelInterface
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 */
interface ModelInterface extends Arrayable, ArrayAccess, DebugInfoInterface, MarkVisibilityInterface, JsonSerializable, Stringable
{

    /**
     * Clones the model.
     *
     * @return TModel&Model
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function clone(): static;

    /**
     * Deletes the model instance from the database.
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function destroy(): void;

    /**
     * Marks all keys as hidden, except for the given ones.
     *
     * @param string[]|string $keys
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function only(array|string $keys): static;

    /**
     * Saves the model.
     * If a model is marked as new, all set fields are treated as modified.
     * If a model is not new, only the modified fields are saved.
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function save(): void;

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function toArray(): array;

    /**
     * Returns the value for the given key.
     *
     * @param string $key
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getValue(string $key): mixed;

    /**
     * Returns TRUE if the given key exists.
     *
     * @param string $key
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(string $key): bool;

    /**
     * Sets the value of the given key.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(string $key, mixed $value): void;

    /**
     * Removes the value for the given key.
     *
     * @param string $key
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(string $key): void;

    /**
     * Shortcut for {@see Model::queryRelation()}.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return QueryInterface<Model>
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __call(string $name, array $arguments): QueryInterface;

}
