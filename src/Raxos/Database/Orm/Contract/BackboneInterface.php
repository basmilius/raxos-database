<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use JetBrains\PhpStorm\ExpectedValues;
use Raxos\Database\Contract\{ConnectionInterface, QueryInterface};
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Database\Orm\Structure\Structure;

/**
 * Interface BackboneInterface
 *
 * @template TModel
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.0.17
 */
interface BackboneInterface
{

    public CacheInterface $cache {
        get;
    }

    public ConnectionInterface $connection {
        get;
    }

    public Structure $structure {
        get;
    }

    /**
     * Adds the given model instance.
     *
     * @param TModel&Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addInstance(Model $instance): void;

    /**
     * Returns a new instance.
     *
     * @return Model
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createInstance(): Model;

    /**
     * Removes the given model instance.
     *
     * @param TModel&Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function removeInstance(Model $instance): void;

    /**
     * Adds a save task.
     *
     * @param callable $fn
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addSaveTask(callable $fn): void;

    /**
     * Runs any save tasks that are queued.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function runSaveTasks(): void;

    /**
     * Returns the cast value.
     *
     * @template TCaster of CasterInterface
     *
     * @param class-string<TCaster> $caster
     * @param string $mode
     * @param mixed $value
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getCastedValue(string $caster, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value): mixed;

    /**
     * Returns the value of the given column.
     *
     * @param ColumnDefinition $property
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getColumnValue(ColumnDefinition $property): mixed;

    /**
     * Returns the value of the given macro.
     *
     * @param MacroDefinition $property
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getMacroValue(MacroDefinition $property): mixed;

    /**
     * Returns the value of the given relation.
     *
     * @param RelationDefinition $property
     *
     * @return TModel&Model|ModelArrayList<int, TModel&Model>|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getRelationValue(RelationDefinition $property): Model|ModelArrayList|null;

    /**
     * Sets the value of the given column property.
     *
     * @param ColumnDefinition $property
     * @param mixed $value
     *
     * @return void
     * @throws InstanceException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setColumnValue(ColumnDefinition $property, mixed $value): void;

    /**
     * Sets the value of the given relation property.
     *
     * @param RelationDefinition $property
     * @param mixed $value
     *
     * @return void
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setRelationValue(RelationDefinition $property, mixed $value): void;

    /**
     * Gets the value(s) of the primary key(s).
     *
     * @return array|null
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getPrimaryKeyValues(): array|null;

    /**
     * Returns TRUE if the model is modified, or if a key is given, if
     * that property is modified.
     *
     * @param string|null $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function isModified(?string $key = null): bool;

    /**
     * Queries the relation with the given key.
     *
     * @param TModel&Model $instance
     * @param string $key
     *
     * @return QueryInterface
     * @throws ConnectionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function queryRelation(Model $instance, string $key): QueryInterface;

    /**
     * Reloads the record of the model. This will also flush the caster,
     * macro and relation cache to start fresh.
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function reload(): void;

    /**
     * Saves the model.
     * - If the model is new, a new record is created in the database,
     *   and all fields are treated as modified.
     * - If the model is loaded from the database, only the fields that
     *   are actually modified are saved.
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.19
     */
    public function save(): void;

}
