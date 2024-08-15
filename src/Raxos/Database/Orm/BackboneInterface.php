<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\ExpectedValues;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Caster\CasterInterface;
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Database\Query\QueryInterface;

/**
 * Interface BackboneInterface
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 13-08-2024
 */
interface BackboneInterface
{

    /**
     * Adds the given model instance.
     *
     * @param TModel&Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function addInstance(Model $instance): void;

    /**
     * Returns a new instance.
     *
     * @return Model
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function createInstance(): Model;

    /**
     * Removes the given model instance.
     *
     * @param TModel&Model $instance
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function removeInstance(Model $instance): void;

    /**
     * Adds a save task.
     *
     * @param callable $fn
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
     */
    public function addSaveTask(callable $fn): void;

    /**
     * Runs any save tasks that are queued.
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 14-08-2024
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
     * @since 13-08-2024
     */
    public function getCastedValue(string $caster, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value): mixed;

    /**
     * Returns the value of the given column.
     *
     * @param ColumnDefinition $property
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function getColumnValue(ColumnDefinition $property): mixed;

    /**
     * Returns the value of the given macro.
     *
     * @param MacroDefinition $property
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function getMacroValue(MacroDefinition $property): mixed;

    /**
     * Returns the value of the given relation.
     *
     * @param RelationDefinition $property
     *
     * @return TModel&Model|ModelArrayList<TModel&Model>|null
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
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
     * @since 13-08-2024
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
     * @since 15-08-2024
     */
    public function setRelationValue(RelationDefinition $property, mixed $value): void;

    /**
     * Gets the value(s) of the primary key(s).
     *
     * @return array|null
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
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
     * @since 13-08-2024
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
     * @since 14-08-2024
     */
    public function queryRelation(Model $instance, string $key): QueryInterface;

}
