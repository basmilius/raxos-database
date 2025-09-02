<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

use Generator;
use Raxos\Database\Contract\ConnectionInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Definition\{ColumnDefinition, PolymorphicDefinition, PropertyDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Literal\ColumnLiteral;
use Raxos\Foundation\Contract\ArrayListInterface;

/**
 * Interface StructureInterface
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 2.0.0
 */
interface StructureInterface
{

    /** @var class-string<TModel> */
    public string $class {
        get;
    }

    public string $connectionId {
        get;
    }

    /** @var string[]|null */
    public ?array $onDuplicateKeyUpdate {
        get;
    }

    public ConnectionInterface $connection {
        get;
    }

    public ?PolymorphicDefinition $polymorphic {
        get;
    }

    /** @var ColumnDefinition[]|null */
    public ?array $primaryKey {
        get;
    }

    /** @var PropertyDefinition[] */
    public array $properties {
        get;
    }

    public ?string $softDeleteColumn {
        get;
    }

    public string $table {
        get;
    }

    public ?self $parent {
        get;
    }

    /**
     * Creates a new instance of the model.
     *
     * @param array<string, mixed> $data
     *
     * @return Model
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function createInstance(array $data): Model;

    /**
     * Eager loads the given relation for the given instances.
     *
     * @param RelationInterface<TModel, Model> $relation
     * @param ArrayListInterface<int, TModel> $instances
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eagerLoadRelation(RelationInterface $relation, ArrayListInterface $instances): void;

    /**
     * Eager loads the relations of the given instances.
     *
     * @param ArrayListInterface<int, TModel> $instances
     * @param string[] $enabled
     * @param string[] $disabled
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function eagerLoadRelations(ArrayListInterface $instances, array $enabled = [], array $disabled = []): void;

    /**
     * Returns the definition of a property.
     *
     * @param string $key
     *
     * @return PropertyDefinition
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getProperty(string $key): PropertyDefinition;

    /**
     * Returns TRUE if a property with the given key is declared on the model.
     *
     * @param string $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function hasProperty(string $key): bool;

    /**
     * Returns a column literal for the given key.
     *
     * @param string $key
     * @param string|null $table
     *
     * @return ColumnLiteral
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getColumn(string $key, ?string $table = null): ColumnLiteral;

    /**
     * Returns the relation instance for a given property.
     *
     * @param RelationDefinition $property
     *
     * @return RelationInterface<TModel, Model>
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelation(RelationDefinition $property): RelationInterface;

    /**
     * Generates the relation properties of the model.
     *
     * @return Generator<RelationInterface<TModel, Model>>
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelations(): Generator;

    /**
     * Returns the first primary key as a column literal for use in relations.
     *
     * @return ColumnLiteral
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getRelationPrimaryKey(): ColumnLiteral;

    /**
     * Returns the primary key columns.
     *
     * @return ColumnDefinition[]|null
     * @author Bas Milius <bas@mili.us>
     * @since 2.0.0
     */
    public function getPrimaryKey(): ?array;

}
